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
        $cache[$id] = db_one('SELECT id, email, name, lang, created_at FROM users WHERE id = ?', [$id]);
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

function create_user(string $email, string $name, string $password, string $lang): int
{
    return db_insert(
        'INSERT INTO users (email, name, password_hash, lang, created_at) VALUES (?, ?, ?, ?, ?)',
        [mb_strtolower($email), $name, password_hash($password, PASSWORD_DEFAULT), $lang, now()]
    );
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
