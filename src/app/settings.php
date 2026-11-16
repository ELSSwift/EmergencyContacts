<?php
declare(strict_types=1);

/*
 * Vereinseinstellungen (im Admin-Bereich pflegbar, in der Tabelle settings gespeichert).
 */

const LOGO_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
const LOGO_MAX_BYTES = 1048576;

function settings_defaults(): array
{
    return [
        'club_name' => (string)config('app_name'),
        'primary_color' => '#b3261e',
        'contact_email' => '',
        'website' => '',
        'imprint' => '',
        'enabled_langs' => array_keys(SUPPORTED_LANGS),
        'default_lang' => (string)config('default_lang'),
        'default_retention_days' => (int)config('default_retention_days'),
        'logo' => '',              // Dateiname in data/, leer = Standard-Icon
        'logo_version' => 0,
        'base_url' => (string)(config('base_url') ?? ''),
        // E-Mail-Versand (Startwerte aus config.php)
        'mail_transport' => (string)config('mail.transport', 'file'),
        'mail_from' => (string)config('mail.from', ''),
        'mail_from_name' => '',    // leer = Vereinsname
        'mail_reply_to' => '',     // leer = Kontakt-E-Mail
        'mail_smtp_host' => (string)config('mail.smtp_host', ''),
        'mail_smtp_port' => (int)config('mail.smtp_port', 587),
        'mail_smtp_secure' => (string)config('mail.smtp_secure', ''),
        'mail_smtp_user' => (string)config('mail.smtp_user', ''),
        'mail_smtp_pass_enc' => '', // verschlüsselt; leer = Wert aus config.php
        // pro Sprache: home_title_de, home_text_de, privacy_de, …
    ];
}

const MAIL_TRANSPORTS = ['file', 'smtp', 'mail'];
const SMTP_SECURE = ['', 'tls', 'ssl'];

function mail_transport(): string
{
    $t = (string)setting('mail_transport');
    return in_array($t, MAIL_TRANSPORTS, true) ? $t : 'file';
}

function smtp_password(): string
{
    $enc = (string)setting('mail_smtp_pass_enc', '');
    if ($enc !== '') {
        return (string)(decrypt_data($enc)['p'] ?? '');
    }
    return (string)config('mail.smtp_pass', '');
}

function smtp_password_set(?string $password): void
{
    setting_set('mail_smtp_pass_enc', $password === null || $password === '' ? '' : encrypt_data(['p' => $password]));
}

function has_smtp_password(): bool
{
    return smtp_password() !== '';
}

function valid_base_url(string $u): bool
{
    return (bool)preg_match('#^https?://[A-Za-z0-9.\-]+(:\d{1,5})?(/[^\s<>"?\#]*)?$#', $u);
}

function valid_hostname(string $h): bool
{
    return (bool)preg_match('/^[A-Za-z0-9]([A-Za-z0-9\-]*[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9\-]*[A-Za-z0-9])?)*$/', $h) && strlen($h) <= 253;
}

function settings_all(): array
{
    if (!isset($GLOBALS['settings_cache'])) {
        $values = settings_defaults();
        foreach (db_all('SELECT key, value FROM settings') as $row) {
            $decoded = json_decode($row['value'], true);
            $values[$row['key']] = $decoded;
        }
        $GLOBALS['settings_cache'] = $values;
    }
    return $GLOBALS['settings_cache'];
}

function setting(string $key, mixed $default = null): mixed
{
    $all = settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function setting_set(string $key, mixed $value): void
{
    db_exec('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)', [$key, json_encode($value, JSON_UNESCAPED_UNICODE)]);
    unset($GLOBALS['settings_cache']);
}

/** Text pro Sprache mit Rückfall auf die Standardsprache des Vereins. */
function setting_text(string $key, ?string $lang = null): string
{
    $lang ??= current_lang();
    $v = trim((string)setting($key . '_' . $lang, ''));
    if ($v === '') {
        $v = trim((string)setting($key . '_' . setting('default_lang'), ''));
    }
    return $v;
}

function club_name(): string
{
    $n = trim((string)setting('club_name'));
    return $n !== '' ? $n : (string)config('app_name');
}

function enabled_langs(): array
{
    $enabled = array_values(array_intersect(array_keys(SUPPORTED_LANGS), (array)setting('enabled_langs', [])));
    return $enabled ?: ['de'];
}

function valid_hex_color(string $c): bool
{
    return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $c);
}

/** Etwas dunklere/hellere Variante einer Farbe (für Hover). */
function shade_color(string $hex, float $factor): string
{
    $rgb = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    $out = array_map(function ($v) use ($factor) {
        $v = $factor < 0 ? $v * (1 + $factor) : $v + (255 - $v) * $factor;
        return str_pad(dechex((int)round(max(0, min(255, $v)))), 2, '0', STR_PAD_LEFT);
    }, $rgb);
    return '#' . implode('', $out);
}

/** Kontrastfarbe für Text auf der Hauptfarbe. */
function contrast_text(string $hex): string
{
    [$r, $g, $b] = array_map(fn($v) => hexdec($v) / 255, str_split(ltrim($hex, '#'), 2));
    $lin = fn($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    $lum = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    return $lum > 0.45 ? '#1c2230' : '#ffffff';
}

function logo_path(): ?string
{
    $file = (string)setting('logo', '');
    if ($file === '' || !preg_match('/^logo\.(png|jpg|webp)$/', $file)) {
        return null;
    }
    $path = config('data_dir') . '/' . $file;
    return is_file($path) ? $path : null;
}

function logo_url(): string
{
    return logo_path() ? url('logo', ['v' => (int)setting('logo_version', 0)]) : 'assets/icon.svg';
}

/**
 * Hochgeladenes Logo prüfen und speichern.
 * @return string|null Fehlermeldung oder null bei Erfolg
 */
function store_logo(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return t('admin.logo_upload_failed');
    }
    if ($file['size'] > LOGO_MAX_BYTES) {
        return t('admin.logo_too_large');
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? '';
    if (!isset(LOGO_TYPES[$mime])) {
        return t('admin.logo_wrong_type');
    }
    $name = 'logo.' . LOGO_TYPES[$mime];
    $dir = config('data_dir');
    foreach (LOGO_TYPES as $ext) {
        @unlink($dir . '/logo.' . $ext);
    }
    $ok = is_uploaded_file($file['tmp_name'])
        ? move_uploaded_file($file['tmp_name'], $dir . '/' . $name)
        : copy($file['tmp_name'], $dir . '/' . $name);
    if (!$ok) {
        return t('admin.logo_upload_failed');
    }
    setting_set('logo', $name);
    setting_set('logo_version', (int)setting('logo_version', 0) + 1);
    return null;
}

function remove_logo(): void
{
    foreach (LOGO_TYPES as $ext) {
        @unlink(config('data_dir') . '/logo.' . $ext);
    }
    setting_set('logo', '');
    setting_set('logo_version', (int)setting('logo_version', 0) + 1);
}
