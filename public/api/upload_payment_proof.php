<?php
/**
 * Guest uploads DuitNow payment proof (image or PDF).
 * POST multipart: order_id, gt (guest_token), proof file
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requirePost();

$orderId = (int) ($_POST['order_id'] ?? 0);
$guestToken = trim((string) ($_POST['gt'] ?? ''));
$config = getConfig();

if ($orderId <= 0 || $guestToken === '' || !orderDeliveryColumnsExist()) {
    jsonError('Invalid request', 400);
}

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT o.id, o.shop_id, o.status_bayar, o.payment_method, o.payment_proof_status, o.guest_token
     FROM orders o
     WHERE o.id = ? AND o.status_order != 'dibatalkan'
     LIMIT 1"
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order || !hash_equals((string) ($order['guest_token'] ?? ''), $guestToken)) {
    jsonError('Order not found', 404);
}
if (($order['status_bayar'] ?? '') === 'lunas') {
    jsonError(t('already_paid'), 409);
}
if (($order['payment_method'] ?? '') !== 'duitnow') {
    jsonError(t('proof_not_needed'), 400);
}

$file = $_FILES['proof'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    jsonError(t('proof_upload_required'), 400);
}
$max = (int) ($config['upload_max_bytes'] ?? 2097152);
if (($file['size'] ?? 0) > max($max, 5 * 1024 * 1024)) {
    jsonError(t('proof_too_large'), 400);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if (!in_array($mime, $allowed, true)) {
    jsonError(t('proof_invalid_type'), 400);
}
$ext = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
    default => 'bin',
};

$dir = dirname(__DIR__, 2) . '/assets/uploads/proofs';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$name = 'proof_' . $orderId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$dest = $dir . '/' . $name;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    jsonError(t('proof_upload_failed'), 500);
}
$rel = 'assets/uploads/proofs/' . $name;

$pdo->prepare(
    "UPDATE orders
     SET payment_proof_url = ?, payment_proof_status = 'uploaded'
     WHERE id = ? AND shop_id = ?"
)->execute([$rel, $orderId, (int) $order['shop_id']]);

jsonResponse([
    'ok' => true,
    'payment_proof_status' => 'uploaded',
    'message' => t('proof_uploaded'),
]);
