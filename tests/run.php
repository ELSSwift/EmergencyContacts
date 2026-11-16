<?php
declare(strict_types=1);

/*
 * Einfache Tests ohne Abhängigkeiten:  php tests/run.php
 */

$tmp = sys_get_temp_dir() . '/ec-test-' . bin2hex(random_bytes(4));
mkdir($tmp);
putenv('EC_DATA_DIR=' . $tmp);
require dirname(__DIR__) . '/src/app/bootstrap.php';

$failures = 0;
$count = 0;
function check(string $name, bool $cond): void
{
    global $failures, $count;
    $count++;
    echo ($cond ? '  ok   ' : '  FAIL ') . $name . "\n";
    if (!$cond) {
        $failures++;
    }
}

echo "Übersetzungen\n";
$de = require dirname(__DIR__) . '/src/app/lang/de.php';
foreach (['en', 'fr', 'it'] as $l) {
    $other = require dirname(__DIR__) . "/src/app/lang/$l.php";
    $missing = array_diff(array_keys($de), array_keys($other));
    $extra = array_diff(array_keys($other), array_keys($de));
    check("$l hat alle Schlüssel" . ($missing ? ' (fehlt: ' . implode(', ', $missing) . ')' : ''), !$missing);
    check("$l hat keine überzähligen Schlüssel" . ($extra ? ' (' . implode(', ', $extra) . ')' : ''), !$extra);
}
// Alle in Code verwendeten Schlüssel existieren
$used = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/src')) as $f) {
    if ($f->getExtension() === 'php' && !str_contains($f->getPathname(), DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR)) {
        preg_match_all("/\\bt(?:_lang)?\\((?:\\\$\\w+,\\s*)?'([a-z0-9_.]*[a-z0-9_])'/", (string)file_get_contents($f->getPathname()), $m);
        foreach ($m[1] as $k) {
            $used[$k] = true;
        }
    }
}
$unknown = array_diff(array_keys($used), array_keys($de));
check('alle verwendeten Schlüssel existieren' . ($unknown ? ' (' . implode(', ', $unknown) . ')' : ''), !$unknown);

echo "Verschlüsselung\n";
$blob = encrypt_data(['a' => 'Ärztin', 'n' => 1]);
check('Roundtrip', decrypt_data($blob) === ['a' => 'Ärztin', 'n' => 1]);
check('Klartext nicht im Chiffrat', !str_contains($blob, 'rztin'));
$tampered = substr($blob, 0, -4) . (substr($blob, -4) === 'AAAA' ? 'BBBB' : 'AAAA');
check('Manipulation wird erkannt', decrypt_data($tampered) === []);

echo "Validierung & Vollständigkeit\n";
$p = empty_participant();
$errs = validate_participant($p);
check('Leeres Formular hat Fehler', isset($errs['first_name'], $errs['notify_email'], $errs['contacts']));
$p['first_name'] = 'Anna';
$p['last_name'] = 'Muster';
$p['notify_email'] = 'anna@example.ch';
$p['contacts'][0] = ['name' => 'Beat', 'relation' => 'Vater', 'phone' => '+41 79 000 00 00', 'email' => ''];
check('Gültige Minimalangaben', validate_participant($p) === []);
$s = contact_summary($p);
check('Kontakt erkannt, Methode Telefon', $s['has_contact'] && $s['methods'] === 'phone');
check('Medizinisch fehlt', $s['medical_status'] === 'missing');
$p['no_medical'] = true;
check('Medizinisch: keine relevanten', contact_summary($p)['medical_status'] === 'none');
$p['allergies'] = 'Penicillin';
check('Medizinisch angegeben', contact_summary($p)['medical_status'] === 'provided');
$p['contacts'][1] = ['name' => 'Carla', 'relation' => '', 'phone' => '', 'email' => 'nope'];
$errs = validate_participant($p);
check('Ungültige Kontakt-E-Mail', isset($errs['contacts.1.email']));

echo "Events, Notfallzugriff, Aufbewahrung\n";
$owner = create_user('orga@example.ch', 'Olga Orga', 'sehrgeheim123', 'de');
check('Login korrekt', attempt_login('ORGA@example.ch', 'sehrgeheim123') !== null);
check('Login falsch', attempt_login('orga@example.ch', 'falsch') === null);

$evId = create_event($owner, ['title' => 'Sommerlauf', 'description' => '', 'location' => 'Bern',
    'starts_on' => '2026-06-01', 'ends_on' => '2026-06-02', 'retention_days' => 5]);
$event = find_event($evId);
check('Owner im Team', team_role($evId, $owner) === 'owner');
check('Zugriff bis', access_until($event) === '2026-06-07');
check('Zugriff aktiv am letzten Tag', event_access_active($event, '2026-06-07'));
check('Zugriff abgelaufen danach', !event_access_active($event, '2026-06-08'));

$anon = registration_save($event, $p, null);
$member = create_user('teil@example.ch', 'Tina Teil', 'sehrgeheim123', 'fr');
$acc = registration_save($event, $p, $member);
check('Daten in DB verschlüsselt', !str_contains($anon['data_enc'], 'Penicillin'));
check('Flags gespeichert', (int)$anon['has_contact'] === 1 && $anon['medical_status'] === 'provided');

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$mailsBefore = count(glob($tmp . '/mail/*.json'));
$data = emergency_access($event, $anon, db_one('SELECT * FROM users WHERE id = ?', [$owner]), 'Sturz bei Posten 3');
check('Notfalldaten entschlüsselt', $data['allergies'] === 'Penicillin');
$log = access_log_for_registration((int)$anon['id']);
check('Zugriff protokolliert', count($log) === 1 && $log[0]['reason'] === 'Sturz bei Posten 3');
$mails = glob($tmp . '/mail/*.json');
check('Benachrichtigung erzeugt', count($mails) === $mailsBefore + 1);
$mail = json_decode((string)file_get_contents(end($mails)), true);
check('Benachrichtigung an notify_email', $mail['to'] === 'anna@example.ch' && str_contains($mail['body'], 'Sturz bei Posten 3'));
emergency_access($event, $acc, db_one('SELECT * FROM users WHERE id = ?', [$owner]), 'Test Sprache');
$subjects = array_map(fn($f) => json_decode((string)file_get_contents($f), true)['subject'], glob($tmp . '/mail/*.json'));
check('Benachrichtigung in Sprache des Kontos (fr)', count(array_filter($subjects, fn($s) => str_starts_with($s, 'Accès d’urgence'))) === 1);

$r = run_retention('2026-06-07');
check('Vor Ablauf nichts gelöscht', $r['deleted'] === 0 && find_registration_by_token($anon['edit_token']) !== null);
$r = run_retention('2026-06-08');
check('Nach Ablauf anonyme Anmeldung gelöscht', $r['deleted'] === 1 && find_registration_by_token($anon['edit_token']) === null);
check('Protokoll der anonymen Anmeldung gelöscht', (int)db_one('SELECT COUNT(*) c FROM access_log WHERE participant_label = ? AND registration_id IS NULL', ['Anna Muster'])['c'] === 0);
check('Anmeldung mit Konto bleibt', find_registration_by_token($acc['edit_token']) !== null);
check('Event abgeschlossen, kein Zugriff', !event_access_active(find_event($evId)));

echo "Rollen\n";
$orgaUser = db_one('SELECT * FROM users WHERE id = ?', [$owner]);
$memberUser = db_one('SELECT * FROM users WHERE id = ?', [$member]);
check('Erstes Konto ist Admin', $orgaUser['role'] === 'admin' && is_admin($orgaUser));
check('Weitere Konten sind Mitglieder', $memberUser['role'] === 'user' && !can_organize($memberUser));
db_exec("UPDATE users SET role = 'organizer' WHERE id = ?", [$member]);
$memberUser = db_one('SELECT * FROM users WHERE id = ?', [$member]);
check('Organisator:in darf Events anlegen, ist aber kein Admin', can_organize($memberUser) && !is_admin($memberUser));

echo "Konto löschen durch Admin\n";
$leaver = create_user('weg@example.ch', 'Willi Weg', 'sehrgeheim123', 'de');
$futureId = create_event($leaver, ['title' => 'Herbstlauf', 'description' => '', 'location' => 'Thun',
    'starts_on' => date('Y-m-d', strtotime('+30 days')), 'ends_on' => date('Y-m-d', strtotime('+30 days')), 'retention_days' => 3]);
$oldReg = registration_save(find_event($evId), $p, $leaver);      // Event abgeschlossen
$openReg = registration_save(find_event($futureId), $p, $leaver); // Event offen
$threw = false;
try { delete_user_account($leaver); } catch (RuntimeException $e) { $threw = true; }
check('Ohne Übernahme kein Löschen mit offenen Events', $threw && find_user_by_email('weg@example.ch') !== null);
delete_user_account($leaver, $owner);
check('Konto gelöscht', find_user_by_email('weg@example.ch') === null);
check('Offenes Event an Admin übertragen', (int)find_event($futureId)['owner_id'] === $owner && team_role($futureId, $owner) === 'owner');
check('Anmeldung an abgeschlossenem Event gelöscht', find_registration_by_token($oldReg['edit_token']) === null);
$kept = find_registration_by_token($openReg['edit_token']);
check('Anmeldung an offenem Event bleibt anonym', $kept !== null && $kept['user_id'] === null);

echo "Vereinseinstellungen\n";
check('Standardname ohne Einstellung', club_name() === config('app_name'));
setting_set('club_name', 'SLRG Testsektion');
check('Vereinsname gesetzt', club_name() === 'SLRG Testsektion');
setting_set('default_lang', 'de');
setting_set('home_text_de', 'Willkommen!');
check('Text in eigener Sprache', setting_text('home_text', 'de') === 'Willkommen!');
check('Rückfall auf Standardsprache', setting_text('home_text', 'fr') === 'Willkommen!');
setting_set('home_text_fr', 'Bienvenue !');
check('Text in Fremdsprache', setting_text('home_text', 'fr') === 'Bienvenue !');
setting_set('enabled_langs', ['de', 'fr']);
check('Aktive Sprachen', enabled_langs() === ['de', 'fr']);
setting_set('enabled_langs', ['xx']);
check('Ungültige Sprachen -> Rückfall', enabled_langs() === ['de']);
check('Farbprüfung', valid_hex_color('#00a3e0') && !valid_hex_color('red') && !valid_hex_color('#00a3e0;}'));
check('Kontrast hell/dunkel', contrast_text('#ffe600') === '#1c2230' && contrast_text('#003366') === '#ffffff');
check('Abdunkeln', shade_color('#ffffff', -0.5) === '#808080');

echo "Logo\n";
$png = $tmp . '/test.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
check('PNG wird akzeptiert', store_logo(['error' => UPLOAD_ERR_OK, 'size' => filesize($png), 'tmp_name' => $png]) === null);
check('Logo gespeichert', logo_path() !== null && str_ends_with(logo_path(), 'logo.png'));
$txt = $tmp . '/evil.png';
file_put_contents($txt, '<script>alert(1)</script>');
check('Keine Bilddatei wird abgelehnt', store_logo(['error' => UPLOAD_ERR_OK, 'size' => filesize($txt), 'tmp_name' => $txt]) !== null);
check('Altes Logo bleibt bei Fehler', logo_path() !== null);
remove_logo();
check('Logo entfernt', logo_path() === null && logo_url() === 'assets/icon.svg');

$_SERVER['HTTP_HOST'] = 'localhost';
send_mail('x@example.ch', 'Test {club}', 'Gruss {club}');
$last = glob($tmp . '/mail/*.json');
usort($last, fn($a, $b) => filemtime($a) <=> filemtime($b));
$found = array_filter(array_map(fn($f) => json_decode((string)file_get_contents($f), true), $last), fn($m) => $m['to'] === 'x@example.ch');
$m = reset($found);
check('Vereinsname in Mail ersetzt', $m && $m['subject'] === 'Test SLRG Testsektion' && $m['body'] === 'Gruss SLRG Testsektion');

echo "Mail- und Systemeinstellungen\n";
check('Startwerte aus config', mail_transport() === 'file' && setting('mail_from') === config('mail.from'));
smtp_password_set('Ge#heim!42');
check('SMTP-Passwort verschlüsselt gespeichert', !str_contains((string)db_one("SELECT value FROM settings WHERE key = 'mail_smtp_pass_enc'")['value'], 'Ge#heim'));
check('SMTP-Passwort entschlüsselt', smtp_password() === 'Ge#heim!42' && has_smtp_password());
smtp_password_set(null);
check('SMTP-Passwort entfernt', !has_smtp_password());
setting_set('contact_email', 'vorstand@example.ch');
setting_set('mail_from_name', 'Notfall-Team');
send_mail('y@example.ch', 'Reply', 'x');
$eml = array_values(array_filter(glob($tmp . '/mail/*.eml'), fn($f) => str_contains((string)file_get_contents($f), 'To: y@example.ch')));
$raw = $eml ? (string)file_get_contents($eml[0]) : '';
check('Reply-To = Kontakt-E-Mail', str_contains($raw, "Reply-To: vorstand@example.ch"));
check('Eigener Absendername', str_contains($raw, base64_encode('Notfall-Team')));
setting_set('mail_from', 'kaputt');
check('Ungültiger Absender wird gemeldet', !send_mail('y@example.ch', 'x', 'x') && mail_last_error() !== '');
setting_set('mail_from', 'noreply@example.ch');
setting_set('mail_transport', 'smtp');
setting_set('mail_smtp_host', '127.0.0.1');
setting_set('mail_smtp_port', 1);
check('SMTP-Fehler wird gemeldet', !send_mail('y@example.ch', 'x', 'x') && str_contains(mail_last_error(), 'SMTP'));
setting_set('mail_transport', 'file');
setting_set('base_url', 'https://notfall.example.ch/');
check('Basis-Adresse für Links', absolute_url('event', ['id' => 5]) === 'https://notfall.example.ch/index.php?r=event&id=5');
check('Adressprüfung', valid_base_url('https://notfall.example.ch') && valid_base_url('http://localhost:8081/ec') && !valid_base_url('javascript:alert(1)') && !valid_base_url('https://x.ch/"><b>'));
check('Hostprüfung', valid_hostname('smtp.example.ch') && valid_hostname('127.0.0.1') && !valid_hostname('smtp example') && !valid_hostname('-x.ch'));

// Aufräumen
db_exec('PRAGMA wal_checkpoint(TRUNCATE)');
echo "\n$count Tests, $failures Fehler\n";
exit($failures ? 1 : 0);
