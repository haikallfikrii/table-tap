<?php
/**
 * Delivery mode — Pro QR channel with address + COD / DuitNow proof.
 */

declare(strict_types=1);

require_once __DIR__ . '/cafe_sessions.php';
require_once __DIR__ . '/shop.php';

const DELIVERY_TABLE_NUMBER = 'Delivery';

function deliveryColumnsExist(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $ok = (bool) db()->query("SHOW COLUMNS FROM shops LIKE 'delivery_enabled'")->fetch();
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

function orderDeliveryColumnsExist(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $ok = (bool) db()->query("SHOW COLUMNS FROM orders LIKE 'payment_method'")->fetch();
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

function shopDeliveryEnabled(?array $shop): bool
{
    if (!$shop || !deliveryColumnsExist()) {
        return false;
    }
    if (!shopHasFeature($shop, 'delivery')) {
        return false;
    }
    return (int) ($shop['delivery_enabled'] ?? 0) === 1;
}

function shopPayMethods(?array $shop): array
{
    if (!deliveryColumnsExist()) {
        return ['counter' => true, 'cod' => false, 'duitnow' => false];
    }
    return [
        'counter' => (int) ($shop['pay_counter'] ?? 1) === 1,
        'cod' => (int) ($shop['pay_cod'] ?? 1) === 1,
        'duitnow' => (int) ($shop['pay_duitnow'] ?? 1) === 1,
    ];
}

function shopHoldKitchenUntilPaid(?array $shop): bool
{
    if (!deliveryColumnsExist()) {
        return true;
    }
    return (int) ($shop['hold_kitchen_until_paid'] ?? 1) === 1;
}

function normalizePhone(string $phone): ?string
{
    $phone = trim($phone);
    $digits = preg_replace('/[^\d+]/', '', $phone) ?? '';
    $digits = preg_replace('/^\+?60/', '0', $digits) ?? $digits;
    $digits = preg_replace('/\D/', '', $digits) ?? '';
    if (strlen($digits) < 9 || strlen($digits) > 15) {
        return null;
    }
    if ($digits[0] !== '0' && strlen($digits) >= 9) {
        // keep as-is for non-MY formats with enough digits
    }
    return $digits;
}

function isValidPhone(string $phone): bool
{
    return normalizePhone($phone) !== null;
}

function normalizeAddress(string $address): string
{
    $address = trim(preg_replace('/\s+/u', ' ', $address) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($address, 0, 500);
    }
    return substr($address, 0, 500);
}

function isValidAddress(string $address): bool
{
    $len = function_exists('mb_strlen') ? mb_strlen($address) : strlen($address);
    return $len >= 8 && $len <= 500;
}

function ensureDeliveryTable(int $shopId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT * FROM tables WHERE shop_id = ? AND nomor_meja = ? AND status = 'aktif' LIMIT 1"
    );
    $stmt->execute([$shopId, DELIVERY_TABLE_NUMBER]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }
    $token = generateToken(16);
    $pdo->prepare(
        'INSERT INTO tables (shop_id, nomor_meja, token_akses, status) VALUES (?, ?, ?, ?)'
    )->execute([$shopId, DELIVERY_TABLE_NUMBER, $token, 'aktif']);
    $stmt->execute([$shopId, DELIVERY_TABLE_NUMBER]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Could not create delivery table');
    }
    return $row;
}

function ensureDeliveryToken(array $shop): string
{
    $token = trim((string) ($shop['delivery_token'] ?? ''));
    if ($token !== '') {
        return $token;
    }
    $token = generateToken(24);
    db()->prepare('UPDATE shops SET delivery_token = ? WHERE id = ?')
        ->execute([$token, (int) $shop['id']]);
    return $token;
}

function findShopByDeliveryAccess(string $slug, string $token): ?array
{
    if (!deliveryColumnsExist() || $slug === '' || $token === '') {
        return null;
    }
    $stmt = db()->prepare(
        "SELECT s.*, p.kod AS package_kod, p.nama_my AS package_nama_my, p.nama_en AS package_nama_en,
                p.retention_days
         FROM shops s
         INNER JOIN packages p ON p.id = s.package_id
         WHERE s.slug = ? AND s.delivery_token = ? AND s.status = 'aktif' AND s.delivery_enabled = 1
         LIMIT 1"
    );
    $stmt->execute([$slug, $token]);
    $shop = $stmt->fetch();
    if (!$shop || !shopHasFeature($shop, 'delivery')) {
        return null;
    }
    return $shop;
}

function deliveryEntryUrl(string $slug, string $deliveryToken): string
{
    return baseUrl('public/delivery.php?shop=' . rawurlencode($slug) . '&token=' . rawurlencode($deliveryToken));
}

function deliveryBrowseOrderUrl(string $slug, string $deliveryToken): string
{
    return baseUrl(
        'public/order.php?shop=' . rawurlencode($slug)
        . '&token=' . rawurlencode($deliveryToken)
        . '&channel=delivery'
    );
}

function shopAsDeliveryContext(array $shop): array
{
    $table = ensureDeliveryTable((int) $shop['id']);
    return [
        'id' => (int) $table['id'],
        'shop_id' => (int) $shop['id'],
        'nomor_meja' => DELIVERY_TABLE_NUMBER,
        'token_akses' => (string) ($table['token_akses'] ?? ''),
        'nama_kedai' => (string) ($shop['nama_kedai'] ?? ''),
        'slug' => (string) ($shop['slug'] ?? ''),
        'shop_status' => (string) ($shop['status'] ?? 'aktif'),
        'sst_enabled' => (int) ($shop['sst_enabled'] ?? 0),
        'sst_rate' => (float) ($shop['sst_rate'] ?? 0),
        'package_id' => (int) ($shop['package_id'] ?? 0),
        'fulfillment_mode' => (string) ($shop['fulfillment_mode'] ?? 'waiter'),
        'ordering_mode' => (string) ($shop['ordering_mode'] ?? 'table'),
        'cafe_verify' => shopContactVerify($shop),
        'package_kod' => (string) ($shop['package_kod'] ?? ''),
        'delivery_enabled' => 1,
        'pay_cod' => (int) ($shop['pay_cod'] ?? 1),
        'pay_duitnow' => (int) ($shop['pay_duitnow'] ?? 1),
        'pay_counter' => (int) ($shop['pay_counter'] ?? 1),
        'duitnow_qr_url' => (string) ($shop['duitnow_qr_url'] ?? ''),
        'hold_kitchen_until_paid' => (int) ($shop['hold_kitchen_until_paid'] ?? 1),
        'delivery_require_phone' => (int) ($shop['delivery_require_phone'] ?? 0),
        'channel' => 'delivery',
    ];
}

/**
 * Attach delivery / payment meta after order create.
 */
function attachOrderDeliveryMeta(
    int $orderId,
    int $shopId,
    string $phone,
    string $address,
    string $paymentMethod,
    string $proofStatus = 'none'
): void {
    if (!orderDeliveryColumnsExist()) {
        return;
    }
    $method = in_array($paymentMethod, ['counter', 'cod', 'duitnow'], true) ? $paymentMethod : 'counter';
    $proof = in_array($proofStatus, ['none', 'uploaded', 'rejected', 'confirmed'], true) ? $proofStatus : 'none';
    db()->prepare(
        'UPDATE orders
         SET phone = ?, alamat = ?, payment_method = ?, payment_proof_status = ?, jenis_hidang = ?
         WHERE id = ? AND shop_id = ?'
    )->execute([
        $phone !== '' ? $phone : null,
        $address !== '' ? $address : null,
        $method,
        $proof,
        'delivery',
        $orderId,
        $shopId,
    ]);
}

function orderNeedsPaymentHold(array $order, ?array $shop): bool
{
    if (!orderDeliveryColumnsExist()) {
        return false;
    }
    $method = (string) ($order['payment_method'] ?? 'counter');
    $bayar = (string) ($order['status_bayar'] ?? 'belum_bayar');
    $proof = (string) ($order['payment_proof_status'] ?? 'none');
    if ($bayar === 'lunas') {
        return false;
    }
    if ($method === 'cod') {
        return false; // cook immediately
    }
    if ($method === 'duitnow') {
        if (!shopHoldKitchenUntilPaid($shop)) {
            return false;
        }
        return $proof !== 'confirmed';
    }
    // counter / unpaid delivery: hold if shop says so
    if ($method === 'counter' && shopHoldKitchenUntilPaid($shop)) {
        return true;
    }
    return false;
}

function enableDeliveryForShop(int $shopId): void
{
    if (!deliveryColumnsExist()) {
        throw new RuntimeException('Delivery schema missing');
    }
    $shop = findShopById($shopId);
    if (!$shop || !shopHasFeature($shop, 'delivery')) {
        throw new RuntimeException('Delivery requires Pro');
    }
    ensureDeliveryTable($shopId);
    $token = ensureDeliveryToken($shop);
    db()->prepare(
        'UPDATE shops SET delivery_enabled = 1, delivery_token = ? WHERE id = ?'
    )->execute([$token, $shopId]);
}

function disableDeliveryForShop(int $shopId): void
{
    if (!deliveryColumnsExist()) {
        return;
    }
    db()->prepare('UPDATE shops SET delivery_enabled = 0 WHERE id = ?')->execute([$shopId]);
}
