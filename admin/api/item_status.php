<?php
/**
 * Update order item status (kitchen / drinks / waiter)
 * POST JSON: { item_id, status }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/stations.php';

requirePost();
$body = readJsonBody();

$itemId = (int) ($body['item_id'] ?? 0);
$status = (string) ($body['status'] ?? '');
if ($status === 'selesai') {
    $status = 'siap';
}

$kitchenStatuses = ['menunggu', 'sedang_dimasak', 'siap'];
$waiterStatuses = ['diambil', 'dihantar'];
$allowed = array_merge($kitchenStatuses, $waiterStatuses);

if ($itemId <= 0 || !in_array($status, $allowed, true)) {
    jsonError('Invalid payload');
}

$pdo = db();
$stationCol = orderStationColumnExists() ? ', oi.station_id_saat_order' : '';
$stmt = $pdo->prepare(
    "SELECT oi.id, oi.kategori_saat_order, oi.status_item, oi.order_id, o.shop_id{$stationCol}
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE oi.id = ? AND o.status_order != 'dibatalkan'
     LIMIT 1"
);
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    jsonError('Item not found', 404);
}

$shop = findShopById((int) $item['shop_id']);
$selfPickup = shopFulfillment($shop) === 'self_pickup';

if (in_array($status, $kitchenStatuses, true)) {
    $user = requireLoginApi(['dapur', 'minuman', 'owner']);
    $shopIdCheck = (int) $item['shop_id'];
    $sid = (int) ($item['station_id_saat_order'] ?? 0);
    $station = $sid > 0 ? findShopStation($shopIdCheck, $sid) : null;
    if (!$station) {
        $station = defaultStationForKategori($shopIdCheck, (string) $item['kategori_saat_order']);
    }
    if (!userCanAccessStation($user, $station)) {
        jsonError('Forbidden', 403);
    }
} elseif ($status === 'dihantar' && $selfPickup) {
    requireLoginApi(['dapur', 'minuman', 'kasir', 'owner']);
} else {
    requireLoginApi(['waiter', 'owner']);
}

$shopId = requireShopIdApi();
if ((int) $item['shop_id'] !== $shopId) {
    jsonError('Forbidden', 403);
}

$upd = $pdo->prepare('UPDATE order_items SET status_item = ? WHERE id = ?');
$upd->execute([$status, $itemId]);

if ($status === 'dihantar' && $selfPickup) {
    mutePickupAlert($pdo, (int) $item['order_id'], (int) $item['shop_id']);
}

if (in_array($status, ['sedang_dimasak', 'siap', 'diambil'], true)) {
    $pdo->prepare(
        "UPDATE orders SET status_order = 'diproses'
         WHERE id = ? AND status_order = 'menunggu'"
    )->execute([(int) $item['order_id']]);
}

$check = $pdo->prepare(
    "SELECT COUNT(*) AS pending
     FROM order_items
     WHERE order_id = ? AND status_item != 'dihantar'"
);
$check->execute([(int) $item['order_id']]);
$row = $check->fetch();
if ((int) ($row['pending'] ?? 1) === 0) {
    $pdo->prepare(
        "UPDATE orders SET status_order = 'selesai' WHERE id = ?"
    )->execute([(int) $item['order_id']]);
}

jsonResponse(['ok' => true, 'item_id' => $itemId, 'status' => $status]);
