<?php
/**
 * Mark order as paid (lunas)
 * POST JSON: { order_id }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

requireLoginApi(['kasir', 'owner']);
requirePost();

$body = readJsonBody();
$orderId = (int) ($body['order_id'] ?? 0);
if ($orderId <= 0) {
    jsonError('Invalid order_id');
}

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT id, status_bayar FROM orders WHERE id = ? AND status_order != 'dibatalkan' LIMIT 1"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    jsonError('Order not found', 404);
}

if ($order['status_bayar'] === 'lunas') {
    jsonResponse(['ok' => true, 'already' => true]);
}

$upd = $pdo->prepare(
    "UPDATE orders
     SET status_bayar = 'lunas',
         status_order = CASE WHEN status_order = 'menunggu' THEN 'diproses' ELSE status_order END,
         waktu_lunas = NOW()
     WHERE id = ?"
);
$upd->execute([$orderId]);

jsonResponse(['ok' => true, 'order_id' => $orderId]);
