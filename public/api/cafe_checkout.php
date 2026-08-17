<?php
/**
 * Cafe checkout — verify (optional OTP), create session, submit order in one step.
 * POST JSON: { shop, token, nama_pelanggan, email?, code?, items, jenis_hidang }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requirePost();
$body = readJsonBody();

$slug = trim((string) ($body['shop'] ?? ''));
$token = trim((string) ($body['token'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$code = trim((string) ($body['code'] ?? ''));
$nama = trim((string) ($body['nama_pelanggan'] ?? ''));
$items = $body['items'] ?? [];
$jenisHidang = (($body['jenis_hidang'] ?? '') === 'takeaway') ? 'takeaway' : 'dine_in';

if ($slug === '' || $token === '') {
    jsonError('Invalid request', 403);
}

$shop = findShopByAccess($slug, $token);
if (!$shop) {
    jsonError('Invalid shop access', 403);
}

$verifyMode = shopCafeVerify($shop);
$selfPickup = shopFulfillment($shop) === 'self_pickup';
$contactHash = null;

if ($verifyMode === 'email') {
    if ($code === '') {
        jsonError(t('cafe_otp_required'), 400);
    }
    $contactHash = verifyEmailOtp((int) $shop['id'], $email, $code);
    if ($contactHash === null) {
        jsonError(t('cafe_otp_invalid'), 400);
    }
} elseif ($email !== '') {
    $normalized = normalizeEmail($email);
    if ($normalized !== null) {
        $contactHash = hashContact($normalized);
    }
}

$nama = normalizeCustomerName($nama);
if ($selfPickup && !isValidCustomerName($nama)) {
    jsonError(t('guest_name_required'), 400);
}
if (!$selfPickup && !isValidCustomerName($nama)) {
    $local = normalizeEmail($email);
    $fallback = $local !== null ? (string) strtok($local, '@') : 'Pelanggan';
    $nama = normalizeCustomerName($fallback);
    if (!isValidCustomerName($nama)) {
        $nama = 'Pelanggan';
    }
}

$session = createCustomerSession($shop, $nama, $contactHash, true);
$table = shopAsBrowseContext($shop);

$created = createShopOrder(
    $table,
    $shop,
    is_array($items) ? $items : [],
    $jenisHidang,
    $nama,
    'qr',
    false,
    (int) $session['session_id']
);

$orderId = $created['order_id'];
$guestToken = (string) ($created['guest_token'] ?? '');

jsonResponse([
    'ok' => true,
    'order_id' => $orderId,
    'session_token' => $session['session_token'],
    'redirect' => cafeSessionTrackUrl($session['session_token'], $orderId, $guestToken),
]);
