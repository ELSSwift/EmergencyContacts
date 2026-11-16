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

// Aufräumen
db_exec('PRAGMA wal_checkpoint(TRUNCATE)');
echo "\n$count Tests, $failures Fehler\n";
exit($failures ? 1 : 0);
