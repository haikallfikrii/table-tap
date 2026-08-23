<?php
/**
 * AJAX: submit customer order
 * POST JSON table: { meja, token, ... }
 * POST JSON cafe:  { session, ... }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/shift.php';

requirePost();

$body = readJsonBody();
$sessionToken = trim((string) ($body['session'] ?? ''));
$nomorMeja = trim((string) ($body['meja'] ?? ''));
$token = trim((string) ($body['token'] ?? ''));
$items = $body['items'] ?? [];
$jenisHidang = (($body['jenis_hidang'] ?? '') === 'takeaway') ? 'takeaway' : 'dine_in';

$session = null;
$table = null;
$sessionId = null;
$guestName = (string) ($body['nama_pelanggan'] ?? '');

if ($sessionToken !== '') {
    $session = findSessionByToken($sessionToken);
    if (!$session || ($session['status'] ?? '') !== 'active') {
        jsonError('Session expired', 403);
    }
    $table = sessionAsTableContext($session);
    $sessionId = (int) $session['id'];
    if ($guestName === '') {
        $guestName = (string) ($session['nama_pelanggan'] ?? '');
    }
} elseif ($nomorMeja !== '' && $token !== '') {
    $table = findTableByAccess($nomorMeja, $token);
    if (!$table) {
        jsonError('Invalid table access', 403);
    }
} else {
    jsonError('Invalid access', 403);
}

$shopId = (int) $table['shop_id'];
$shop = findShopById($shopId);
if (!$shop || $shop['status'] !== 'aktif') {
    jsonError('Shop inactive', 403);
}
assertShopAcceptingOrders($shop);

$created = createShopOrder(
    $table,
    $shop,
    is_array($items) ? $items : [],
    $jenisHidang,
    $guestName,
    'qr',
    false,
    $sessionId,
    ($session !== null && trim((string) ($session['contact_email'] ?? '')) !== '')
        ? (string) $session['contact_email']
        : null
);
$orderId = $created['order_id'];
$guestToken = (string) ($created['guest_token'] ?? '');
$totals = $created['totals'];

if ($sessionToken !== '') {
    $redirect = cafeSessionTrackUrl($sessionToken, $orderId, $guestToken);
} else {
    $redirect = baseUrl(
        'public/confirmation.php?order=' . $orderId
        . '&meja=' . urlencode($nomorMeja)
        . '&token=' . urlencode($token)
        . ($guestToken !== '' ? '&gt=' . urlencode($guestToken) : '')
    );
}

jsonResponse([
    'ok' => true,
    'order_id' => $orderId,
    'subtotal' => $totals['subtotal'],
    'sst' => $totals['sst_jumlah'],
    'total' => $totals['total'],
    'redirect' => $redirect,
]);
