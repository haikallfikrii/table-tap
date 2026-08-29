<?php
/**
 * Bulk-assign menu items in a customer category to a work station.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/stations.php';
require_once __DIR__ . '/menu_categories.php';

function resolveShopIdForBulk(string $shopRef): ?int
{
    $shopRef = trim($shopRef);
    if ($shopRef === '') {
        return null;
    }
    $pdo = db();
    if (ctype_digit($shopRef)) {
        $id = (int) $shopRef;
        return findShopById($id) ? $id : null;
    }
    $stmt = $pdo->prepare('SELECT id FROM shops WHERE slug = ? LIMIT 1');
    $stmt->execute([$shopRef]);
    $id = (int) $stmt->fetchColumn();
    if ($id > 0) {
        return $id;
    }
    $stmt = $pdo->prepare(
        "SELECT shop_id FROM users WHERE username = ? AND role = 'owner' LIMIT 1"
    );
    $stmt->execute([$shopRef]);
    $id = (int) $stmt->fetchColumn();
    return $id > 0 ? $id : null;
}

/** @return list<array<string,mixed>> */
function findMenuCategoriesByRef(int $shopId, string $categoryRef): array
{
    $categoryRef = strtolower(trim($categoryRef));
    if ($categoryRef === '') {
        return [];
    }
    $cats = shopMenuCategories($shopId, false);
    $exact = [];
    $fuzzy = [];
    foreach ($cats as $cat) {
        $kod = strtolower((string) ($cat['kod'] ?? ''));
        $my = strtolower((string) ($cat['nama_my'] ?? ''));
        $en = strtolower((string) ($cat['nama_en'] ?? ''));
        if ($kod === $categoryRef) {
            $exact[] = $cat;
            continue;
        }
        if (str_contains($kod, $categoryRef) || str_contains($my, $categoryRef) || str_contains($en, $categoryRef)) {
            $fuzzy[] = $cat;
        }
    }
    return $exact !== [] ? $exact : $fuzzy;
}

function findStationByRef(int $shopId, string $stationRef): ?array
{
    $stationRef = strtolower(trim($stationRef));
    if ($stationRef === '') {
        return null;
    }
    ensureShopStations($shopId);
    $stations = shopStations($shopId, false);
    foreach ($stations as $st) {
        if ((string) ($st['kod'] ?? '') === $stationRef) {
            return $st;
        }
    }
    foreach ($stations as $st) {
        $kod = strtolower((string) ($st['kod'] ?? ''));
        $my = strtolower((string) ($st['nama_my'] ?? ''));
        $en = strtolower((string) ($st['nama_en'] ?? ''));
        if (str_contains($kod, $stationRef) || str_contains($my, $stationRef) || str_contains($en, $stationRef)) {
            return $st;
        }
    }
    if (in_array($stationRef, ['air', 'drinks', 'minuman'], true)) {
        return shopStationByKod($shopId, 'minuman');
    }
    return null;
}

/**
 * @return array{ok:bool,shop_id?:int,category?:array,station?:array,updated:int,items:list<array<string,mixed>>,error?:string}
 */
function bulkSetMenuCategoryStation(int $shopId, string $categoryRef, string $stationRef): array
{
    if (!menuStationColumnExists()) {
        return ['ok' => false, 'updated' => 0, 'items' => [], 'error' => 'station_id column missing'];
    }

    $categories = findMenuCategoriesByRef($shopId, $categoryRef);
    if ($categories === []) {
        return ['ok' => false, 'updated' => 0, 'items' => [], 'error' => 'Category not found: ' . $categoryRef];
    }
    if (count($categories) > 1) {
        return [
            'ok' => false,
            'updated' => 0,
            'items' => [],
            'error' => 'Multiple categories match; use exact kod. Matches: '
                . implode(', ', array_map(static fn ($c) => (string) ($c['kod'] ?? ''), $categories)),
        ];
    }
    $category = $categories[0];
    $categoryId = (int) $category['id'];

    $station = findStationByRef($shopId, $stationRef);
    if (!$station) {
        return ['ok' => false, 'updated' => 0, 'items' => [], 'error' => 'Station not found: ' . $stationRef];
    }
    $stationId = (int) $station['id'];

    $pdo = db();
    $sel = $pdo->prepare(
        'SELECT id, nama_my, nama_en, station_id
         FROM menu_items
         WHERE shop_id = ? AND menu_category_id = ?'
    );
    $sel->execute([$shopId, $categoryId]);
    $rows = $sel->fetchAll() ?: [];

    $upd = $pdo->prepare(
        'UPDATE menu_items SET station_id = ? WHERE shop_id = ? AND menu_category_id = ?'
    );
    $upd->execute([$stationId, $shopId, $categoryId]);

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'id' => (int) $row['id'],
            'nama_my' => (string) ($row['nama_my'] ?? ''),
            'previous_station_id' => (int) ($row['station_id'] ?? 0),
            'station_id' => $stationId,
        ];
    }

    return [
        'ok' => true,
        'shop_id' => $shopId,
        'category' => [
            'id' => $categoryId,
            'kod' => (string) ($category['kod'] ?? ''),
            'nama_my' => (string) ($category['nama_my'] ?? ''),
        ],
        'station' => [
            'id' => $stationId,
            'kod' => (string) ($station['kod'] ?? ''),
            'nama_my' => (string) ($station['nama_my'] ?? ''),
        ],
        'updated' => count($items),
        'items' => $items,
    ];
}
