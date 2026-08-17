<?php
/**
 * Start cafe session without OTP (cafe_verify = none).
 * POST JSON: { shop, token, nama_pelanggan }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requirePost();
$body = readJsonBody();

$slug = trim((string) ($body['shop'] ?? ''));
$token = trim((string) ($body['token'] ?? ''));
$nama = trim((string) ($body['nama_pelanggan'] ?? ''));

if ($slug === '' || $token === '') {
    jsonError('Invalid request', 403);
}

$shop = findShopByAccess($slug, $token);
if (!$shop) {
    jsonError('Invalid shop access', 403);
}

if (shopCafeVerify($shop) !== 'none') {
    jsonError('Verification required', 400);
}

$session = createCustomerSession($shop, $nama, null, true);

jsonResponse([
    'ok' => true,
    'session_token' => $session['session_token'],
    'redirect' => $session['order_url'],
]);
