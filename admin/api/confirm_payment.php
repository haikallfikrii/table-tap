<?php
/**
 * Kasir confirms / rejects DuitNow proof, or marks COD cash received.
 * POST JSON: { order_id, action: confirm|reject|cod_received }
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
$action = (string) ($body['action'] ?? '');
$lang = currentLang();

if ($orderId <= 0 || !in_array($action, ['confirm', 'reject', 'cod_received'], true)) {
    jsonError('Invalid request');
}
if (!orderDeliveryColumnsExist()) {
    jsonError('Not available', 400);
}

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT id, status_bayar, payment_method, payment_proof_status, status_order
     FROM orders
     WHERE id = ? AND shop_id = ? AND status_order != 'dibatalkan'
     LIMIT 1"
);
$stmt->execute([$orderId, $shopId]);
$order = $stmt->fetch();
if (!$order) {
    jsonError('Order not found', 404);
}

$method = (string) ($order['payment_method'] ?? 'counter');
$already = ($order['status_bayar'] ?? '') === 'lunas';

if ($action === 'reject') {
    if ($method !== 'duitnow') {
        jsonError(t('proof_not_needed'), 400);
    }
    $pdo->prepare(
        "UPDATE orders SET payment_proof_status = 'rejected' WHERE id = ? AND shop_id = ?"
    )->execute([$orderId, $shopId]);
    jsonResponse(['ok' => true, 'order_id' => $orderId, 'payment_proof_status' => 'rejected']);
}

if ($action === 'cod_received' && $method !== 'cod') {
    jsonError('Not a COD order', 400);
}
if ($action === 'confirm' && $method !== 'duitnow') {
    jsonError(t('proof_not_needed'), 400);
}

if (!$already) {
    $proofStatus = $action === 'confirm' ? 'confirmed' : (($method === 'cod') ? 'confirmed' : 'none');
    $pdo->prepare(
        "UPDATE orders
         SET status_bayar = 'lunas',
             waktu_lunas = ?,
             payment_proof_status = ?,
             status_order = CASE WHEN status_order = 'menunggu' THEN 'diproses' ELSE status_order END
         WHERE id = ? AND shop_id = ?"
    )->execute([appNow(), $proofStatus, $orderId, $shopId]);
}

$receipt = fetchOrderReceipt($orderId, $shopId, $lang);

jsonResponse([
    'ok' => true,
    'order_id' => $orderId,
    'already' => $already,
    'status_bayar' => 'lunas',
    'receipt' => $receipt,
    'receipt_url' => orderReceiptUrl($orderId, true),
]);
