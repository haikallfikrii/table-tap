<?php
/**
 * Mark order as paid (lunas)
 * POST JSON: { order_id, send_receipt?, email? }
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
$sendReceipt = !empty($body['send_receipt']);
$email = trim((string) ($body['email'] ?? ''));
$lang = currentLang();

if ($orderId <= 0) {
    jsonError('Invalid order_id');
}

$pdo = db();
$emailCol = orderCustomerEmailColumnExists() ? ', customer_email' : '';
$stmt = $pdo->prepare(
    "SELECT id, status_bayar{$emailCol}
     FROM orders
     WHERE id = ? AND shop_id = ? AND status_order != 'dibatalkan'
     LIMIT 1"
);
$stmt->execute([$orderId, $shopId]);
$order = $stmt->fetch();

if (!$order) {
    jsonError('Order not found', 404);
}

$alreadyPaid = $order['status_bayar'] === 'lunas';

if (!$alreadyPaid) {
    $updSql = "UPDATE orders
         SET status_bayar = 'lunas',
             status_order = CASE WHEN status_order = 'menunggu' THEN 'diproses' ELSE status_order END,
             waktu_lunas = ?";
    $params = [appNow()];
    if (orderDeliveryColumnsExist()) {
        $updSql .= ", payment_proof_status = 'confirmed'";
    }
    $updSql .= ' WHERE id = ? AND shop_id = ?';
    $params[] = $orderId;
    $params[] = $shopId;
    $pdo->prepare($updSql)->execute($params);
}

if ($email !== '') {
    saveOrderCustomerEmail($orderId, $email);
}

$emailSent = false;
$emailMasked = '';
$emailError = '';

if ($sendReceipt) {
    $mailResult = sendOrderReceiptEmail(
        $orderId,
        $shopId,
        $email !== '' ? $email : null,
        $lang
    );
    if ($mailResult['ok']) {
        $emailSent = true;
        $emailMasked = (string) ($mailResult['email'] ?? '');
    } else {
        $emailError = (string) ($mailResult['error'] ?? 'send_failed');
    }
}

$receipt = fetchOrderReceipt($orderId, $shopId, $lang);

jsonResponse([
    'ok' => true,
    'already' => $alreadyPaid,
    'order_id' => $orderId,
    'receipt_url' => orderReceiptUrl($orderId, true),
    'receipt' => $receipt,
    'email_sent' => $emailSent,
    'email_masked' => $emailMasked,
    'email_error' => $emailError !== '' ? $emailError : null,
]);
