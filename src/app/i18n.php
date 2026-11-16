<?php
declare(strict_types=1);

const SUPPORTED_LANGS = ['de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français', 'it' => 'Italiano'];

function i18n_init(?string $forced = null): void
{
    $lang = $forced;
    if ($lang === null && PHP_SAPI !== 'cli') {
        $lang = $_SESSION['lang'] ?? ($_COOKIE['lang'] ?? null);
        if (!$lang || !in_array($lang, enabled_langs(), true)) {
            $lang = detect_browser_lang();
        }
    }
    set_lang($lang ?: (string)setting('default_lang'));
}

function detect_browser_lang(): ?string
{
    $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    foreach (explode(',', $header) as $part) {
        $code = strtolower(substr(trim($part), 0, 2));
        if (in_array($code, enabled_langs(), true)) {
            return $code;
        }
    }
    return null;
}

function set_lang(string $lang): void
{
    if (!isset(SUPPORTED_LANGS[$lang])) {
        $lang = function_exists('setting') ? (string)setting('default_lang', 'de') : 'de';
        if (!isset(SUPPORTED_LANGS[$lang])) {
            $lang = 'de';
        }
    }
    $GLOBALS['lang'] = $lang;
    $GLOBALS['strings'] = require APP_DIR . '/lang/' . $lang . '.php';
    if (!isset($GLOBALS['fallback_strings'])) {
        $GLOBALS['fallback_strings'] = require APP_DIR . '/lang/de.php';
    }
}

function current_lang(): string
{
    return $GLOBALS['lang'] ?? 'de';
}

/** Übersetzung mit Platzhaltern {name}. */
function t(string $key, array $vars = []): string
{
    $s = $GLOBALS['strings'][$key] ?? $GLOBALS['fallback_strings'][$key] ?? $key;
    foreach ($vars as $k => $v) {
        $s = str_replace('{' . $k . '}', (string)$v, $s);
    }
    return $s;
}

/** Übersetzung in einer bestimmten Sprache (z. B. für E-Mails). */
function t_lang(string $lang, string $key, array $vars = []): string
{
    $prev = current_lang();
    set_lang($lang);
    $s = t($key, $vars);
    set_lang($prev);
    return $s;
}
