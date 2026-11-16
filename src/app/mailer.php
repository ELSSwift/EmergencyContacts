<?php
declare(strict_types=1);

/*
 * Einfacher Mailversand (Einstellungen unter „Verein verwalten“ → E-Mail-Versand).
 *  transport = file : E-Mails werden als .eml in data/mail abgelegt (Postausgang, zum Testen)
 *  transport = smtp : Versand über SMTP (optional STARTTLS/SSL + AUTH LOGIN)
 *  transport = mail : PHP mail()
 */

/** Fehlermeldung des letzten fehlgeschlagenen Versands (für die Testmail). */
function mail_last_error(): string
{
    return (string)($GLOBALS['mail_last_error'] ?? '');
}

function send_mail(string $to, string $subject, string $body): bool
{
    $GLOBALS['mail_last_error'] = '';
    if (!valid_email($to)) {
        $GLOBALS['mail_last_error'] = 'Ungültige Empfängeradresse';
        return false;
    }
    $subject = str_replace('{club}', club_name(), $subject);
    $body = str_replace('{club}', club_name(), $body);
    $from = (string)setting('mail_from');
    if (!valid_email($from)) {
        $GLOBALS['mail_last_error'] = 'Keine gültige Absenderadresse eingestellt';
        app_log('Mail FEHLER an ' . $to . ': ' . $GLOBALS['mail_last_error']);
        return false;
    }
    $fromName = trim((string)setting('mail_from_name')) ?: club_name();
    $replyTo = trim((string)setting('mail_reply_to')) ?: trim((string)setting('contact_email'));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'From' => '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
        'To' => $to,
        'Subject' => $encodedSubject,
        'Date' => date(DATE_RFC2822),
        'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => 'base64',
        'Auto-Submitted' => 'auto-generated',
    ];
    if ($replyTo !== '' && valid_email($replyTo)) {
        $headers['Reply-To'] = $replyTo;
    }
    $encodedBody = chunk_split(base64_encode($body));

    try {
        $ok = match (mail_transport()) {
            'smtp' => smtp_send($from, $to, $headers, $encodedBody),
            'mail' => mail($to, $encodedSubject, $encodedBody, array_diff_key($headers, ['To' => 1, 'Subject' => 1])),
            default => mail_to_file($to, $subject, $headers, $encodedBody, $body),
        };
    } catch (Throwable $e) {
        app_log('Mailversand fehlgeschlagen an ' . $to . ': ' . $e->getMessage());
        $GLOBALS['mail_last_error'] = $e->getMessage();
        $ok = false;
    }
    if (!$ok && mail_last_error() === '') {
        $GLOBALS['mail_last_error'] = 'Versand fehlgeschlagen (siehe data/logs)';
    }
    app_log('Mail ' . ($ok ? 'gesendet' : 'FEHLER') . ' an ' . $to . ': ' . $subject);
    return $ok;
}

function mail_to_file(string $to, string $subject, array $headers, string $encodedBody, string $plainBody): bool
{
    $dir = config('data_dir') . '/mail';
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
    $raw = '';
    foreach ($headers as $k => $v) {
        $raw .= $k . ': ' . $v . "\r\n";
    }
    $raw .= "\r\n" . $encodedBody;
    // Lesbare Kopie für das DEV-Postfach
    $json = json_encode(['to' => $to, 'subject' => $subject, 'body' => $plainBody, 'date' => now()], JSON_UNESCAPED_UNICODE);
    return file_put_contents($dir . '/' . $name, $raw) !== false
        && file_put_contents($dir . '/' . $name . '.json', $json) !== false;
}

function smtp_send(string $from, string $to, array $headers, string $encodedBody): bool
{
    $host = (string)setting('mail_smtp_host');
    $port = (int)setting('mail_smtp_port');
    $secure = (string)setting('mail_smtp_secure');
    if ($host === '' || $port < 1) {
        throw new RuntimeException('SMTP-Server ist nicht eingestellt');
    }
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15);
    if (!$fp) {
        throw new RuntimeException("SMTP-Verbindung fehlgeschlagen: $errstr ($errno)");
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $command, array $expect) use ($fp, $read): string {
        fwrite($fp, $command . "\r\n");
        $resp = $read();
        if (!in_array((int)substr($resp, 0, 3), $expect, true)) {
            throw new RuntimeException('SMTP-Fehler bei "' . strtok($command, ' ') . '": ' . trim($resp));
        }
        return $resp;
    };

    $read();
    $ehloHost = gethostname() ?: 'localhost';
    $cmd('EHLO ' . $ehloHost, [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS fehlgeschlagen');
        }
        $cmd('EHLO ' . $ehloHost, [250]);
    }
    $user = (string)setting('mail_smtp_user');
    if ($user !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($user), [334]);
        $cmd(base64_encode(smtp_password()), [235]);
    }
    $cmd('MAIL FROM:<' . $from . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);
    $raw = '';
    foreach ($headers as $k => $v) {
        $raw .= $k . ': ' . $v . "\r\n";
    }
    $raw .= "\r\n" . $encodedBody;
    $cmd($raw . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
    return true;
}
