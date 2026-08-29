<?php
/**
 * Cancel / void an order — excluded from income & kitchen queues.
 * POST JSON: { order_id }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/shop.php';

requirePost();
requireLoginApi(['kasir', 'owner']);

$shopId = requireShopIdApi();
$orderId = (int) (readJsonBody()['order_id'] ?? 0);
if ($orderId <= 0) {
    jsonError('Invalid order');
}

if (!cancelShopOrder($orderId, $shopId)) {
    jsonError(t('order_not_found'), 404);
}

jsonResponse([
    'ok' => true,
    'order_id' => $orderId,
    'status_order' => 'dibatalkan',
]);
