<?php
/**
 * Customer active orders for a table (multi-order tracking).
 * GET: meja, token, lang, focus (optional order id)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';

$nomorMeja = trim((string) ($_GET['meja'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'my';
$focusOrderId = (int) ($_GET['focus'] ?? 0);

if ($nomorMeja === '' || $token === '') {
    jsonError('Invalid request', 400);
}

$table = findTableByAccess($nomorMeja, $token);
if (!$table) {
    jsonError('Invalid table access', 403);
}

$shop = findShopById((int) $table['shop_id']);
if (!$shop || ($shop['status'] ?? '') !== 'aktif') {
    jsonError('Tracking unavailable', 403);
}

$orders = fetchActiveCustomerOrders($table, $lang);
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
]);
