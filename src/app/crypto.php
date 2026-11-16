<?php
declare(strict_types=1);

/*
 * Verschlüsselung der Personendaten in der Datenbank (AES-256-GCM).
 * Der Schlüssel liegt in config.php ('secret_key', base64) oder wird beim
 * ersten Start in data/secret.key erzeugt.
 */

function crypto_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $configured = config('secret_key');
    if (is_string($configured) && $configured !== '') {
        $key = base64_decode($configured, true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('secret_key in config.php muss 32 Bytes (base64) lang sein.');
        }
        return $key;
    }
    $file = config('data_dir') . '/secret.key';
    if (!is_file($file)) {
        $fh = @fopen($file, 'x');
        if ($fh) {
            fwrite($fh, base64_encode(random_bytes(32)));
            fclose($fh);
        }
    }
    $key = base64_decode(trim((string)file_get_contents($file)), true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('Ungültiger Schlüssel in data/secret.key');
    }
    return $key;
}

function encrypt_data(array $data): string
{
    $iv = random_bytes(12);
    $tag = '';
    $plain = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', crypto_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Verschlüsselung fehlgeschlagen');
    }
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function decrypt_data(string $blob): array
{
    if (!str_starts_with($blob, 'v1:')) {
        return [];
    }
    $raw = base64_decode(substr($blob, 3), true);
    if ($raw === false || strlen($raw) < 28) {
        return [];
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', crypto_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        return [];
    }
    $data = json_decode($plain, true);
    return is_array($data) ? $data : [];
}
