<?php
/**
 * Cancel / void an order — excluded from income & kitchen queues.
 * POST JSON: { order_id }
 * Notifies the owner dashboard when staff (e.g. kasir) cancels.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/shop.php';

requirePost();
$user = requireLoginApi(['kasir', 'owner']);

$shopId = requireShopIdApi();
$orderId = (int) (readJsonBody()['order_id'] ?? 0);
if ($orderId <= 0) {
    jsonError('Invalid order');
}

$pdo = db();
$info = $pdo->prepare(
    "SELECT o.id, o.status_order, o.total_harga, o.nama_pelanggan, o.jenis_hidang,
            t.nomor_meja
     FROM orders o
     INNER JOIN tables t ON t.id = o.table_id
     WHERE o.id = ? AND o.shop_id = ?
     LIMIT 1"
);
$info->execute([$orderId, $shopId]);
$order = $info->fetch();
if (!$order) {
    jsonError(t('order_not_found'), 404);
}

$wasCancelled = (($order['status_order'] ?? '') === 'dibatalkan');
if (!cancelShopOrder($orderId, $shopId)) {
    jsonError(t('order_not_found'), 404);
}

if (!$wasCancelled) {
    $byName = trim((string) (($user['nama'] ?? '') !== '' ? $user['nama'] : ($user['username'] ?? 'staff')));
    $role = (string) ($user['role'] ?? '');
    $meja = (string) ($order['nomor_meja'] ?? '-');
    $total = formatMoney((float) ($order['total_harga'] ?? 0));
    $lang = function_exists('currentLang') ? currentLang() : 'my';
    if ($lang === 'en') {
        $title = 'Order #' . $orderId . ' cancelled';
        $body = $byName . ' (' . $role . ') cancelled table ' . $meja . ' · ' . $total;
    } else {
        $title = 'Pesanan #' . $orderId . ' dibatalkan';
        $body = $byName . ' (' . $role . ') batalkan meja ' . $meja . ' · ' . $total;
    }
    createShopAlert($shopId, 'order_cancelled', $title, $body, [
        'order_id' => $orderId,
        'nomor_meja' => $meja,
        'total' => (float) ($order['total_harga'] ?? 0),
        'by' => $byName,
        'role' => $role,
    ]);
}

jsonResponse([
    'ok' => true,
    'order_id' => $orderId,
    'status_order' => 'dibatalkan',
]);
