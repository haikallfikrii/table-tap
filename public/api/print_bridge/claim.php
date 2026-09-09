<?php
/**
 * Print Bridge — claim pending jobs for one shop.
 *
 * GET/POST with header: X-Print-Bridge-Token: <shop token>
 * Query: ?stations=kasir,dapur,minuman&limit=5
 *
 * Claimed jobs flip to `printing` atomically, so two bridge devices on the
 * same Wi-Fi never print the same ticket twice.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/helpers.php';
require_once dirname(__DIR__, 3) . '/includes/print_bridge.php';

$shop = requirePrintBridgeShop();
$shopId = (int) $shop['id'];

$stationsParam = (string) ($_GET['stations'] ?? '');
$stations = $stationsParam !== '' ? array_map('trim', explode(',', $stationsParam)) : [];
$limit = (int) ($_GET['limit'] ?? 5);

touchPrintBridgeSeen($shopId);
$jobs = claimPrintJobs($shopId, $stations, $limit);

if ($jobs === []) {
    purgeOldPrintJobs($shopId);
}

jsonResponse([
    'ok' => true,
    'shop_id' => $shopId,
    'shop_name' => (string) ($shop['nama_kedai'] ?? ''),
    'server_time' => appNow(),
    'jobs' => $jobs,
]);
