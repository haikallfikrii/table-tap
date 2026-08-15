<?php
/**
 * Cashier/kitchen: mute customer pickup alert or mark ready items collected.
 * POST JSON: { order_id, action: mute|collect }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

requirePost();
$body = readJsonBody();

$orderId = (int) ($body['order_id'] ?? 0);
$action = (string) ($body['action'] ?? '');
if ($orderId <= 0 || !in_array($action, ['mute', 'collect'], true)) {
    jsonError('Invalid payload');
}

requireLoginApi(['kasir', 'dapur', 'minuman', 'owner']);
$shopId = requireShopIdApi();
$shop = findShopById($shopId);
if (shopFulfillment($shop) !== 'self_pickup') {
    jsonError('Not a self-pickup shop', 403);
}

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT id FROM orders WHERE id = ? AND shop_id = ? AND status_order != 'dibatalkan' LIMIT 1"
);
$stmt->execute([$orderId, $shopId]);
if (!$stmt->fetch()) {
    jsonError('Order not found', 404);
}

if ($action === 'mute') {
    mutePickupAlert($pdo, $orderId, $shopId);
} else {
    collectSelfPickupReadyItems($pdo, $orderId, $shopId);
}

jsonResponse(['ok' => true, 'order_id' => $orderId, 'action' => $action]);
