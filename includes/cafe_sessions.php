<?php
/**
 * Cafe mode — shop-level QR, per-customer sessions.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/shop.php';
require_once __DIR__ . '/customer_orders.php';
require_once __DIR__ . '/verification.php';

const CAFE_TABLE_NUMBER = 'Cafe';

function orderingModeColumnExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW COLUMNS FROM shops LIKE 'ordering_mode'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function customerSessionsTableExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW TABLES LIKE 'customer_sessions'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function orderSessionColumnExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW COLUMNS FROM orders LIKE 'session_id'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function shopOrderingMode(?array $shop): string
{
    if (!$shop || !orderingModeColumnExists()) {
        return 'table';
    }
    $mode = (string) ($shop['ordering_mode'] ?? 'table');
    return $mode === 'cafe' ? 'cafe' : 'table';
}

function shopIsCafeMode(?array $shop): bool
{
    return shopOrderingMode($shop) === 'cafe';
}

function shopCafeVerify(?array $shop): string
{
    if (!$shop) {
        return 'email';
    }
    $v = (string) ($shop['cafe_verify'] ?? 'email');
    return $v === 'none' ? 'none' : 'email';
}

function cafeEntryUrl(string $slug, string $shopToken): string
{
    return baseUrl('public/cafe.php?shop=' . urlencode($slug) . '&token=' . urlencode($shopToken));
}

function cafeSessionOrderUrl(string $sessionToken): string
{
    return baseUrl('public/order.php?s=' . urlencode($sessionToken));
}

function cafeSessionTrackUrl(string $sessionToken, int $orderId = 0, string $guestToken = ''): string
{
    $url = baseUrl('public/confirmation.php?s=' . urlencode($sessionToken));
    if ($orderId > 0) {
        $url .= '&order=' . $orderId;
    }
    if ($guestToken !== '') {
        $url .= '&gt=' . urlencode($guestToken);
    }
    return $url;
}

/**
 * Verify shop access via slug + shop_token (cafe entry QR).
 */
function findShopByAccess(string $slug, string $token): ?array
{
    if (!orderingModeColumnExists()) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT s.*, p.kod AS package_kod, p.nama_my AS package_nama_my, p.nama_en AS package_nama_en,
                p.retention_days
         FROM shops s
         INNER JOIN packages p ON p.id = s.package_id
         WHERE s.slug = ? AND s.shop_token = ? AND s.status = ? AND s.ordering_mode = ?
         LIMIT 1'
    );
    $stmt->execute([$slug, $token, 'aktif', 'cafe']);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ensureShopToken(array $shop): string
{
    $token = trim((string) ($shop['shop_token'] ?? ''));
    if ($token !== '') {
        return $token;
    }
    if (!orderingModeColumnExists()) {
        return '';
    }
    $token = generateToken(24);
    db()->prepare('UPDATE shops SET shop_token = ? WHERE id = ?')->execute([$token, (int) $shop['id']]);
    return $token;
}

/**
 * Virtual table for cafe orders (kitchen/kasir still see table_id).
 *
 * @return array<string, mixed>
 */
function ensureCafeTable(int $shopId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT t.*, s.nama_kedai, s.slug, s.status AS shop_status,
                s.sst_enabled, s.sst_rate, s.package_id, s.fulfillment_mode,
                s.ordering_mode, s.shop_token, s.cafe_verify,
                p.kod AS package_kod
         FROM tables t
         INNER JOIN shops s ON s.id = t.shop_id
         INNER JOIN packages p ON p.id = s.package_id
         WHERE t.shop_id = ? AND t.nomor_meja = ? AND t.status = 'aktif'
         LIMIT 1"
    );
    $stmt->execute([$shopId, CAFE_TABLE_NUMBER]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }

    $token = generateToken(24);
    $pdo->prepare(
        'INSERT INTO tables (shop_id, nomor_meja, token_akses, status) VALUES (?, ?, ?, ?)'
    )->execute([$shopId, CAFE_TABLE_NUMBER, $token, 'aktif']);

    $stmt->execute([$shopId, CAFE_TABLE_NUMBER]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Failed to create cafe table');
    }
    return $row;
}

function findSessionByToken(string $sessionToken): ?array
{
    if (!customerSessionsTableExists() || $sessionToken === '') {
        return null;
    }
    $stmt = db()->prepare(
        "SELECT cs.*, s.nama_kedai, s.slug, s.status AS shop_status,
                s.sst_enabled, s.sst_rate, s.package_id, s.fulfillment_mode,
                s.ordering_mode, s.shop_token, s.cafe_verify,
                p.kod AS package_kod,
                t.nomor_meja, t.token_akses
         FROM customer_sessions cs
         INNER JOIN shops s ON s.id = cs.shop_id
         INNER JOIN packages p ON p.id = s.package_id
         INNER JOIN tables t ON t.id = cs.table_id
         WHERE cs.session_token = ? AND cs.status != 'blocked'
           AND s.status = 'aktif' AND s.ordering_mode = 'cafe'
         LIMIT 1"
    );
    $stmt->execute([$sessionToken]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if ((string) ($row['expires_at'] ?? '') < appNow()) {
        return null;
    }
    return $row;
}

function assertSessionOrderRateLimit(int $sessionId, int $shopId): void
{
    if (!orderSessionColumnExists()) {
        return;
    }
    $burst = db()->prepare(
        "SELECT COUNT(*) FROM orders
         WHERE session_id = ? AND shop_id = ?
           AND status_bayar = 'belum_bayar'
           AND status_order != 'dibatalkan'
           AND waktu_order >= DATE_SUB(NOW(), INTERVAL 30 SECOND)"
    );
    $burst->execute([$sessionId, $shopId]);
    if ((int) $burst->fetchColumn() >= 3) {
        jsonError(t('order_rate_limited'), 429);
    }
}

function assertActiveSessionLimit(int $shopId, string $contactHash): void
{
    if (!customerSessionsTableExists() || $contactHash === '') {
        return;
    }
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM customer_sessions
         WHERE shop_id = ? AND contact_hash = ?
           AND status = 'active' AND expires_at > NOW()
           AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    );
    $stmt->execute([$shopId, $contactHash]);
    if ((int) $stmt->fetchColumn() >= 10) {
        jsonError(t('cafe_session_limit'), 429);
    }
}

/**
 * Create verified customer session after OTP or instant (verify=none).
 *
 * @return array{session_token:string,session_id:int,order_url:string}
 */
function createCustomerSession(
    array $shop,
    string $namaPelanggan,
    ?string $contactHash = null,
    bool $verified = true
): array {
    if (!customerSessionsTableExists()) {
        jsonError('Cafe mode unavailable', 503);
    }

    $namaPelanggan = normalizeCustomerName($namaPelanggan);
    if (!isValidCustomerName($namaPelanggan)) {
        jsonError(t('guest_name_required'), 400);
    }

    $shopId = (int) $shop['id'];
    if ($contactHash !== null && $contactHash !== '') {
        assertActiveSessionLimit($shopId, $contactHash);
    }

    $table = ensureCafeTable($shopId);
    $sessionToken = generateToken(24);
    $hours = 8;
    $expiresAt = date('Y-m-d H:i:s', time() + ($hours * 3600));
    $verifiedAt = $verified ? appNow() : null;
    $status = $verified ? 'active' : 'pending';

    db()->prepare(
        'INSERT INTO customer_sessions
         (shop_id, session_token, table_id, nama_pelanggan, contact_hash, verified_at, expires_at, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $shopId,
        $sessionToken,
        (int) $table['id'],
        $namaPelanggan,
        $contactHash,
        $verifiedAt,
        $expiresAt,
        $status,
    ]);

    return [
        'session_token' => $sessionToken,
        'session_id' => (int) db()->lastInsertId(),
        'order_url' => cafeSessionOrderUrl($sessionToken),
    ];
}

/** @return list<array<string, mixed>> */
function fetchActiveSessionOrders(array $session, string $lang): array
{
    if (!orderSessionColumnExists()) {
        return [];
    }
    $shopId = (int) $session['shop_id'];
    $sessionId = (int) $session['id'];
    $hasGuestToken = orderGuestTokenColumnExists();
    $guestCol = $hasGuestToken ? ', guest_token' : '';

    $stmt = db()->prepare(
        "SELECT id, subtotal, sst_rate, sst_jumlah, total_harga, status_order, waktu_order,
                jenis_hidang, nama_pelanggan, pickup_alert{$guestCol}
         FROM orders
         WHERE session_id = ? AND shop_id = ?
           AND status_bayar = 'belum_bayar'
           AND status_order NOT IN ('selesai', 'dibatalkan')
         ORDER BY id DESC"
    );
    $stmt->execute([$sessionId, $shopId]);
    $rows = $stmt->fetchAll();
    if ($rows === []) {
        return [];
    }

    $orderIds = array_map(static fn(array $r): int => (int) $r['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = db()->prepare(
        "SELECT order_id, id, qty, status_item, nama_saat_order_my, nama_saat_order_en, kategori_saat_order
         FROM order_items
         WHERE order_id IN ($placeholders)
         ORDER BY order_id ASC, id ASC"
    );
    $itemStmt->execute($orderIds);
    $itemsByOrder = [];
    foreach ($itemStmt->fetchAll() as $it) {
        $oid = (int) $it['order_id'];
        $itemsByOrder[$oid][] = $it;
    }

    $fulfillment = shopFulfillment($session);
    $orders = [];
    foreach ($rows as $row) {
        $oid = (int) $row['id'];
        $items = customerOrderItemsPayload($itemsByOrder[$oid] ?? [], $lang);
        $orders[] = customerOrderPayload($row, $items, $fulfillment, $hasGuestToken);
    }
    return $orders;
}

/** Build table-like context from session for shared order UI. */
function sessionAsTableContext(array $session): array
{
    return [
        'id' => (int) $session['table_id'],
        'shop_id' => (int) $session['shop_id'],
        'nomor_meja' => CAFE_TABLE_NUMBER,
        'token_akses' => (string) ($session['token_akses'] ?? ''),
        'nama_kedai' => (string) ($session['nama_kedai'] ?? ''),
        'slug' => (string) ($session['slug'] ?? ''),
        'shop_status' => (string) ($session['shop_status'] ?? 'aktif'),
        'sst_enabled' => (int) ($session['sst_enabled'] ?? 0),
        'sst_rate' => (float) ($session['sst_rate'] ?? 0),
        'package_id' => (int) ($session['package_id'] ?? 0),
        'fulfillment_mode' => (string) ($session['fulfillment_mode'] ?? 'waiter'),
        'ordering_mode' => 'cafe',
        'package_kod' => (string) ($session['package_kod'] ?? ''),
    ];
}

function touchSessionActivity(int $sessionId): void
{
    if (!customerSessionsTableExists()) {
        return;
    }
    db()->prepare('UPDATE customer_sessions SET last_order_at = ? WHERE id = ?')
        ->execute([appNow(), $sessionId]);
}

function enableCafeModeForShop(int $shopId, string $verify = 'email'): void
{
    if (!orderingModeColumnExists()) {
        throw new RuntimeException('Cafe mode schema missing');
    }
    if (!in_array($verify, ['email', 'none'], true)) {
        $verify = 'email';
    }
    $shop = findShopById($shopId);
    if (!$shop) {
        throw new RuntimeException('Shop not found');
    }
    ensureCafeTable($shopId);
    $token = ensureShopToken($shop);
    db()->prepare(
        'UPDATE shops SET ordering_mode = ?, cafe_verify = ?, shop_token = ? WHERE id = ?'
    )->execute(['cafe', $verify, $token, $shopId]);
}

function disableCafeModeForShop(int $shopId): void
{
    if (!orderingModeColumnExists()) {
        return;
    }
    db()->prepare('UPDATE shops SET ordering_mode = ? WHERE id = ?')
        ->execute(['table', $shopId]);
}
