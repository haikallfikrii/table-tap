<?php
/**
 * Re-queue a paid receipt to the Wi-Fi Print Bridge (kasir "Print receipt" button).
 * POST JSON: { order_id }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/print_bridge.php';

requireLoginApi(['kasir', 'owner']);
$shopId = requireShopIdApi();
requirePost();

$body = readJsonBody();
$orderId = (int) ($body['order_id'] ?? 0);
if ($orderId <= 0) {
    jsonError('Invalid order_id');
}

$shop = findShopById($shopId);
if (!shopPrintBridgeEnabled($shop)) {
    jsonError(t('print_bridge_disabled'), 400);
}

$jobId = enqueueReceiptPrintJob($shopId, $orderId, $shop, currentLang(), true);
if ($jobId === null) {
    jsonError(t('print_bridge_queue_failed'), 500);
}

jsonResponse(['ok' => true, 'job_id' => $jobId, 'order_id' => $orderId]);
