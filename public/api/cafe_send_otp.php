<?php
/**
 * Send email OTP for cafe / delivery checkout.
 * POST JSON: { shop, token, email, nama_pelanggan, lang }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requirePost();
$body = readJsonBody();

$slug = trim((string) ($body['shop'] ?? ''));
$token = trim((string) ($body['token'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$nama = trim((string) ($body['nama_pelanggan'] ?? ''));
$lang = (($body['lang'] ?? '') === 'en') ? 'en' : 'my';

if ($slug === '' || $token === '') {
    jsonError('Invalid request', 403);
}

$viaDelivery = false;
$shop = findShopByAccess($slug, $token);
if (!$shop) {
    $shop = findShopByDeliveryAccess($slug, $token);
    $viaDelivery = (bool) $shop;
}
if (!$shop) {
    jsonError('Invalid shop access', 403);
}

$needsEmail = $viaDelivery || shopRequiresEmailVerify($shop);
if (!$needsEmail) {
    jsonError('Verification not required', 400);
}

$nama = normalizeCustomerName($nama);
if (!isValidCustomerName($nama)) {
    jsonError(t('guest_name_required'), 400);
}

$result = sendEmailOtp((int) $shop['id'], $email, $lang, shopBrand($shop));

jsonResponse([
    'ok' => true,
    'email_masked' => $result['email_masked'],
    'expires_in' => $result['expires_in'],
]);
