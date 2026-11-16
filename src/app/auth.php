<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $cache = [];
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = db_one('SELECT id, email, name, lang, role, created_at FROM users WHERE id = ?', [$id]);
        if (!$cache[$id]) {
            unset($_SESSION['user_id']);
        }
    }
    return $cache[$id];
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? url();
        flash('info', t('auth.login_required'));
        redirect(url('login'));
    }
    return $user;
}

const ROLES = ['user', 'organizer', 'admin'];

/** Das erste Konto einer Installation wird automatisch Admin. */
function create_user(string $email, string $name, string $password, string $lang): int
{
    $role = db_one("SELECT 1 FROM users WHERE role = 'admin'") ? 'user' : 'admin';
    return db_insert(
        'INSERT INTO users (email, name, password_hash, lang, role, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [mb_strtolower($email), $name, password_hash($password, PASSWORD_DEFAULT), $lang, $role, now()]
    );
}

function is_admin(?array $user): bool
{
    return $user !== null && ($user['role'] ?? '') === 'admin';
}

/** Darf Events anlegen (freigegebene Organisator:innen und Admins). */
function can_organize(?array $user): bool
{
    return $user !== null && in_array($user['role'] ?? '', ['organizer', 'admin'], true);
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin($user)) {
        forbidden();
    }
    return $user;
}

function admin_count(): int
{
    return (int)db_one("SELECT COUNT(*) c FROM users WHERE role = 'admin'")['c'];
}

/** Anzahl Events der Person, die noch nicht bereinigt sind (Zugriffsfrist läuft oder Event steht bevor). */
function user_open_event_count(int $userId): int
{
    return (int)db_one('SELECT COUNT(*) c FROM events WHERE owner_id = ? AND purged_at IS NULL', [$userId])['c'];
}

/**
 * Löscht ein Konto. Anmeldungen an abgeschlossenen Events werden gelöscht, solche an laufenden
 * Events bleiben anonym bestehen (und werden mit der Aufbewahrung gelöscht).
 * Offene eigene Events gehen an $transferTo über; ohne Übernahme dürfen keine offenen Events bestehen.
 */
function delete_user_account(int $userId, ?int $transferTo = null): void
{
    if ($transferTo === null && user_open_event_count($userId) > 0) {
        throw new RuntimeException('Konto besitzt noch offene Events');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach (db_all('SELECT e.*, r.id AS reg_id FROM registrations r JOIN events e ON e.id = r.event_id WHERE r.user_id = ?', [$userId]) as $row) {
            if (!event_access_active($row)) {
                db_exec('DELETE FROM access_log WHERE registration_id = ?', [$row['reg_id']]);
                db_exec('DELETE FROM registrations WHERE id = ?', [$row['reg_id']]);
            }
        }
        if ($transferTo !== null) {
            foreach (db_all('SELECT id FROM events WHERE owner_id = ? AND purged_at IS NULL', [$userId]) as $ev) {
                db_exec('UPDATE events SET owner_id = ? WHERE id = ?', [$transferTo, $ev['id']]);
                db_exec('DELETE FROM event_team WHERE event_id = ? AND user_id = ?', [$ev['id'], $transferTo]);
                db_exec("UPDATE event_team SET user_id = ? WHERE event_id = ? AND user_id = ? AND role = 'owner'", [$transferTo, $ev['id'], $userId]);
            }
        }
        // Übrig sind nur noch bereinigte (abgeschlossene) eigene Events
        db_exec('DELETE FROM events WHERE owner_id = ?', [$userId]);
        db_exec('DELETE FROM users WHERE id = ?', [$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function find_user_by_email(string $email): ?array
{
    return db_one('SELECT * FROM users WHERE email = ?', [mb_strtolower($email)]);
}

function attempt_login(string $email, string $password): ?array
{
    $user = find_user_by_email($email);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['lang'] = $user['lang'];
}

function logout_user(): void
{
    $lang = $_SESSION['lang'] ?? null;
    $_SESSION = [];
    session_regenerate_id(true);
    if ($lang) {
        $_SESSION['lang'] = $lang;
    }
}

function password_problem(string $password): ?string
{
    if (mb_strlen($password) < 10) {
        return t('auth.password_too_short');
    }
    return null;
}
