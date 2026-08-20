<?php
/**
 * Split unpaid order by selected items → new paid bill + residual unpaid bill.
 * POST JSON: { order_id, item_ids: number[], nama_pelanggan? }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/billing.php';

requireLoginApi(['kasir', 'owner']);
$shopId = requireShopIdApi();
requirePost();

$body = readJsonBody();
$orderId = (int) ($body['order_id'] ?? 0);
$itemIds = $body['item_ids'] ?? [];
if (!is_array($itemIds)) {
    $itemIds = [];
}
$guest = isset($body['nama_pelanggan']) ? trim((string) $body['nama_pelanggan']) : null;
$lang = currentLang();

$result = splitOrderBill($shopId, $orderId, $itemIds, $lang, $guest);
if (!$result['ok']) {
    $map = [
        'invalid_items' => [t('split_invalid_items'), 400],
        'order_not_found' => [t('order_not_found'), 404],
        'already_paid' => [t('split_already_paid'), 409],
        'select_partial' => [t('split_select_partial'), 400],
        'shop_not_found' => [t('order_not_found'), 404],
        'split_failed' => [t('split_failed'), 500],
    ];
    $err = (string) ($result['error'] ?? 'split_failed');
    $info = $map[$err] ?? [t('split_failed'), 400];
    jsonError($info[0], $info[1]);
}

jsonResponse([
    'ok' => true,
    'source_order_id' => (int) $result['source_order_id'],
    'paid_order_id' => (int) $result['paid_order_id'],
    'receipt_url' => (string) ($result['receipt_url'] ?? ''),
    'receipt' => $result['receipt'] ?? null,
]);
