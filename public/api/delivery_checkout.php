<?php
/**
 * Delivery checkout — create order with address + email OTP + optional phone + payment.
 * POST JSON: { shop, token, nama_pelanggan, phone?, email, code, alamat, payment_method, items }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/shift.php';

requirePost();
$body = readJsonBody();

$slug = trim((string) ($body['shop'] ?? ''));
$token = trim((string) ($body['token'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$code = trim((string) ($body['code'] ?? ''));
$phoneRaw = trim((string) ($body['phone'] ?? ''));
$nama = trim((string) ($body['nama_pelanggan'] ?? ''));
$alamat = normalizeAddress((string) ($body['alamat'] ?? ''));
$paymentMethod = (string) ($body['payment_method'] ?? 'cod');
$items = $body['items'] ?? [];

if ($slug === '' || $token === '') {
    jsonError('Invalid request', 403);
}

$shop = findShopByDeliveryAccess($slug, $token);
if (!$shop) {
    jsonError(t('delivery_invalid_link'), 403);
}
assertShopAcceptingOrders($shop);

$methods = shopPayMethods($shop);
if (!isset($methods[$paymentMethod]) || !$methods[$paymentMethod]) {
    $paymentMethod = 'cod';
    foreach (['cod', 'duitnow', 'counter'] as $m) {
        if (!empty($methods[$m])) {
            $paymentMethod = $m;
            break;
        }
    }
}

// Delivery always requires email OTP
if ($code === '') {
    jsonError(t('cafe_otp_required'), 400);
}
$contactHash = verifyEmailOtp((int) $shop['id'], $email, $code);
if ($contactHash === null) {
    jsonError(t('cafe_otp_invalid'), 400);
}
$normalizedEmail = normalizeEmail($email);
if ($normalizedEmail === null) {
    jsonError(t('cafe_email_invalid'), 400);
}
assertDeliveryContactRateLimit((int) $shop['id'], $normalizedEmail);

$phone = '';
if (shopDeliveryRequirePhone($shop) || $phoneRaw !== '') {
    $phone = normalizePhone($phoneRaw) ?? '';
    if (shopDeliveryRequirePhone($shop) && $phone === '') {
        jsonError(t('phone_required'), 400);
    }
}

if (!isValidAddress($alamat)) {
    jsonError(t('address_required'), 400);
}

$nama = normalizeCustomerName($nama);
if (!isValidCustomerName($nama)) {
    jsonError(t('guest_name_required'), 400);
}

$table = shopAsDeliveryContext($shop);

$created = createShopOrder(
    $table,
    $shop,
    is_array($items) ? $items : [],
    'delivery',
    $nama,
    'qr',
    false,
    null,
    $normalizedEmail
);

$orderId = $created['order_id'];
$guestToken = (string) ($created['guest_token'] ?? '');
$proofStatus = 'none';
attachOrderDeliveryMeta($orderId, (int) $shop['id'], $phone, $alamat, $paymentMethod, $proofStatus);

$track = baseUrl(
    'public/confirmation.php?meja=' . rawurlencode(DELIVERY_TABLE_NUMBER)
    . '&token=' . rawurlencode((string) $table['token_akses'])
    . '&order=' . $orderId
    . ($guestToken !== '' ? '&gt=' . rawurlencode($guestToken) : '')
    . '&channel=delivery'
);

jsonResponse([
    'ok' => true,
    'order_id' => $orderId,
    'payment_method' => $paymentMethod,
    'needs_proof' => $paymentMethod === 'duitnow',
    'duitnow_qr_url' => (string) ($shop['duitnow_qr_url'] ?? ''),
    'redirect' => $track,
]);
