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
