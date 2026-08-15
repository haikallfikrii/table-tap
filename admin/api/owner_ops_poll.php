<?php
/**
 * Owner live floor snapshot — kitchen, drinks, waiter/pickup, unpaid.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

requireLoginApi(['owner']);
$shopId = requireShopIdApi();
$shop = findShopById($shopId);

jsonResponse([
    'ok' => true,
    'ops' => ownerOpsSnapshot($shopId, $shop),
]);
