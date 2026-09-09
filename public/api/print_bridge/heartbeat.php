<?php
/**
 * Print Bridge — token check + queue status.
 *
 * GET/POST with header: X-Print-Bridge-Token: <shop token>
 * Used by the APK setup screen to confirm the token before staff walk away.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/helpers.php';
require_once dirname(__DIR__, 3) . '/includes/print_bridge.php';
require_once dirname(__DIR__, 3) . '/includes/stations.php';

$shop = requirePrintBridgeShop();
$shopId = (int) $shop['id'];

touchPrintBridgeSeen($shopId);

$stations = [['kod' => 'kasir', 'name' => 'Kasir']];
foreach (shopStations($shopId, true) as $st) {
    $stations[] = [
        'kod' => (string) $st['kod'],
        'name' => stationLabel($st, 'my'),
    ];
}

jsonResponse([
    'ok' => true,
    'shop_id' => $shopId,
    'shop_name' => (string) ($shop['nama_kedai'] ?? ''),
    'shop_slug' => (string) ($shop['slug'] ?? ''),
    'server_time' => appNow(),
    'stations' => $stations,
    'queue' => printBridgeQueueStats($shopId),
]);
