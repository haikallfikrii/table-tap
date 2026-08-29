<?php
/**
 * Bulk route a menu category to a work station (e.g. Western → Minuman/Air).
 *
 * Run once after deploy:
 *   curl -s "https://tabletap.my/cron/set_category_station.php?key=CRON_SECRET&shop=ownerportsantai&category=western&station=minuman"
 *
 * Params:
 *   shop     — owner username, shop slug, or numeric shop id
 *   category — menu category kod or name fragment (e.g. western)
 *   station  — station kod or name fragment (minuman, air, …)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/menu_station_bulk.php';

$config = getConfig();
$key = (string) ($_GET['key'] ?? '');
if ($key === '' || !hash_equals((string) ($config['cron_secret'] ?? ''), $key)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Forbidden');
}

header('Content-Type: application/json; charset=utf-8');

$shopRef = trim((string) ($_GET['shop'] ?? ''));
$categoryRef = trim((string) ($_GET['category'] ?? 'western'));
$stationRef = trim((string) ($_GET['station'] ?? 'minuman'));

if ($shopRef === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing shop parameter']);
    exit;
}

$shopId = resolveShopIdForBulk($shopRef);
if ($shopId === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Shop not found: ' . $shopRef]);
    exit;
}

$result = bulkSetMenuCategoryStation($shopId, $categoryRef, $stationRef);
if (!$result['ok']) {
    http_response_code($result['error'] && str_contains((string) $result['error'], 'not found') ? 404 : 400);
}
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
