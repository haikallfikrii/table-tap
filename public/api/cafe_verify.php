<?php
/**
 * Verify email OTP and create customer session.
 * POST JSON: { shop, token, email, code, nama_pelanggan }
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

if ($slug === '' || $token === '') {
    jsonError('Invalid request', 403);
}

$shop = findShopByAccess($slug, $token);
if (!$shop) {
    jsonError('Invalid shop access', 403);
}

$contactHash = verifyEmailOtp((int) $shop['id'], $email, $code);
if ($contactHash === null) {
    jsonError(t('cafe_otp_invalid'), 400);
}

$session = createCustomerSession($shop, $nama, $contactHash, true, normalizeEmail($email));

jsonResponse([
    'ok' => true,
    'session_token' => $session['session_token'],
    'redirect' => $session['order_url'],
]);
