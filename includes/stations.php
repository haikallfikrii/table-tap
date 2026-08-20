<?php
/**
 * Kitchen / drinks / custom work stations (Pro: extra stations like Western).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function stationsTableExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW TABLES LIKE 'stations'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function menuStationColumnExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW COLUMNS FROM menu_items LIKE 'station_id'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function orderStationColumnExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW COLUMNS FROM order_items LIKE 'station_id_saat_order'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function userStationColumnExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW COLUMNS FROM users LIKE 'station_id'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function maxCustomStations(): int
{
    return 8;
}

function stationKodFromName(string $name): string
{
    $kod = strtolower(trim($name));
    $kod = preg_replace('/[^a-z0-9]+/i', '-', $kod) ?? '';
    $kod = trim($kod, '-');
    if ($kod === '' || in_array($kod, ['dapur', 'minuman'], true)) {
        $kod = 'stesen-' . bin2hex(random_bytes(3));
    }
    return substr($kod, 0, 40);
}

/** @return list<array<string,mixed>> */
function shopStations(int $shopId, bool $activeOnly = true): array
{
    if (!stationsTableExists()) {
        return [];
    }
    ensureShopStations($shopId);
    $sql = 'SELECT * FROM stations WHERE shop_id = ?';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY urutan ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute([$shopId]);
    return $stmt->fetchAll() ?: [];
}

function findShopStation(int $shopId, int $stationId): ?array
{
    if (!stationsTableExists() || $stationId <= 0) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM stations WHERE id = ? AND shop_id = ? LIMIT 1');
    $stmt->execute([$stationId, $shopId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function shopStationByKod(int $shopId, string $kod): ?array
{
    if (!stationsTableExists()) {
        return null;
    }
    ensureShopStations($shopId);
    $stmt = db()->prepare('SELECT * FROM stations WHERE shop_id = ? AND kod = ? LIMIT 1');
    $stmt->execute([$shopId, $kod]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function stationLabel(array $station, string $lang = 'my'): string
{
    $name = $lang === 'en'
        ? (string) ($station['nama_en'] ?: $station['nama_my'] ?? '')
        : (string) ($station['nama_my'] ?: $station['nama_en'] ?? '');
    return $name !== '' ? $name : (string) ($station['kod'] ?? 'Stesen');
}

function defaultStationForKategori(int $shopId, string $kategori): ?array
{
    $kod = $kategori === 'minuman' ? 'minuman' : 'dapur';
    return shopStationByKod($shopId, $kod);
}

function resolveMenuStationId(int $shopId, string $kategori, int $postedStationId): ?int
{
    if (!stationsTableExists() || !menuStationColumnExists()) {
        return null;
    }
    if ($postedStationId > 0) {
        $st = findShopStation($shopId, $postedStationId);
        if ($st && (int) $st['is_active'] === 1) {
            return (int) $st['id'];
        }
    }
    $fallback = defaultStationForKategori($shopId, $kategori);
    return $fallback ? (int) $fallback['id'] : null;
}

function userAssignedStationId(?array $user): ?int
{
    if (!$user) {
        return null;
    }
    $id = $user['station_id'] ?? null;
    if ($id === null || $id === '' || (int) $id <= 0) {
        return null;
    }
    return (int) $id;
}

function userCanAccessStation(?array $user, ?array $station): bool
{
    if (!$user || !$station) {
        return false;
    }
    if (($user['role'] ?? '') === 'owner') {
        return true;
    }
    $role = (string) ($user['role'] ?? '');
    if (!in_array($role, ['dapur', 'minuman'], true)) {
        return false;
    }
    $assigned = userAssignedStationId($user);
    if ($assigned !== null) {
        return $assigned === (int) $station['id'];
    }
    $kod = (string) ($station['kod'] ?? '');
    if ($role === 'dapur') {
        return $kod === 'dapur';
    }
    return $kod === 'minuman';
}

/**
 * Station for a kitchen screen. Staff with an assigned station are locked to it.
 */
function resolveKitchenStation(array $user, int $shopId, ?int $requestedId, string $fallbackKod): ?array
{
    if (!stationsTableExists()) {
        return [
            'id' => 0,
            'shop_id' => $shopId,
            'kod' => $fallbackKod === 'minuman' ? 'minuman' : 'dapur',
            'nama_my' => $fallbackKod === 'minuman' ? 'Minuman' : 'Dapur',
            'nama_en' => $fallbackKod === 'minuman' ? 'Drinks' : 'Kitchen',
            'is_system' => 1,
            'is_active' => 1,
        ];
    }

    $assigned = userAssignedStationId($user);
    if ($assigned !== null && ($user['role'] ?? '') !== 'owner') {
        $st = findShopStation($shopId, $assigned);
        return ($st && (int) $st['is_active'] === 1) ? $st : null;
    }

    if ($requestedId !== null && $requestedId > 0) {
        $st = findShopStation($shopId, $requestedId);
        if ($st && (int) $st['is_active'] === 1 && userCanAccessStation($user, $st)) {
            return $st;
        }
        return null;
    }

    $kod = $fallbackKod === 'minuman' ? 'minuman' : 'dapur';
    return shopStationByKod($shopId, $kod);
}

function stationScreenUrl(array $station): string
{
    $id = (int) ($station['id'] ?? 0);
    $kod = (string) ($station['kod'] ?? 'dapur');
    if ($kod === 'minuman') {
        return baseUrl('admin/minuman.php' . ($id > 0 ? ('?station=' . $id) : ''));
    }
    return baseUrl('admin/dapur.php' . ($id > 0 ? ('?station=' . $id) : ''));
}

function ensureShopStations(int $shopId, ?PDO $pdo = null): void
{
    if ($shopId <= 0 || !stationsTableExists()) {
        return;
    }
    $pdo = $pdo ?? db();
    $chk = $pdo->prepare("SELECT kod FROM stations WHERE shop_id = ? AND kod IN ('dapur','minuman')");
    $chk->execute([$shopId]);
    $have = [];
    foreach ($chk->fetchAll() as $row) {
        $have[(string) $row['kod']] = true;
    }
    $ins = $pdo->prepare(
        'INSERT INTO stations (shop_id, kod, nama_my, nama_en, is_system, urutan, is_active)
         VALUES (?, ?, ?, ?, 1, ?, 1)'
    );
    if (empty($have['dapur'])) {
        $ins->execute([$shopId, 'dapur', 'Dapur', 'Kitchen', 1]);
    }
    if (empty($have['minuman'])) {
        $ins->execute([$shopId, 'minuman', 'Minuman', 'Drinks', 2]);
    }
}

function ensureAllShopStations(?PDO $pdo = null): void
{
    if (!stationsTableExists()) {
        return;
    }
    $pdo = $pdo ?? db();
    foreach ($pdo->query('SELECT id FROM shops')->fetchAll() as $row) {
        ensureShopStations((int) $row['id'], $pdo);
    }
}

function backfillMenuAndOrderStations(?PDO $pdo = null): void
{
    $pdo = $pdo ?? db();
    if (!stationsTableExists()) {
        return;
    }
    if (menuStationColumnExists()) {
        $pdo->exec(
            "UPDATE menu_items mi
             INNER JOIN stations s
               ON s.shop_id = mi.shop_id
              AND s.kod = IF(mi.kategori = 'minuman', 'minuman', 'dapur')
             SET mi.station_id = s.id
             WHERE mi.station_id IS NULL"
        );
    }
    if (orderStationColumnExists()) {
        $pdo->exec(
            "UPDATE order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             INNER JOIN stations s
               ON s.shop_id = o.shop_id
              AND s.kod = IF(oi.kategori_saat_order = 'minuman', 'minuman', 'dapur')
             SET oi.station_id_saat_order = s.id
             WHERE oi.station_id_saat_order IS NULL"
        );
    }
}

function countCustomStations(int $shopId): int
{
    if (!stationsTableExists()) {
        return 0;
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM stations WHERE shop_id = ? AND is_system = 0');
    $stmt->execute([$shopId]);
    return (int) $stmt->fetchColumn();
}

function emptyStationCounts(): array
{
    return [
        'items' => 0,
        'orders' => 0,
        'menunggu' => 0,
        'sedang_dimasak' => 0,
        'siap' => 0,
        'diambil' => 0,
    ];
}
