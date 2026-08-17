<?php
/**
 * Send e-receipt to customer email.
 * POST JSON: { order_id, email? }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/receipt.php';

requireLoginApi(['kasir', 'owner']);
$shopId = requireShopIdApi();
requirePost();

$body = readJsonBody();
$orderId = (int) ($body['order_id'] ?? 0);
$email = trim((string) ($body['email'] ?? ''));
$lang = currentLang();

if ($orderId <= 0) {
    jsonError('Invalid order_id');
}

$result = sendOrderReceiptEmail($orderId, $shopId, $email !== '' ? $email : null, $lang);

if (!$result['ok']) {
    $err = (string) ($result['error'] ?? 'failed');
    $msg = match ($err) {
        'not_found' => t('receipt_not_found'),
        'not_paid' => t('receipt_not_paid'),
        'no_email' => t('receipt_no_email'),
        'send_failed' => t('receipt_send_failed'),
        default => t('receipt_send_failed'),
    };
    jsonError($msg, 400);
}

jsonResponse([
    'ok' => true,
    'email_sent' => true,
    'email_masked' => $result['email'] ?? '',
]);
