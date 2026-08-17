<?php
/**
 * Customer confirms they collected a self-pickup order.
 * POST JSON: { order, meja, token }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';

requirePost();
$body = readJsonBody();

$orderId = (int) ($body['order'] ?? 0);
$nomorMeja = trim((string) ($body['meja'] ?? ''));
$token = trim((string) ($body['token'] ?? ''));
$guestToken = trim((string) ($body['guest_token'] ?? ''));

if ($orderId <= 0 || $nomorMeja === '' || $token === '') {
    jsonError('Invalid request', 400);
}

$table = findTableByAccess($nomorMeja, $token);
if (!$table) {
    jsonError('Invalid table access', 403);
}

$shop = findShopById((int) $table['shop_id']);
if (!$shop || shopFulfillment($shop) !== 'self_pickup') {
    jsonError('Not available', 403);
}

$pdo = db();
$hasGuestToken = orderGuestTokenColumnExists();
$guestCol = $hasGuestToken ? ', guest_token' : '';
$stmt = $pdo->prepare(
    "SELECT id{$guestCol} FROM orders
     WHERE id = ? AND table_id = ? AND shop_id = ? AND status_order != 'dibatalkan'
     LIMIT 1"
);
$stmt->execute([$orderId, (int) $table['id'], (int) $table['shop_id']]);
$orderRow = $stmt->fetch();
if (!$orderRow) {
    jsonError('Order not found', 404);
}
if (!verifyOrderGuestToken($orderRow, $guestToken)) {
    jsonError('Invalid order access', 403);
}

collectSelfPickupReadyItems($pdo, $orderId, (int) $table['shop_id']);

jsonResponse(['ok' => true, 'order_id' => $orderId]);
