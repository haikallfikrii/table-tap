<?php
/**
 * AJAX: submit customer order
 * POST JSON: { meja, token, jenis_hidang, items: [{ menu_item_id, qty, catatan }] }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requirePost();

$body = readJsonBody();
$nomorMeja = trim((string) ($body['meja'] ?? ''));
$token = trim((string) ($body['token'] ?? ''));
$items = $body['items'] ?? [];
$jenisHidang = (($body['jenis_hidang'] ?? '') === 'takeaway') ? 'takeaway' : 'dine_in';

if ($nomorMeja === '' || $token === '') {
    jsonError('Invalid table access', 403);
}

$table = findTableByAccess($nomorMeja, $token);
if (!$table) {
    jsonError('Invalid table access', 403);
}

$shopId = (int) $table['shop_id'];
$shop = findShopById($shopId);
if (!$shop || $shop['status'] !== 'aktif') {
    jsonError('Shop inactive', 403);
}

$created = createShopOrder(
    $table,
    $shop,
    is_array($items) ? $items : [],
    $jenisHidang,
    (string) ($body['nama_pelanggan'] ?? ''),
    'qr',
    false
);
$orderId = $created['order_id'];
$guestToken = (string) ($created['guest_token'] ?? '');
$totals = $created['totals'];
$redirect = baseUrl(
    'public/confirmation.php?order=' . $orderId
    . '&meja=' . urlencode($nomorMeja)
    . '&token=' . urlencode($token)
    . ($guestToken !== '' ? '&gt=' . urlencode($guestToken) : '')
);

jsonResponse([
    'ok' => true,
    'order_id' => $orderId,
    'subtotal' => $totals['subtotal'],
    'sst' => $totals['sst_jumlah'],
    'total' => $totals['total'],
    'redirect' => $redirect,
]);
