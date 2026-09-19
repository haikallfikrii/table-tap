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
 * Thermal printer prefs for kasir / kitchen screens.
 * @return array{kasir_print_on_paid:bool,kasir_print_hub:bool,kasir_open_drawer:bool,beep_kitchen:int,beep_kasir:int}
 */
function shopPrinterSettings(?array $shop): array
{
    return [
        'kasir_print_on_paid' => (int) ($shop['kasir_print_on_paid'] ?? 1) === 1,
        'kasir_print_hub' => (int) ($shop['kasir_print_hub'] ?? 0) === 1,
        'kasir_open_drawer' => (int) ($shop['kasir_open_drawer'] ?? 0) === 1,
        'beep_kitchen' => max(0, min(9, (int) ($shop['printer_beep_kitchen'] ?? 4))),
        'beep_kasir' => max(0, min(9, (int) ($shop['printer_beep_kasir'] ?? 0))),
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
        'delivery'        => 3, // Pro delivery QR + COD / DuitNow
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

/** Mark order cancelled — removed from kasir, kitchen, and income reports. */
function cancelShopOrder(int $orderId, int $shopId): bool
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id, status_order FROM orders WHERE id = ? AND shop_id = ? LIMIT 1'
    );
    $stmt->execute([$orderId, $shopId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    if (($row['status_order'] ?? '') === 'dibatalkan') {
        return true;
    }
    $pdo->prepare(
        'UPDATE orders SET status_order = ?, pickup_alert = 0 WHERE id = ? AND shop_id = ?'
    )->execute(['dibatalkan', $orderId, $shopId]);
    return true;
}

function shopAlertsTableExists(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $ok = (bool) db()->query("SHOW TABLES LIKE 'shop_alerts'")->fetch();
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * @param array<string,mixed> $meta
 */
function createShopAlert(int $shopId, string $jenis, string $title, string $body = '', array $meta = []): ?int
{
    if ($shopId <= 0 || !shopAlertsTableExists()) {
        return null;
    }
    try {
        $json = $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        db()->prepare(
            'INSERT INTO shop_alerts (shop_id, jenis, title, body, meta_json, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, ?)'
        )->execute([
            $shopId,
            substr($jenis, 0, 40),
            substr($title, 0, 160),
            substr($body, 0, 500),
            $json,
            function_exists('appNow') ? appNow() : date('Y-m-d H:i:s'),
        ]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * @return list<array<string,mixed>>
 */
function listShopAlerts(int $shopId, int $limit = 12, bool $unreadOnly = false): array
{
    if ($shopId <= 0 || !shopAlertsTableExists()) {
        return [];
    }
    $limit = max(1, min(50, $limit));
    try {
        $sql = 'SELECT id, jenis, title, body, meta_json, is_read, created_at
                FROM shop_alerts
                WHERE shop_id = ?';
        if ($unreadOnly) {
            $sql .= ' AND is_read = 0';
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $stmt = db()->prepare($sql);
        $stmt->execute([$shopId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $meta = [];
            if (!empty($row['meta_json'])) {
                $decoded = json_decode((string) $row['meta_json'], true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
            $out[] = [
                'id' => (int) $row['id'],
                'jenis' => (string) $row['jenis'],
                'title' => (string) $row['title'],
                'body' => (string) $row['body'],
                'meta' => $meta,
                'is_read' => (int) $row['is_read'] === 1,
                'created_at' => (string) $row['created_at'],
            ];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function countUnreadShopAlerts(int $shopId): int
{
    if ($shopId <= 0 || !shopAlertsTableExists()) {
        return 0;
    }
    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM shop_alerts WHERE shop_id = ? AND is_read = 0');
        $stmt->execute([$shopId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * @param list<int> $ids empty = mark all unread for shop
 */
function markShopAlertsRead(int $shopId, array $ids = []): int
{
    if ($shopId <= 0 || !shopAlertsTableExists()) {
        return 0;
    }
    try {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = true;
            }
        }
        if ($clean === []) {
            $stmt = db()->prepare('UPDATE shop_alerts SET is_read = 1 WHERE shop_id = ? AND is_read = 0');
            $stmt->execute([$shopId]);
            return $stmt->rowCount();
        }
        $idList = array_keys($clean);
        $ph = implode(',', array_fill(0, count($idList), '?'));
        $params = array_merge([$shopId], $idList);
        $stmt = db()->prepare(
            "UPDATE shop_alerts SET is_read = 1 WHERE shop_id = ? AND id IN ($ph) AND is_read = 0"
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (Throwable $e) {
        return 0;
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
         WHERE shop_id = ? AND status_bayar = 'belum_bayar' AND status_order != 'dibatalkan'
           AND (jenis_hidang IS NULL OR jenis_hidang != 'delivery')"
    );
    $pay->execute([$shopId]);
    $payRow = $pay->fetch() ?: ['n' => 0, 'amt' => 0];

    require_once __DIR__ . '/delivery.php';
    $delivery = deliveryOpsSnapshot($shopId, $shop);

    $alerts = listShopAlerts($shopId, 10, false);
    $alertsUnread = countUnreadShopAlerts($shopId);

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
        'delivery' => $delivery,
        'alerts' => [
            'unread' => $alertsUnread,
            'items' => $alerts,
        ],
    ];
}
