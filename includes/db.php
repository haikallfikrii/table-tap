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

function dbConnect(): PDO
{
    $c = getConfig();
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $c['db_host'],
        $c['db_name'],
        $c['db_charset'] ?? 'utf8mb4'
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    // Shared hosting caps concurrent MySQL connections; at peak a connect can briefly fail.
    $delaysMs = [150, 400, 900];
    $attempt = 0;
    while (true) {
        try {
            return new PDO($dsn, $c['db_user'], $c['db_pass'], $options);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $busy = (bool) preg_match('/\b(1040|1203|1226|2002|2006|2013)\b|Too many connections|max_user_connections|gone away|Lost connection/i', $msg);
            if (!$busy || $attempt >= count($delaysMs)) {
                throw $e;
            }
            error_log('[DB] connect busy, retry ' . ($attempt + 1) . ': ' . $msg);
            usleep($delaysMs[$attempt] * 1000);
            $attempt++;
        }
    }
}

/** @return array{pdo:?PDO,patched:bool,schema:?array} */
function &dbState(): array
{
    static $state = ['pdo' => null, 'patched' => false, 'schema' => null];
    return $state;
}

function db(): PDO
{
    $state = &dbState();
    if ($state['pdo'] instanceof PDO) {
        return $state['pdo'];
    }

    try {
        $state['pdo'] = dbConnect();
    } catch (PDOException $e) {
        error_log('[DB] connect failed: ' . $e->getMessage());
        if (dbIsApiRequest()) {
            dbUnavailableResponse();
        }
        throw $e;
    }

    // Do not SET time_zone here — some shared hosts reject it and used to break login.
    // All app timestamps use appNow() (PHP Asia/Kuala_Lumpur) instead.

    if (!$state['patched']) {
        $state['patched'] = true;
        $state['schema'] = dbLoadSchemaCache();
        if ($state['schema'] === null) {
            require_once __DIR__ . '/schema_patch.php';
            $ok = ensureAppSchema($state['pdo']);
            $state['schema'] = dbWriteSchemaCache($state['pdo'], $ok);
        }
    }

    return $state['pdo'];
}

function dbIsApiRequest(): bool
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    return str_contains($uri, '/api/') || str_contains($accept, 'application/json');
}

/** JSON 503 so the client-side retry treats it as transient. */
function dbUnavailableResponse(): void
{
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 2');
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'ok' => false,
        'error' => 'Server sibuk. Sila cuba lagi sebentar. / Server busy, please try again.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function dbSchemaCacheFile(): string
{
    $patch = __DIR__ . '/schema_patch.php';
    $key = substr(md5(
        (string) @filemtime($patch) . ':' . (string) @filesize($patch) . ':' . (string) (getConfig()['db_name'] ?? '')
    ), 0, 12);
    return dirname(__DIR__) . '/storage/cache/schema-' . $key . '.json';
}

/**
 * Schema snapshot written after the idempotent patch runs, so normal requests skip
 * ~40 SHOW COLUMNS / CREATE TABLE IF NOT EXISTS statements. A failed patch is retried after 10 min.
 *
 * @return array<string, array<string, true>>|null
 */
function dbLoadSchemaCache(): ?array
{
    $file = dbSchemaCacheFile();
    if (!is_file($file)) {
        return null;
    }
    $data = json_decode((string) @file_get_contents($file), true);
    if (!is_array($data) || !isset($data['tables']) || !is_array($data['tables'])) {
        return null;
    }
    if (empty($data['ok']) && (time() - (int) ($data['at'] ?? 0)) > 600) {
        return null;
    }
    $map = [];
    foreach ($data['tables'] as $table => $cols) {
        $map[(string) $table] = array_fill_keys(is_array($cols) ? $cols : [], true);
    }
    return $map;
}

/** @return array<string, array<string, true>>|null */
function dbWriteSchemaCache(PDO $pdo, bool $ok): ?array
{
    try {
        $rows = $pdo->query(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
        )->fetchAll(PDO::FETCH_NUM);
    } catch (Throwable $e) {
        return null;
    }
    $tables = [];
    foreach ($rows as $r) {
        $tables[strtolower((string) $r[0])][] = strtolower((string) $r[1]);
    }
    if ($tables === []) {
        return null;
    }
    $file = dbSchemaCacheFile();
    $dir = dirname($file);
    if (is_dir($dir) || @mkdir($dir, 0775, true)) {
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode(['ok' => $ok, 'at' => time(), 'tables' => $tables])) !== false) {
            @rename($tmp, $file);
        }
    }
    $map = [];
    foreach ($tables as $table => $cols) {
        $map[$table] = array_fill_keys($cols, true);
    }
    return $map;
}

function dbHasTable(string $table): bool
{
    db();
    $state = &dbState();
    if (is_array($state['schema'])) {
        return isset($state['schema'][strtolower($table)]);
    }
    if (!preg_match('/^\w+$/', $table)) {
        return false;
    }
    try {
        return (bool) db()->query('SHOW TABLES LIKE ' . db()->quote($table))->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

function dbHasColumn(string $table, string $column): bool
{
    db();
    $state = &dbState();
    if (is_array($state['schema'])) {
        return isset($state['schema'][strtolower($table)][strtolower($column)]);
    }
    if (!preg_match('/^\w+$/', $table)) {
        return false;
    }
    try {
        return (bool) db()->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . db()->quote($column))->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Drop the cached PDO and open a fresh connection (MySQL "gone away" / idle timeout).
 * Schema patch is skipped — already applied on the first connection of this request.
 */
function dbReconnect(): PDO
{
    $state = &dbState();
    $state['pdo'] = dbConnect();
    return $state['pdo'];
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
