<?php
/**
 * Kitchen dashboard — default dapur station, or the staff member's assigned station.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/i18n.php';
require_once dirname(__DIR__) . '/includes/stations.php';

requireLogin(['dapur', 'owner']);

$user = currentUser();
$lang = currentLang();
$config = getConfig();
$shop = findShopById(requireShopId());
$selfPickup = shopFulfillment($shop) === 'self_pickup';
$requested = (int) ($_GET['station'] ?? 0);
$station = resolveKitchenStation($user, (int) $shop['id'], $requested > 0 ? $requested : null, 'dapur');
if (!$station || !userCanAccessStation($user, $station)) {
    http_response_code(403);
    exit('Akses ditolak / Access denied.');
}

require __DIR__ . '/_station_screen.php';
