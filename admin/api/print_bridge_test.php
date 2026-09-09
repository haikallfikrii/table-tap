<?php
/**
 * Queue a Print Bridge test ticket from the owner settings screen.
 * POST JSON: { station: "kasir"|"dapur"|"minuman"|<station kod> }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/print_bridge.php';

requireLoginApi(['kasir', 'owner']);
$shopId = requireShopIdApi();
requirePost();

$body = readJsonBody();
$station = strtolower(trim((string) ($body['station'] ?? 'kasir')));
if ($station === '' || !preg_match('/^[a-z0-9-]{1,40}$/', $station)) {
    jsonError('Invalid station');
}

$shop = findShopById($shopId);
if (!shopPrintBridgeEnabled($shop)) {
    jsonError(t('print_bridge_disabled'), 400);
}

$jobId = enqueueTestPrintJob($shopId, $station, $shop, currentLang());
if ($jobId === null) {
    jsonError(t('print_bridge_queue_failed'), 500);
}

jsonResponse([
    'ok' => true,
    'job_id' => $jobId,
    'station' => $station,
    'queue' => printBridgeQueueStats($shopId),
]);
