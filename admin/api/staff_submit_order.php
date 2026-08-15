<?php
/**
 * Staff places an order for a table (guest without a phone).
 * POST JSON: { table_id, from, jenis_hidang, nama_pelanggan, items }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

requirePost();
requireLoginApi(['waiter', 'kasir', 'owner']);
$shopId = requireShopIdApi();

$body = readJsonBody();
$tableId = (int) ($body['table_id'] ?? 0);
$from = (string) ($body['from'] ?? 'waiter');
if (!in_array($from, ['waiter', 'kasir', 'owner'], true)) {
    $from = 'waiter';
}
$jenisHidang = (($body['jenis_hidang'] ?? '') === 'takeaway') ? 'takeaway' : 'dine_in';
$items = $body['items'] ?? [];

$table = findTableByIdForShop($tableId, $shopId);
if (!$table) {
    jsonError('Invalid table', 403);
}

$shop = findShopById($shopId);
if (!$shop || $shop['status'] !== 'aktif') {
    jsonError('Shop inactive', 403);
}

$created = createShopOrder(
    $table,
    $shop,
    is_array($items) ? $items : [],
    $jenisHidang,
    (string) ($body['nama_pelanggan'] ?? ''),
    'staf',
    true
);
$orderId = $created['order_id'];
$totals = $created['totals'];
$redirect = staffStationHome($from) . '?ordered=' . $orderId . '&meja=' . urlencode((string) $table['nomor_meja']);

jsonResponse([
    'ok' => true,
    'order_id' => $orderId,
    'subtotal' => $totals['subtotal'],
    'sst' => $totals['sst_jumlah'],
    'total' => $totals['total'],
    'redirect' => $redirect,
]);
