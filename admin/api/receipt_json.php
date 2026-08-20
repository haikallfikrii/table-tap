<?php
/**
 * Receipt payload for silent Bluetooth print (paid orders only).
 * GET ?order=ID&lang=my|en
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/receipt.php';

requireLoginApi(['kasir', 'owner']);
$shopId = requireShopIdApi();

$orderId = (int) ($_GET['order'] ?? 0);
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : currentLang();
if ($orderId <= 0) {
    jsonError('Invalid order');
}

$receipt = fetchOrderReceipt($orderId, $shopId, $lang);
if (!$receipt) {
    jsonError('Order not found', 404);
}
if (($receipt['status_bayar'] ?? '') !== 'lunas') {
    jsonError('Receipt available after payment', 403);
}

jsonResponse([
    'ok' => true,
    'receipt' => $receipt,
]);
