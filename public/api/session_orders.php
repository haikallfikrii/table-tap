<?php
/**
 * Customer active orders for a cafe session (private — not shared with other customers).
 * GET: s=session_token, lang, focus (optional order id)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';

$sessionToken = trim((string) ($_GET['s'] ?? ''));
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'my';
$focusOrderId = (int) ($_GET['focus'] ?? 0);

if ($sessionToken === '') {
    jsonError('Invalid request', 400);
}

$session = findSessionByToken($sessionToken);
if (!$session || ($session['status'] ?? '') !== 'active') {
    jsonError('Session expired', 403);
}

$shop = findShopById((int) $session['shop_id']);
if (!$shop || ($shop['status'] ?? '') !== 'aktif') {
    jsonError('Tracking unavailable', 403);
}

$orders = fetchActiveSessionOrders($session, $lang);
if ($orders === []) {
    jsonError('No active orders', 404);
}

if ($focusOrderId <= 0) {
    $focusOrderId = (int) ($orders[0]['order_id'] ?? 0);
}

$focusStage = 'queue';
foreach ($orders as $order) {
    if ((int) ($order['order_id'] ?? 0) === $focusOrderId) {
        $focusStage = (string) ($order['stage'] ?? 'queue');
        break;
    }
}

jsonResponse([
    'ok' => true,
    'focus_order_id' => $focusOrderId,
    'focus_stage' => $focusStage,
    'fulfillment' => shopFulfillment($shop),
    'orders' => $orders,
    'sound' => shopSoundSettings($shop),
    'session_url' => cafeSessionOrderUrl($sessionToken),
]);
