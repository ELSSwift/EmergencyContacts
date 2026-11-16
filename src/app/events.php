<?php
declare(strict_types=1);

/*
 * Events, Team, Anmeldungen, Notfallzugriff und Aufbewahrung.
 */

function find_event(int $id): ?array
{
    return db_one('SELECT * FROM events WHERE id = ?', [$id]);
}

function find_event_by_token(string $token): ?array
{
    return $token === '' ? null : db_one('SELECT * FROM events WHERE reg_token = ?', [$token]);
}

function team_role(int $eventId, int $userId): ?string
{
    return db_one('SELECT role FROM event_team WHERE event_id = ? AND user_id = ?', [$eventId, $userId])['role'] ?? null;
}

/** Letzter Tag, an dem das Team noch Zugriff hat. */
function access_until(array $event): string
{
    return date('Y-m-d', strtotime($event['ends_on'] . ' +' . (int)$event['retention_days'] . ' days'));
}

function event_access_active(array $event, ?string $today = null): bool
{
    return empty($event['purged_at']) && ($today ?? today()) <= access_until($event);
}

function event_registration_open(array $event): bool
{
    return (bool)$event['registration_open'] && empty($event['purged_at']) && today() <= $event['ends_on'];
}

function create_event(int $ownerId, array $e): int
{
    $id = db_insert(
        'INSERT INTO events (owner_id, title, description, location, starts_on, ends_on, retention_days, reg_token, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$ownerId, $e['title'], $e['description'], $e['location'], $e['starts_on'], $e['ends_on'], $e['retention_days'], random_token(18), now()]
    );
    db_exec('INSERT INTO event_team (event_id, user_id, role, added_at) VALUES (?, ?, ?, ?)', [$id, $ownerId, 'owner', now()]);
    return $id;
}

function event_from_post(): array
{
    return [
        'title' => post_str('title', 150),
        'description' => post_str('description', 2000),
        'location' => post_str('location', 200),
        'starts_on' => post_str('starts_on', 10),
        'ends_on' => post_str('ends_on', 10),
        'retention_days' => (int)post_str('retention_days', 4),
        'registration_open' => !empty($_POST['registration_open']),
    ];
}

function validate_event(array $e): array
{
    $errors = [];
    if ($e['title'] === '') {
        $errors['title'] = t('validation.required');
    }
    if (!valid_date($e['starts_on'])) {
        $errors['starts_on'] = t('validation.date');
    }
    if (!valid_date($e['ends_on'])) {
        $errors['ends_on'] = t('validation.date');
    } elseif (valid_date($e['starts_on']) && $e['ends_on'] < $e['starts_on']) {
        $errors['ends_on'] = t('validation.end_before_start');
    }
    if ($e['retention_days'] < 0 || $e['retention_days'] > 90) {
        $errors['retention_days'] = t('validation.retention');
    }
    return $errors;
}

function registration_save(array $event, array $data, ?int $userId, ?array $existing = null): array
{
    $s = contact_summary($data);
    if ($existing) {
        db_exec(
            'UPDATE registrations SET data_enc = ?, has_contact = ?, contact_methods = ?, medical_status = ?, updated_at = ? WHERE id = ?',
            [encrypt_data($data), (int)$s['has_contact'], $s['methods'], $s['medical_status'], now(), $existing['id']]
        );
        return db_one('SELECT * FROM registrations WHERE id = ?', [$existing['id']]);
    }
    $id = db_insert(
        'INSERT INTO registrations (event_id, user_id, edit_token, data_enc, has_contact, contact_methods, medical_status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$event['id'], $userId, random_token(24), encrypt_data($data), (int)$s['has_contact'], $s['methods'], $s['medical_status'], now(), now()]
    );
    return db_one('SELECT * FROM registrations WHERE id = ?', [$id]);
}

function registration_data(array $reg): array
{
    return normalize_participant(decrypt_data($reg['data_enc']));
}

function find_registration_by_token(string $token): ?array
{
    return $token === '' ? null : db_one('SELECT * FROM registrations WHERE edit_token = ?', [$token]);
}

function access_log_for_registration(int $regId): array
{
    return db_all('SELECT accessor_name, reason, accessed_at FROM access_log WHERE registration_id = ? ORDER BY accessed_at DESC', [$regId]);
}

/**
 * Notfallzugriff protokollieren und betroffene Person benachrichtigen.
 * Gibt die entschlüsselten Daten zurück.
 */
function emergency_access(array $event, array $reg, array $user, string $reason): array
{
    $data = registration_data($reg);
    $logId = db_insert(
        'INSERT INTO access_log (event_id, registration_id, user_id, accessor_name, participant_label, reason, accessed_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$event['id'], $reg['id'], $user['id'], $user['name'], participant_label($data), $reason, now()]
    );

    $lang = current_lang();
    if ($reg['user_id']) {
        $lang = db_one('SELECT lang FROM users WHERE id = ?', [$reg['user_id']])['lang'] ?? $lang;
    }
    $vars = [
        'name' => participant_label($data),
        'event' => $event['title'],
        'accessor' => $user['name'],
        'time' => format_datetime(now()),
        'reason' => $reason,
        'link' => absolute_url('mine', ['k' => $reg['edit_token']]),
    ];
    $sent = send_mail(
        $data['notify_email'],
        t_lang($lang, 'mail.access.subject', $vars),
        t_lang($lang, 'mail.access.body', $vars)
    );
    db_exec('UPDATE access_log SET notified = ? WHERE id = ?', [$sent ? 1 : 0, $logId]);
    app_log("Notfallzugriff event={$event['id']} reg={$reg['id']} user={$user['id']} notified=" . ($sent ? 1 : 0));
    return $data;
}

/**
 * Aufbewahrung: Nach Ablauf der Zugriffsfrist werden anonyme Anmeldungen gelöscht.
 * Anmeldungen mit Konto bleiben beim Konto erhalten, das Team hat keinen Zugriff mehr.
 */
function run_retention(?string $today = null): array
{
    $today ??= today();
    $deleted = 0;
    $closed = 0;
    foreach (db_all('SELECT * FROM events WHERE purged_at IS NULL') as $event) {
        if (event_access_active($event, $today)) {
            continue;
        }
        // Protokolleinträge enthalten den Namen -> zusammen mit den anonymen Anmeldungen löschen
        db_exec('DELETE FROM access_log WHERE registration_id IN (SELECT id FROM registrations WHERE event_id = ? AND user_id IS NULL)', [$event['id']]);
        $deleted += db_exec('DELETE FROM registrations WHERE event_id = ? AND user_id IS NULL', [$event['id']]);
        db_exec('UPDATE events SET purged_at = ?, registration_open = 0 WHERE id = ?', [now(), $event['id']]);
        $closed++;
    }
    if ($closed > 0) {
        app_log("Aufbewahrung: $closed Event(s) abgeschlossen, $deleted anonyme Anmeldung(en) gelöscht");
    }
    meta_set('retention_last_run', now());
    return ['events' => $closed, 'deleted' => $deleted];
}

/** Aufbewahrung höchstens stündlich bei normalen Requests ausführen. */
function maybe_run_retention(): void
{
    $last = meta_get('retention_last_run');
    if (!$last || strtotime($last) < time() - 3600) {
        run_retention();
    }
}
