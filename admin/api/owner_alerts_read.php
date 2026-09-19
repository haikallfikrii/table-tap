<?php
/**
 * Owner — mark dashboard alerts as read.
 * POST JSON: { ids?: number[] }  empty / omit = all unread
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/shop.php';

requirePost();
requireLoginApi(['owner']);
$shopId = requireShopIdApi();
$body = readJsonBody();
$ids = [];
if (isset($body['ids']) && is_array($body['ids'])) {
    foreach ($body['ids'] as $id) {
        $ids[] = (int) $id;
    }
}

jsonResponse([
    'ok' => true,
    'marked' => markShopAlertsRead($shopId, $ids),
    'unread' => countUnreadShopAlerts($shopId),
]);
