<?php
/**
 * Shop (tenant) helpers — branding, SST, retention.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : ('shop-' . bin2hex(random_bytes(3)));
}

function findShopById(int $shopId): ?array
{
    $stmt = db()->prepare(
        'SELECT s.*, p.kod AS package_kod, p.nama_my AS package_nama_my, p.nama_en AS package_nama_en,
                p.retention_days
         FROM shops s
         INNER JOIN packages p ON p.id = s.package_id
         WHERE s.id = ?
         LIMIT 1'
    );
    $stmt->execute([$shopId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getShopOrFail(int $shopId): array
{
    $shop = findShopById($shopId);
    if (!$shop || $shop['status'] !== 'aktif') {
        http_response_code(403);
        exit('Kedai tidak aktif / Shop inactive.');
    }
    return $shop;
}

/** Brand name for UI: shop name, fallback to app name */
function shopBrand(?array $shop = null): string
{
    if ($shop && !empty($shop['nama_kedai'])) {
        return (string) $shop['nama_kedai'];
    }
    $c = getConfig();
    return (string) ($c['app_name'] ?? 'TableTap');
}

/**
 * Calculate SST from subtotal using shop settings.
 * @return array{subtotal:float,sst_rate:float,sst_jumlah:float,total:float}
 */
function calculateTotals(float $subtotal, array $shop): array
{
    $subtotal = round($subtotal, 2);
    $enabled = (int) ($shop['sst_enabled'] ?? 0) === 1;
    $rate = $enabled ? (float) ($shop['sst_rate'] ?? 0) : 0.0;
    if ($rate < 0) {
        $rate = 0.0;
    }
    $sst = $enabled ? round($subtotal * ($rate / 100), 2) : 0.0;
    return [
        'subtotal'   => $subtotal,
        'sst_rate'   => $rate,
        'sst_jumlah' => $sst,
        'total'      => round($subtotal + $sst, 2),
    ];
}

/** @return array{mode:string,count:int,duration_sec:int,interval_ms:int,volume:int} */
function shopSoundSettings(?array $shop): array
{
    $mode = (string) ($shop['sound_mode'] ?? 'until_cleared');
    if (!in_array($mode, ['until_cleared', 'count', 'duration'], true)) {
        $mode = 'until_cleared';
    }
    return [
        'mode' => $mode,
        'count' => max(1, min(50, (int) ($shop['sound_repeat_count'] ?? 8))),
        'duration_sec' => max(3, min(300, (int) ($shop['sound_duration_sec'] ?? 45))),
        'interval_ms' => max(400, min(5000, (int) ($shop['sound_interval_ms'] ?? 900))),
        'volume' => max(20, min(100, (int) ($shop['sound_volume'] ?? 100))),
    ];
}

/**
 * Delete paid/cancelled orders older than package retention.
 * Skips shops with retention_days = NULL (forever).
 * Returns number of orders deleted.
 */
function purgeExpiredOrderHistory(?int $shopId = null): int
{
    $pdo = db();
    $sql = 'SELECT s.id AS shop_id, p.retention_days
            FROM shops s
            INNER JOIN packages p ON p.id = s.package_id
            WHERE p.retention_days IS NOT NULL';
    $params = [];
    if ($shopId !== null) {
        $sql .= ' AND s.id = ?';
        $params[] = $shopId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $shops = $stmt->fetchAll();

    $deleted = 0;
    $del = $pdo->prepare(
        "DELETE FROM orders
         WHERE shop_id = ?
           AND status_bayar = 'lunas'
           AND status_order IN ('selesai', 'dibatalkan')
           AND waktu_order < DATE_SUB(NOW(), INTERVAL ? DAY)"
    );

    foreach ($shops as $s) {
        $days = (int) $s['retention_days'];
        if ($days <= 0) {
            continue;
        }
        $del->execute([(int) $s['shop_id'], $days]);
        $deleted += $del->rowCount();
    }

    return $deleted;
}

function shopPackageKod(?array $shop): string
{
    $kod = strtolower((string) ($shop['package_kod'] ?? ''));
    return in_array($kod, ['basic', 'standard', 'pro'], true) ? $kod : 'basic';
}

function shopPackageRank(?array $shop): int
{
    return ['basic' => 1, 'standard' => 2, 'pro' => 3][shopPackageKod($shop)] ?? 1;
}

function shopHasFeature(?array $shop, string $feature): bool
{
    $need = [
        'self_pickup'     => 2, // Standard+
        'menu_gallery'    => 3, // Pro
        'custom_stations' => 3, // Pro extra stations (Western, pastry, …)
        'custom_menu_categories' => 3, // Pro custom menu tabs for customers
    ];
    $required = $need[$feature] ?? 99;
    return shopPackageRank($shop) >= $required;
}

function shopFulfillment(?array $shop): string
{
    if (($shop['fulfillment_mode'] ?? '') !== 'self_pickup') {
        return 'waiter';
    }
    return shopHasFeature($shop, 'self_pickup') ? 'self_pickup' : 'waiter';
}

function normalizeCustomerName(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($name, 0, 40);
    }
    return substr($name, 0, 40);
}

function isValidCustomerName(string $name): bool
{
    $len = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    if ($len < 2 || $len > 40) {
        return false;
    }
    return (bool) preg_match('/^[\p{L}\p{M}\d .\'-]+$/u', $name);
}

function mutePickupAlert(PDO $pdo, int $orderId, int $shopId): void
{
    try {
        $pdo->prepare('UPDATE orders SET pickup_alert = 0 WHERE id = ? AND shop_id = ?')->execute([$orderId, $shopId]);
    } catch (Throwable $e) {
        // Column may be missing until schema patch runs.
    }
}

function collectSelfPickupReadyItems(PDO $pdo, int $orderId, int $shopId): void
{
    $pdo->prepare(
        "UPDATE order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         SET oi.status_item = 'dihantar'
         WHERE o.id = ? AND o.shop_id = ? AND oi.status_item IN ('siap','diambil')"
    )->execute([$orderId, $shopId]);
    mutePickupAlert($pdo, $orderId, $shopId);
    $check = $pdo->prepare(
        "SELECT COUNT(*) FROM order_items WHERE order_id = ? AND status_item != 'dihantar'"
    );
    $check->execute([$orderId]);
    if ((int) $check->fetchColumn() === 0) {
        $pdo->prepare(
            "UPDATE orders SET status_order = 'selesai' WHERE id = ? AND shop_id = ?"
        )->execute([$orderId, $shopId]);
    }
}

/** Live floor counts for the owner dashboard. */
function ownerOpsSnapshot(int $shopId, ?array $shop = null): array
{
    require_once __DIR__ . '/stations.php';

    $shop = $shop ?? findShopById($shopId);
    $pickup = shopFulfillment($shop) === 'self_pickup';
    $lang = function_exists('currentLang') ? currentLang() : 'my';

    $handover = emptyStationCounts();
    $handOrders = [];
    $stationsMeta = shopStations($shopId, true);
    $byId = [];
    $orderSets = [];
    foreach ($stationsMeta as $st) {
        $sid = (int) $st['id'];
        $byId[$sid] = array_merge(emptyStationCounts(), [
            'id' => $sid,
            'kod' => (string) $st['kod'],
            'name' => stationLabel($st, $lang),
            'url' => stationScreenUrl($st),
        ]);
        $orderSets[$sid] = [];
    }
    $legacyKitchen = emptyStationCounts() + ['id' => 0, 'kod' => 'dapur', 'name' => $lang === 'en' ? 'Kitchen' : 'Dapur', 'url' => baseUrl('admin/dapur.php')];
    $legacyDrinks = emptyStationCounts() + ['id' => 0, 'kod' => 'minuman', 'name' => $lang === 'en' ? 'Drinks' : 'Minuman', 'url' => baseUrl('admin/minuman.php')];

    $hasStationCol = orderStationColumnExists();
    $sql = $hasStationCol
        ? "SELECT oi.order_id, oi.kategori_saat_order AS kat, oi.station_id_saat_order AS sid, oi.status_item AS st, COUNT(*) AS n
           FROM order_items oi
           INNER JOIN orders o ON o.id = oi.order_id
           WHERE o.shop_id = ?
             AND o.status_order != 'dibatalkan'
             AND oi.status_item != 'dihantar'
           GROUP BY oi.order_id, oi.kategori_saat_order, oi.station_id_saat_order, oi.status_item"
        : "SELECT oi.order_id, oi.kategori_saat_order AS kat, NULL AS sid, oi.status_item AS st, COUNT(*) AS n
           FROM order_items oi
           INNER JOIN orders o ON o.id = oi.order_id
           WHERE o.shop_id = ?
             AND o.status_order != 'dibatalkan'
             AND oi.status_item != 'dihantar'
           GROUP BY oi.order_id, oi.kategori_saat_order, oi.status_item";
    $stmt = db()->prepare($sql);
    $stmt->execute([$shopId]);

    $dapurId = 0;
    $minumanId = 0;
    foreach ($stationsMeta as $st) {
        if ($st['kod'] === 'dapur') {
            $dapurId = (int) $st['id'];
        }
        if ($st['kod'] === 'minuman') {
            $minumanId = (int) $st['id'];
        }
    }

    foreach ($stmt->fetchAll() as $row) {
        $n = (int) $row['n'];
        $st = (string) $row['st'];
        $oid = (int) $row['order_id'];
        $sid = (int) ($row['sid'] ?? 0);
        if ($sid <= 0 || !isset($byId[$sid])) {
            $sid = ((string) $row['kat'] === 'minuman') ? $minumanId : $dapurId;
        }
        $inKitchen = $st === 'menunggu' || $st === 'sedang_dimasak';
        $inHandover = $pickup ? $st === 'siap' : ($st === 'siap' || $st === 'diambil');

        if ($inKitchen && $sid > 0 && isset($byId[$sid])) {
            $byId[$sid]['items'] += $n;
            $byId[$sid][$st] = ($byId[$sid][$st] ?? 0) + $n;
            $orderSets[$sid][$oid] = true;
        } elseif ($inKitchen && $stationsMeta === []) {
            if ((string) $row['kat'] === 'minuman') {
                $legacyDrinks['items'] += $n;
                $legacyDrinks[$st] = ($legacyDrinks[$st] ?? 0) + $n;
            } else {
                $legacyKitchen['items'] += $n;
                $legacyKitchen[$st] = ($legacyKitchen[$st] ?? 0) + $n;
            }
        }
        if ($inHandover) {
            $handover['items'] += $n;
            $handover[$st] = ($handover[$st] ?? 0) + $n;
            $handOrders[$oid] = true;
        }
    }

    $stationsOut = [];
    foreach ($byId as $sid => $bucket) {
        $bucket['orders'] = count($orderSets[$sid] ?? []);
        $stationsOut[] = $bucket;
    }
    if ($stationsOut === []) {
        $stationsOut = [$legacyKitchen, $legacyDrinks];
    }

    $kitchen = emptyStationCounts();
    $drinks = emptyStationCounts();
    foreach ($stationsOut as $bucket) {
        if ($bucket['kod'] === 'minuman') {
            $drinks = $bucket;
        } elseif ($bucket['kod'] === 'dapur') {
            $kitchen = $bucket;
        }
    }
    $handover['orders'] = count($handOrders);

    $pay = db()->prepare(
        "SELECT COUNT(*) AS n, COALESCE(SUM(total_harga), 0) AS amt
         FROM orders
         WHERE shop_id = ? AND status_bayar = 'belum_bayar' AND status_order != 'dibatalkan'"
    );
    $pay->execute([$shopId]);
    $payRow = $pay->fetch() ?: ['n' => 0, 'amt' => 0];

    return [
        'fulfillment' => $pickup ? 'self_pickup' : 'waiter',
        'stations' => $stationsOut,
        'kitchen' => $kitchen,
        'drinks' => $drinks,
        'handover' => $handover,
        'unpaid' => [
            'orders' => (int) $payRow['n'],
            'amount' => (float) $payRow['amt'],
            'amount_fmt' => formatMoney((float) $payRow['amt']),
        ],
    ];
}
