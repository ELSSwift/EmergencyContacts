<?php
declare(strict_types=1);

/*
 * Teilnehmendendaten: Erfassen, Prüfen, Vollständigkeit.
 */

const MAX_CONTACTS = 3;
const PERSON_FIELDS = ['first_name', 'last_name', 'birthdate', 'phone', 'email', 'address'];
const MEDICAL_FIELDS = ['blood_type', 'allergies', 'medications', 'conditions', 'insurance', 'doctor', 'medical_notes'];

function empty_participant(): array
{
    $contacts = [];
    for ($i = 0; $i < MAX_CONTACTS; $i++) {
        $contacts[] = ['name' => '', 'relation' => '', 'phone' => '', 'email' => ''];
    }
    return array_fill_keys(PERSON_FIELDS, '')
        + ['notify_email' => '', 'contacts' => $contacts, 'no_medical' => false]
        + array_fill_keys(MEDICAL_FIELDS, '');
}

/** Bestehende Daten auf die aktuelle Struktur bringen. */
function normalize_participant(array $data): array
{
    $base = empty_participant();
    foreach ($base as $k => $v) {
        if ($k === 'contacts') {
            continue;
        }
        if (array_key_exists($k, $data)) {
            $base[$k] = is_bool($v) ? (bool)$data[$k] : (string)$data[$k];
        }
    }
    $contacts = is_array($data['contacts'] ?? null) ? array_values($data['contacts']) : [];
    for ($i = 0; $i < MAX_CONTACTS; $i++) {
        foreach (['name', 'relation', 'phone', 'email'] as $f) {
            $base['contacts'][$i][$f] = (string)($contacts[$i][$f] ?? '');
        }
    }
    return $base;
}

function participant_from_post(): array
{
    $d = empty_participant();
    foreach (PERSON_FIELDS as $f) {
        $d[$f] = post_str($f, $f === 'address' ? 300 : 120);
    }
    $d['notify_email'] = post_str('notify_email', 190);
    $raw = $_POST['contacts'] ?? [];
    for ($i = 0; $i < MAX_CONTACTS; $i++) {
        $c = is_array($raw[$i] ?? null) ? $raw[$i] : [];
        foreach (['name', 'relation', 'phone', 'email'] as $f) {
            $v = $c[$f] ?? '';
            $d['contacts'][$i][$f] = is_string($v) ? mb_substr(trim($v), 0, 120) : '';
        }
    }
    foreach (MEDICAL_FIELDS as $f) {
        $d[$f] = post_str($f, $f === 'blood_type' ? 10 : 2000);
    }
    $d['no_medical'] = !empty($_POST['no_medical']);
    return $d;
}

/** @return array<string,string> Feld => Fehlermeldung */
function validate_participant(array $d): array
{
    $errors = [];
    if ($d['first_name'] === '') {
        $errors['first_name'] = t('validation.required');
    }
    if ($d['last_name'] === '') {
        $errors['last_name'] = t('validation.required');
    }
    if ($d['birthdate'] !== '' && (!valid_date($d['birthdate']) || $d['birthdate'] > today())) {
        $errors['birthdate'] = t('validation.date');
    }
    if ($d['email'] !== '' && !valid_email($d['email'])) {
        $errors['email'] = t('validation.email');
    }
    if (!valid_email($d['notify_email'])) {
        $errors['notify_email'] = t('validation.notify_email');
    }
    foreach ($d['contacts'] as $i => $c) {
        $filled = $c['name'] !== '' || $c['phone'] !== '' || $c['email'] !== '' || $c['relation'] !== '';
        if (!$filled) {
            continue;
        }
        if ($c['name'] === '') {
            $errors["contacts.$i.name"] = t('validation.required');
        }
        if ($c['phone'] === '' && $c['email'] === '') {
            $errors["contacts.$i.phone"] = t('validation.contact_method');
        }
        if ($c['email'] !== '' && !valid_email($c['email'])) {
            $errors["contacts.$i.email"] = t('validation.email');
        }
    }
    if (!contact_summary($d)['has_contact']) {
        $errors['contacts'] = t('validation.contact_required');
    }
    return $errors;
}

/** Zusammenfassung für die Vollständigkeitsübersicht (ohne sensible Inhalte). */
function contact_summary(array $d): array
{
    $methods = [];
    $count = 0;
    foreach ($d['contacts'] as $c) {
        if ($c['name'] === '' || ($c['phone'] === '' && $c['email'] === '')) {
            continue;
        }
        $count++;
        if ($c['phone'] !== '') {
            $methods['phone'] = true;
        }
        if ($c['email'] !== '') {
            $methods['email'] = true;
        }
    }
    $hasMedical = false;
    foreach (MEDICAL_FIELDS as $f) {
        if (trim((string)$d[$f]) !== '') {
            $hasMedical = true;
        }
    }
    $medical = $hasMedical ? 'provided' : (!empty($d['no_medical']) ? 'none' : 'missing');
    return [
        'has_contact' => $count > 0,
        'contact_count' => $count,
        'methods' => implode(',', array_keys($methods)),
        'medical_status' => $medical,
    ];
}

function participant_label(array $d): string
{
    return trim($d['first_name'] . ' ' . $d['last_name']);
}

function load_profile(int $userId): ?array
{
    $row = db_one('SELECT data_enc FROM profiles WHERE user_id = ?', [$userId]);
    return $row ? normalize_participant(decrypt_data($row['data_enc'])) : null;
}

function save_profile(int $userId, array $data): void
{
    db_exec(
        'INSERT INTO profiles (user_id, data_enc, updated_at) VALUES (?, ?, ?)
         ON CONFLICT(user_id) DO UPDATE SET data_enc = excluded.data_enc, updated_at = excluded.updated_at',
        [$userId, encrypt_data($data), now()]
    );
}
