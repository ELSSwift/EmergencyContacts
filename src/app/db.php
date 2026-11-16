<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $path = config('db_path') ?: config('data_dir') . '/emergencycontacts.sqlite';
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }
    return $pdo;
}

function db_one(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

function db_insert(string $sql, array $params = []): int
{
    db_exec($sql, $params);
    return (int)db()->lastInsertId();
}

function db_migrate(): void
{
    $pdo = db();
    $pdo->exec('CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT)');
    $version = (int)(db_one("SELECT value FROM meta WHERE key = 'schema_version'")['value'] ?? 0);

    $migrations = [
        1 => [
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                name TEXT NOT NULL,
                password_hash TEXT NOT NULL,
                lang TEXT NOT NULL DEFAULT \'de\',
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE profiles (
                user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                data_enc TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE TABLE events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_id INTEGER NOT NULL REFERENCES users(id),
                title TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT \'\',
                location TEXT NOT NULL DEFAULT \'\',
                starts_on TEXT NOT NULL,
                ends_on TEXT NOT NULL,
                retention_days INTEGER NOT NULL DEFAULT 7,
                reg_token TEXT NOT NULL UNIQUE,
                registration_open INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL,
                purged_at TEXT
            )',
            'CREATE TABLE event_team (
                event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                role TEXT NOT NULL DEFAULT \'member\',
                added_at TEXT NOT NULL,
                PRIMARY KEY (event_id, user_id)
            )',
            'CREATE TABLE registrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
                user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                edit_token TEXT NOT NULL UNIQUE,
                data_enc TEXT NOT NULL,
                has_contact INTEGER NOT NULL DEFAULT 0,
                contact_methods TEXT NOT NULL DEFAULT \'\',
                medical_status TEXT NOT NULL DEFAULT \'missing\',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
            'CREATE INDEX idx_reg_event ON registrations(event_id)',
            'CREATE UNIQUE INDEX idx_reg_event_user ON registrations(event_id, user_id) WHERE user_id IS NOT NULL',
            'CREATE TABLE access_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
                registration_id INTEGER REFERENCES registrations(id) ON DELETE SET NULL,
                user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                accessor_name TEXT NOT NULL,
                participant_label TEXT NOT NULL DEFAULT \'\',
                reason TEXT NOT NULL,
                notified INTEGER NOT NULL DEFAULT 0,
                accessed_at TEXT NOT NULL
            )',
            'CREATE INDEX idx_access_reg ON access_log(registration_id)',
            'CREATE INDEX idx_access_event ON access_log(event_id)',
        ],
        2 => [
            // Rollen: user (nur eigene Anmeldungen), organizer (darf Events anlegen), admin (Verein verwalten)
            "ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'user'",
            "UPDATE users SET role = 'organizer' WHERE id IN (SELECT owner_id FROM events)",
            "UPDATE users SET role = 'admin' WHERE id = (SELECT MIN(id) FROM users)",
            'CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)',
        ],
    ];

    foreach ($migrations as $v => $statements) {
        if ($v <= $version) {
            continue;
        }
        $pdo->beginTransaction();
        try {
            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }
            db_exec("INSERT OR REPLACE INTO meta (key, value) VALUES ('schema_version', ?)", [(string)$v]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

function meta_get(string $key): ?string
{
    return db_one('SELECT value FROM meta WHERE key = ?', [$key])['value'] ?? null;
}

function meta_set(string $key, string $value): void
{
    db_exec('INSERT OR REPLACE INTO meta (key, value) VALUES (?, ?)', [$key, $value]);
}
