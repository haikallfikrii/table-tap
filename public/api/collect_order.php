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
$stmt = $pdo->prepare(
    "SELECT id FROM orders
     WHERE id = ? AND table_id = ? AND shop_id = ? AND status_order != 'dibatalkan'
     LIMIT 1"
);
$stmt->execute([$orderId, (int) $table['id'], (int) $table['shop_id']]);
if (!$stmt->fetch()) {
    jsonError('Order not found', 404);
}

collectSelfPickupReadyItems($pdo, $orderId, (int) $table['shop_id']);

jsonResponse(['ok' => true, 'order_id' => $orderId]);
