<?php
declare(strict_types=1);

/*
 * Bootstrap: Konfiguration, Fehlerbehandlung, Session, Datenbank.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);

$defaults = [
    'env'         => 'dev',                       // dev | prod
    'data_dir'    => APP_ROOT . '/data',
    'base_url'    => null,                        // null = automatisch ermitteln
    'app_name'    => 'EmergencyContacts',
    'default_lang'=> 'de',
    'default_retention_days' => 7,
    'mail' => [
        'transport' => 'file',                    // file | smtp | mail
        'from'      => 'noreply@emergencycontacts.local',
        'from_name' => 'EmergencyContacts',
        'smtp_host' => 'localhost',
        'smtp_port' => 25,
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_secure' => '',                      // '' | tls | ssl
    ],
];
$local = is_file(APP_ROOT . '/config.php') ? require APP_ROOT . '/config.php' : [];
$GLOBALS['config'] = array_replace_recursive($defaults, is_array($local) ? $local : []);
if (getenv('EC_DATA_DIR')) {
    $GLOBALS['config']['data_dir'] = getenv('EC_DATA_DIR');   // z. B. für Tests
}

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

date_default_timezone_set('Europe/Zurich');
mb_internal_encoding('UTF-8');

if (config('env') === 'dev') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

$dataDir = config('data_dir');
foreach ([$dataDir, $dataDir . '/sessions', $dataDir . '/mail', $dataDir . '/logs'] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
}
ini_set('error_log', $dataDir . '/logs/php-error.log');
ini_set('log_errors', '1');

// Zugriff auf data/ direkt über den Webserver sperren (IIS + Apache)
// (Die Haupt-web.config sperrt data/ zusätzlich über hiddenSegments.)
$dataWebConfig = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <security>\n      <requestFiltering>\n        <fileExtensions allowUnlisted=\"false\" />\n      </requestFiltering>\n    </security>\n  </system.webServer>\n</configuration>\n";
if (@file_get_contents($dataDir . '/web.config') !== $dataWebConfig) {
    @file_put_contents($dataDir . '/web.config', $dataWebConfig);
}
if (!is_file($dataDir . '/.htaccess')) {
    @file_put_contents($dataDir . '/.htaccess', "Require all denied\n");
}

require APP_DIR . '/helpers.php';
require APP_DIR . '/db.php';
require APP_DIR . '/crypto.php';
require APP_DIR . '/i18n.php';
require APP_DIR . '/settings.php';
require APP_DIR . '/auth.php';
require APP_DIR . '/mailer.php';
require APP_DIR . '/participant.php';
require APP_DIR . '/events.php';

if (PHP_SAPI !== 'cli') {
    session_save_path($dataDir . '/sessions');
    session_name('ecsid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_start();

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, private');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'");
}

db_migrate();
i18n_init();
