<?php
declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function random_token(int $bytes = 24): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

/** URL innerhalb der App (relativ, funktioniert in jedem Unterverzeichnis). */
function url(string $route = '', array $params = []): string
{
    $q = $route !== '' ? ['r' => $route] + $params : $params;
    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
}

/** Absolute URL für E-Mails. */
function absolute_url(string $route = '', array $params = []): string
{
    $base = function_exists('setting') ? (string)setting('base_url', '') : '';
    if ($base === '') {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $base = ($https ? 'https' : 'http') . '://' . $host . $dir;
    }
    return rtrim($base, '/') . '/' . url($route, $params);
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_token(32);
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        render('error', ['message' => t('error.csrf')]);
        exit;
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post_str(string $key, int $max = 500): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return mb_substr(trim($v), 0, $max);
}

function get_str(string $key, int $max = 200): string
{
    $v = $_GET[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return mb_substr(trim($v), 0, $max);
}

function valid_email(string $email): bool
{
    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_date(string $date): bool
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function format_date(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : $date;
}

function format_datetime(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y H:i', $ts) : $date;
}

function render(string $view, array $vars = [], string $layout = 'layout'): void
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require APP_DIR . '/views/' . $layout . '.php';
}

/** Formularfeld mit Label und Fehlermeldung. */
function field(string $name, string $label, mixed $value, array $errors = [], array $opt = []): string
{
    $type = $opt['type'] ?? 'text';
    $errKey = $opt['error_key'] ?? $name;
    $id = 'f_' . preg_replace('/[^a-z0-9_]+/i', '_', $name);
    $err = $errors[$errKey] ?? null;
    $attrs = '';
    foreach (['autocomplete', 'placeholder', 'min', 'max', 'maxlength', 'inputmode'] as $a) {
        if (isset($opt[$a])) {
            $attrs .= ' ' . $a . '="' . h($opt[$a]) . '"';
        }
    }
    if (!empty($opt['required'])) {
        $attrs .= ' required';
    }
    if ($err) {
        $attrs .= ' aria-invalid="true" aria-describedby="' . $id . '_err"';
    }
    $html = '<div class="field' . ($err ? ' has-error' : '') . (isset($opt['class']) ? ' ' . h($opt['class']) : '') . '">';
    $html .= '<label for="' . $id . '">' . h($label) . (!empty($opt['required']) ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>';
    if ($type === 'select') {
        $html .= '<select id="' . $id . '" name="' . h($name) . '"' . $attrs . '>';
        foreach ($opt['options'] ?? [] as $v => $l) {
            $html .= '<option value="' . h($v) . '"' . ((string)$v === (string)$value ? ' selected' : '') . '>' . h($l) . '</option>';
        }
        $html .= '</select>';
    } elseif ($type === 'textarea') {
        $html .= '<textarea id="' . $id . '" name="' . h($name) . '" rows="' . (int)($opt['rows'] ?? 3) . '"' . $attrs . '>' . h($value) . '</textarea>';
    } else {
        $html .= '<input type="' . h($type) . '" id="' . $id . '" name="' . h($name) . '" value="' . h($value) . '"' . $attrs . '>';
    }
    if (isset($opt['hint'])) {
        $html .= '<small class="hint">' . h($opt['hint']) . '</small>';
    }
    if ($err) {
        $html .= '<small class="error" id="' . $id . '_err">' . h($err) . '</small>';
    }
    return $html . '</div>';
}

function not_found(): never
{
    http_response_code(404);
    render('error', ['message' => t('error.not_found')]);
    exit;
}

function forbidden(): never
{
    http_response_code(403);
    render('error', ['message' => t('error.forbidden')]);
    exit;
}

function app_log(string $message): void
{
    @file_put_contents(config('data_dir') . '/logs/app.log', '[' . now() . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
}
