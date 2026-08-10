<?php
/**
 * Safe DB connectivity check (no secrets printed).
 * https://tabletap.jomsite.com/admin/dbcheck.php?key=YOUR_CRON_SECRET
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = getConfig();
$key = (string) ($_GET['key'] ?? '');
if ($key === '' || !hash_equals((string) ($config['cron_secret'] ?? ''), $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$out = [
    'ok' => false,
    'php' => PHP_VERSION,
    'db_host_set' => isset($config['db_host']) && $config['db_host'] !== '',
    'db_name_set' => isset($config['db_name']) && $config['db_name'] !== '',
    'db_user_set' => isset($config['db_user']) && $config['db_user'] !== '',
    'db_host' => (string) ($config['db_host'] ?? ''),
    'db_name' => (string) ($config['db_name'] ?? ''),
    'hint' => 'On Hostinger website PHP, db_host must usually be "localhost" (not the remote phpMyAdmin host).',
];

try {
    $pdo = db();
    $out['ok'] = true;
    $out['server_version'] = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $out['users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $out['shops'] = (int) $pdo->query('SELECT COUNT(*) FROM shops')->fetchColumn();
    $out['now_php'] = appNow();
    $out['now_mysql'] = (string) $pdo->query('SELECT NOW()')->fetchColumn();
} catch (Throwable $e) {
    $out['error_type'] = $e::class;
    $out['error'] = $e->getMessage();
    if (stripos($e->getMessage(), 'Access denied') !== false) {
        $out['hint'] = 'Wrong db_user/db_pass in config/config.php on the server.';
    } elseif (stripos($e->getMessage(), 'Unknown database') !== false) {
        $out['hint'] = 'Wrong db_name in config/config.php on the server.';
    } elseif (stripos($e->getMessage(), 'getaddrinfo') !== false || stripos($e->getMessage(), 'Connection refused') !== false) {
        $out['hint'] = 'Wrong db_host. Use "localhost" for Hostinger site PHP.';
    }
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
