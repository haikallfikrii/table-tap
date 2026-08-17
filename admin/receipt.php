<?php
/**
 * Printable order receipt (kasir / owner).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/i18n.php';
require_once dirname(__DIR__) . '/includes/receipt.php';

requireLogin(['kasir', 'owner']);

$shopId = requireShopId();
$lang = currentLang();
$orderId = max(0, (int) ($_GET['order'] ?? 0));
$autoPrint = isset($_GET['print']);

if ($orderId <= 0) {
    http_response_code(400);
    echo 'Invalid order';
    exit;
}

$receipt = fetchOrderReceipt($orderId, $shopId, $lang);
if (!$receipt) {
    http_response_code(404);
    echo $lang === 'en' ? 'Order not found' : 'Pesanan tidak dijumpai';
    exit;
}

if (($receipt['status_bayar'] ?? '') !== 'lunas') {
    http_response_code(403);
    echo $lang === 'en' ? 'Receipt available after payment' : 'Resit tersedia selepas bayaran';
    exit;
}

echo receiptHtml($receipt, true);

if ($autoPrint) {
    echo '<script>window.addEventListener("load",function(){window.print();});</script>';
}
