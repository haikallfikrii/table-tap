<?php
/**
 * PDO database connection (singleton).
 */

declare(strict_types=1);

function getConfig(): array
{
    static $config = null;
    if ($config === null) {
        $path = dirname(__DIR__) . '/config/config.php';
        if (!is_file($path)) {
            http_response_code(500);
            exit('Config missing. Copy config/config.example.php to config/config.php');
        }
        $config = require $path;
        date_default_timezone_set($config['timezone'] ?? 'Asia/Kuala_Lumpur');
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = getConfig();
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $c['db_host'],
        $c['db_name'],
        $c['db_charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Do not SET time_zone here — some shared hosts reject it and used to break login.
    // All app timestamps use appNow() (PHP Asia/Kuala_Lumpur) instead.

    require_once __DIR__ . '/schema_patch.php';
    ensureAppSchema($pdo);

    return $pdo;
}

/**
 * Convert a PHP timezone name to a MySQL offset like +08:00 (works without tz tables).
 */
function mysqlTimezoneOffset(string $timezone): string
{
    try {
        $tz = new DateTimeZone($timezone);
    } catch (Throwable $e) {
        $tz = new DateTimeZone('Asia/Kuala_Lumpur');
    }
    $offsetSeconds = $tz->getOffset(new DateTimeImmutable('now', $tz));
    $sign = $offsetSeconds >= 0 ? '+' : '-';
    $abs = abs($offsetSeconds);
    return sprintf('%s%02d:%02d', $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60));
}

/** Current local app timestamp for DATETIME columns (never rely on server UTC NOW()). */
function appNow(): string
{
    getConfig();
    return date('Y-m-d H:i:s');
}

/** Inclusive local calendar day bounds [start, nextDayStart). */
function appDayBounds(string $ymd): array
{
    getConfig();
    $start = $ymd . ' 00:00:00';
    $next = date('Y-m-d', strtotime($ymd . ' +1 day')) . ' 00:00:00';
    return [$start, $next];
}
