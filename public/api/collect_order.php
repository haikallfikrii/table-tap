<?php
/**
 * Customer confirms they collected a self-pickup order.
 * POST JSON table: { order, meja, token, guest_token }
 * POST JSON cafe:  { order, session, guest_token }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';

requirePost();
$body = readJsonBody();

$orderId = (int) ($body['order'] ?? 0);
$sessionToken = trim((string) ($body['session'] ?? ''));
$nomorMeja = trim((string) ($body['meja'] ?? ''));
$token = trim((string) ($body['token'] ?? ''));
$guestToken = trim((string) ($body['guest_token'] ?? ''));

if ($orderId <= 0) {
    jsonError('Invalid request', 400);
}

$table = null;
$shopId = 0;
$tableId = 0;
$sessionId = null;

if ($sessionToken !== '') {
    $session = findSessionByToken($sessionToken);
    if (!$session || ($session['status'] ?? '') !== 'active') {
        jsonError('Session expired', 403);
    }
    $table = sessionAsTableContext($session);
    $shopId = (int) $table['shop_id'];
    $tableId = (int) $table['id'];
    $sessionId = (int) $session['id'];
} elseif ($nomorMeja !== '' && $token !== '') {
    $table = findTableByAccess($nomorMeja, $token);
    if (!$table) {
        jsonError('Invalid table access', 403);
    }
    $shopId = (int) $table['shop_id'];
    $tableId = (int) $table['id'];
} else {
    jsonError('Invalid request', 400);
}

$shop = findShopById($shopId);
if (!$shop || shopFulfillment($shop) !== 'self_pickup') {
    jsonError('Not available', 403);
}

$pdo = db();
$hasGuestToken = orderGuestTokenColumnExists();
$hasSessionId = orderSessionColumnExists();
$guestCol = $hasGuestToken ? ', guest_token' : '';
$sessionClause = ($hasSessionId && $sessionId !== null) ? ' AND session_id = ?' : '';
$params = [$orderId, $tableId, $shopId];
if ($sessionClause !== '') {
    $params[] = $sessionId;
}

$stmt = $pdo->prepare(
    "SELECT id{$guestCol} FROM orders
     WHERE id = ? AND table_id = ? AND shop_id = ?{$sessionClause} AND status_order != 'dibatalkan'
     LIMIT 1"
);
$stmt->execute($params);
$orderRow = $stmt->fetch();
if (!$orderRow) {
    jsonError('Order not found', 404);
}
if (!verifyOrderGuestToken($orderRow, $guestToken)) {
    jsonError('Invalid order access', 403);
}

collectSelfPickupReadyItems($pdo, $orderId, $shopId);

jsonResponse(['ok' => true, 'order_id' => $orderId]);
