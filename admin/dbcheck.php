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

    if (!empty($_GET['log'])) {
        $root = dirname(__DIR__);
        $candidates = array_unique(array_filter([
            (string) ini_get('error_log'),
            $root . '/error_log',
            $root . '/public/api/error_log',
            $root . '/public/error_log',
            $root . '/admin/error_log',
            $root . '/admin/api/error_log',
            dirname($root) . '/logs/error_log',
        ]));
        $pattern = '/createShopOrder|submit_order|\[DB\]|ensureAppSchema|Fatal|Uncaught|SQLSTATE/i';
        $logs = [];
        foreach ($candidates as $file) {
            if (!is_file($file) || !is_readable($file)) {
                continue;
            }
            $size = (int) filesize($file);
            $fh = fopen($file, 'rb');
            if (!$fh) {
                continue;
            }
            fseek($fh, max(0, $size - 400000));
            $chunk = (string) stream_get_contents($fh);
            fclose($fh);
            $hits = array_values(array_filter(
                explode("\n", $chunk),
                static fn(string $l): bool => (bool) preg_match($pattern, $l)
            ));
            $logs[str_replace($root, '', $file)] = [
                'size' => $size,
                'mtime' => date('Y-m-d H:i:s', (int) filemtime($file)),
                'lines' => array_map(
                    static fn(string $l): string => mb_substr($l, 0, 400),
                    array_slice($hits, -40)
                ),
            ];
        }
        $out['logs'] = $logs;
        $failLog = $root . '/storage/logs/order_fail.log';
        $out['order_fail'] = is_file($failLog)
            ? array_slice(array_filter(explode("\n", (string) file_get_contents($failLog))), -60)
            : [];
    }

    if (!empty($_GET['write'])) {
        $shopId = (int) ($_GET['shop'] ?? 0);
        if ($shopId <= 0 && !empty($_GET['slug'])) {
            $s = $pdo->prepare('SELECT id FROM shops WHERE slug = ? LIMIT 1');
            $s->execute([(string) $_GET['slug']]);
            $shopId = (int) $s->fetchColumn();
        }
        $out['write_shop_id'] = $shopId;
        $t = $pdo->prepare('SELECT id, shop_id FROM tables WHERE shop_id = ? AND status = \'aktif\' LIMIT 1');
        $t->execute([$shopId]);
        $tbl = $t->fetch();
        $m = $pdo->prepare('SELECT id, nama_my, nama_en, harga, kategori FROM menu_items WHERE shop_id = ? AND is_active = 1 LIMIT 1');
        $m->execute([$shopId]);
        $menu = $m->fetch();
        if (!$tbl || !$menu) {
            $out['write_test'] = 'no table/menu for shop';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    'INSERT INTO orders (shop_id, table_id, waktu_order, status_order, status_bayar, jenis_hidang, subtotal, sst_rate, sst_jumlah, total_harga)
                     VALUES (?, ?, ?, \'menunggu\', \'belum_bayar\', \'dine_in\', 0, 0, 0, 0)'
                )->execute([$shopId, (int) $tbl['id'], appNow()]);
                $oid = (int) $pdo->lastInsertId();
                $pdo->prepare(
                    'INSERT INTO order_items (order_id, menu_item_id, qty, status_item, harga_saat_order, nama_saat_order_my, nama_saat_order_en, kategori_saat_order)
                     VALUES (?, ?, 1, \'menunggu\', ?, ?, ?, ?)'
                )->execute([$oid, (int) $menu['id'], $menu['harga'], $menu['nama_my'], $menu['nama_en'], $menu['kategori']]);
                $out['write_test'] = 'ok (rolled back)';
            } catch (Throwable $we) {
                $out['write_test'] = 'FAILED: ' . $we->getMessage();
            }
            $pdo->rollBack();
        }
    }
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
