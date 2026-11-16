<?php
declare(strict_types=1);

/*
 * Benutzerverwaltung auf der Kommandozeile (z. B. um den ersten Admin zu setzen):
 *   php cli/users.php list
 *   php cli/users.php role <email> <user|organizer|admin>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

$cmd = $argv[1] ?? '';
if ($cmd === 'list') {
    foreach (db_all('SELECT id, email, name, role, created_at FROM users ORDER BY id') as $u) {
        printf("%4d  %-10s %-35s %s (%s)\n", $u['id'], $u['role'], $u['email'], $u['name'], $u['created_at']);
    }
    exit(0);
}
if ($cmd === 'role' && isset($argv[2], $argv[3]) && in_array($argv[3], ROLES, true)) {
    $u = find_user_by_email($argv[2]);
    if (!$u) {
        fwrite(STDERR, "Kein Konto mit dieser E-Mail.\n");
        exit(1);
    }
    db_exec('UPDATE users SET role = ? WHERE id = ?', [$argv[3], $u['id']]);
    echo "{$u['email']} ist jetzt {$argv[3]}.\n";
    exit(0);
}
fwrite(STDERR, "Verwendung:\n  php cli/users.php list\n  php cli/users.php role <email> <user|organizer|admin>\n");
exit(1);
