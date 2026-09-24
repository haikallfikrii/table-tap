<?php
/**
 * JSON payload for Bluetooth daily closing slip.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/shift.php';

requireLoginApi(['kasir', 'owner']);
$shopId = requireShopIdApi();
$shop = getShopOrFail($shopId);

$todayBiz = shopBusinessDate($shop);
$date = (string) ($_GET['date'] ?? $todayBiz);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $todayBiz;
}

$report = dailyClosingReport($shopId, $shop, $date);
$lines = [];
foreach ($report['orders'] as $r) {
    $lines[] = [
        'id' => (int) $r['id'],
        'meja' => (string) $r['nomor_meja'],
        'total' => (float) $r['total_harga'],
        'pay' => (string) (($r['payment_method'] ?? '') !== '' ? $r['payment_method'] : 'counter'),
        'serve' => (string) ($r['jenis_hidang'] ?? 'dine_in'),
        'at' => (string) ($r['waktu_lunas'] ?: $r['waktu_order']),
    ];
}

jsonResponse([
    'ok' => true,
    'shop_name' => (string) ($shop['nama_kedai'] ?? 'TableTap'),
    'date' => $report['date'],
    'start' => $report['start'],
    'end' => $report['end'],
    'order_count' => $report['order_count'],
    'subtotal' => $report['subtotal'],
    'sst' => $report['sst'],
    'total' => $report['total'],
    'unpaid_count' => $report['unpaid_count'],
    'unpaid_total' => $report['unpaid_total'],
    'by_pay' => $report['by_pay'],
    'by_serve' => $report['by_serve'],
    'orders' => $lines,
    'printed_at' => appNow(),
]);
