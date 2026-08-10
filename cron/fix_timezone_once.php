<?php
/**
 * One-time: convert order timestamps stored as UTC wall-clock into Asia/Kuala_Lumpur.
 *
 * Hostinger MySQL often runs UTC while PHP uses Asia/Kuala_Lumpur, so orders paid
 * after midnight MYT landed on "yesterday" and missed Income (Today).
 *
 * Run once after deploy:
 *   https://tabletap.jomsite.com/cron/fix_timezone_once.php?key=YOUR_CRON_SECRET
 *
 * Safe to re-hit: it no-ops after the marker file exists.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/db.php';

$config = getConfig();
$key = (string) ($_GET['key'] ?? '');
if ($key === '' || !hash_equals((string) ($config['cron_secret'] ?? ''), $key)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Forbidden');
}

$marker = dirname(__DIR__) . '/storage/timezone_myt_fixed.flag';
$storageDir = dirname($marker);
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

header('Content-Type: application/json; charset=utf-8');

if (is_file($marker)) {
    echo json_encode([
        'ok' => true,
        'already_done' => true,
        'message' => 'Timezone migration already applied.',
    ]);
    exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
    // Shift naive UTC datetimes to MYT (+08:00). Only touch rows that still look "behind"
    // relative to local midnight today — actually shift ALL historical rows once.
    $orders = $pdo->exec(
        'UPDATE orders
         SET waktu_order = DATE_ADD(waktu_order, INTERVAL 8 HOUR),
             waktu_lunas = IF(waktu_lunas IS NULL, NULL, DATE_ADD(waktu_lunas, INTERVAL 8 HOUR))'
    );

    file_put_contents(
        $marker,
        'fixed_at=' . appNow() . "\nrows_touched=" . (string) $orders . "\n"
    );
    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'already_done' => false,
        'orders_updated' => $orders,
        'message' => 'Order timestamps shifted +8 hours to Asia/Kuala_Lumpur.',
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
