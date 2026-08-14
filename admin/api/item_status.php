<?php
/**
 * Update order item status (kitchen / drinks / waiter)
 * POST JSON: { item_id, status }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

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
$stmt = $pdo->prepare(
    "SELECT oi.id, oi.kategori_saat_order, oi.status_item, oi.order_id, o.shop_id
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

if (in_array($status, $kitchenStatuses, true)) {
    $kat = $item['kategori_saat_order'];
    if ($kat === 'makanan') {
        requireLoginApi(['dapur', 'owner']);
    } else {
        requireLoginApi(['minuman', 'owner']);
    }
} else {
    requireLoginApi(['waiter', 'owner']);
}

$shopId = requireShopIdApi();
if ((int) $item['shop_id'] !== $shopId) {
    jsonError('Forbidden', 403);
}

$upd = $pdo->prepare('UPDATE order_items SET status_item = ? WHERE id = ?');
$upd->execute([$status, $itemId]);

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
