<?php
/**
 * Customer order status (self-pickup live track).
 * GET: order, meja, token
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';

$orderId = (int) ($_GET['order'] ?? 0);
$nomorMeja = trim((string) ($_GET['meja'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'my';

if ($orderId <= 0 || $nomorMeja === '' || $token === '') {
    jsonError('Invalid request', 400);
}

$table = findTableByAccess($nomorMeja, $token);
if (!$table) {
    jsonError('Invalid table access', 403);
}

$shop = findShopById((int) $table['shop_id']);
if (!$shop || shopFulfillment($shop) !== 'self_pickup') {
    jsonError('Tracking unavailable', 403);
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT id, status_order, nama_pelanggan, pickup_alert
     FROM orders
     WHERE id = ? AND table_id = ? AND shop_id = ?
     LIMIT 1'
);
$stmt->execute([$orderId, (int) $table['id'], (int) $table['shop_id']]);
$order = $stmt->fetch();
if (!$order) {
    jsonError('Order not found', 404);
}

$itemStmt = $pdo->prepare(
    "SELECT id, qty, status_item, nama_saat_order_my, nama_saat_order_en, kategori_saat_order
     FROM order_items
     WHERE order_id = ?
     ORDER BY id ASC"
);
$itemStmt->execute([$orderId]);
$items = [];
$readyIds = [];
foreach ($itemStmt->fetchAll() as $it) {
    $st = (string) $it['status_item'];
    $id = (int) $it['id'];
    if (in_array($st, ['siap', 'diambil'], true)) {
        $readyIds[] = $id;
    }
    $items[] = [
        'id' => $id,
        'qty' => (int) $it['qty'],
        'status_item' => $st,
        'nama' => $lang === 'en' ? $it['nama_saat_order_en'] : $it['nama_saat_order_my'],
        'kategori' => $it['kategori_saat_order'],
    ];
}

jsonResponse([
    'ok' => true,
    'order_id' => $orderId,
    'status_order' => $order['status_order'],
    'nama_pelanggan' => $order['nama_pelanggan'] ?? '',
    'stage' => trackStageFromItems($items),
    'pickup_alert' => (int) ($order['pickup_alert'] ?? 1) === 1,
    'items' => $items,
    'ready_item_ids' => $readyIds,
    'sound' => shopSoundSettings($shop),
]);
