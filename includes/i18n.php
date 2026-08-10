<?php
/**
 * Internationalisation — Bahasa Melayu (default) & English.
 * Language preference stored in cookie + readable from localStorage via JS sync.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function availableLangs(): array
{
    return ['my', 'en'];
}

function detectLang(): string
{
    $c = getConfig();
    $default = $c['default_lang'] ?? 'my';

    // Query param override (useful for first paint / share links)
    if (isset($_GET['lang']) && in_array($_GET['lang'], availableLangs(), true)) {
        setLang($_GET['lang']);
        return $_GET['lang'];
    }

    if (!empty($_COOKIE['tabletap_lang']) && in_array($_COOKIE['tabletap_lang'], availableLangs(), true)) {
        return $_COOKIE['tabletap_lang'];
    }

    return in_array($default, availableLangs(), true) ? $default : 'my';
}

function setLang(string $lang): void
{
    if (!in_array($lang, availableLangs(), true)) {
        return;
    }
    setcookie('tabletap_lang', $lang, [
        'expires'  => time() + 60 * 60 * 24 * 365,
        'path'     => '/',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['tabletap_lang'] = $lang;
}

function loadLang(string $lang): array
{
    static $cache = [];
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    $file = __DIR__ . '/lang/' . $lang . '.php';
    if (!is_file($file)) {
        $file = __DIR__ . '/lang/my.php';
    }
    $cache[$lang] = require $file;
    return $cache[$lang];
}

/** Translate key. Supports sprintf-style placeholders: t('hello', $name) */
function t(string $key, mixed ...$args): string
{
    static $strings = null;
    static $lang = null;

    if ($strings === null) {
        $lang = detectLang();
        $strings = loadLang($lang);
    }

    $text = $strings[$key] ?? $key;
    if ($args !== []) {
        return sprintf($text, ...$args);
    }
    return $text;
}

function currentLang(): string
{
    return detectLang();
}
