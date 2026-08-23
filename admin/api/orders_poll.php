<?php
/**
 * Polling endpoint for kasir dashboard (shop-scoped).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/shop.php';
require_once dirname(__DIR__, 2) . '/includes/verification.php';
require_once dirname(__DIR__, 2) . '/includes/delivery.php';

$user = requireLoginApi(['kasir', 'owner']);
$shopId = requireShopIdApi();
$sinceId = max(0, (int) ($_GET['since_id'] ?? 0));
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'my';

$pdo = db();
$shop = findShopById($shopId);

$emailCol = orderCustomerEmailColumnExists() ? ', o.customer_email' : '';
$payCols = orderDeliveryColumnsExist()
    ? ', o.phone, o.alamat, o.payment_method, o.payment_proof_url, o.payment_proof_status'
    : '';
$stmt = $pdo->prepare(
    "SELECT o.id, o.table_id, o.waktu_order, o.status_order, o.status_bayar, o.jenis_hidang,
            o.nama_pelanggan, o.pickup_alert, o.sumber_order, o.subtotal, o.sst_rate, o.sst_jumlah, o.total_harga{$emailCol}{$payCols},
            t.nomor_meja
     FROM orders o
     INNER JOIN tables t ON t.id = o.table_id
     WHERE o.shop_id = ?
       AND o.status_order != 'dibatalkan'
       AND (
         o.status_bayar = 'belum_bayar'
         OR o.status_order IN ('menunggu', 'diproses')
         OR (
           o.status_bayar = 'lunas'
           AND o.waktu_lunas >= DATE_SUB(NOW(), INTERVAL 4 HOUR)
         )
       )
     ORDER BY o.waktu_order DESC, o.id DESC"
);
$stmt->execute([$shopId]);
$orders = $stmt->fetchAll();

$orderIds = array_map(static fn($o) => (int) $o['id'], $orders);
$itemsByOrder = [];

if ($orderIds !== []) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = $pdo->prepare(
        "SELECT id, order_id, qty, catatan, status_item,
                harga_saat_order, nama_saat_order_my, nama_saat_order_en, kategori_saat_order
         FROM order_items
         WHERE order_id IN ($placeholders)
         ORDER BY id ASC"
    );
    $itemStmt->execute($orderIds);
    foreach ($itemStmt->fetchAll() as $item) {
        $oid = (int) $item['order_id'];
        $item['nama'] = $lang === 'en' ? $item['nama_saat_order_en'] : $item['nama_saat_order_my'];
        $itemsByOrder[$oid][] = $item;
    }
}

$maxId = $sinceId;
$newIds = [];
$resultOrders = [];

foreach ($orders as $o) {
    $id = (int) $o['id'];
    if ($id > $maxId) {
        $maxId = $id;
    }
    if ($id > $sinceId) {
        $newIds[] = $id;
    }
    $items = $itemsByOrder[$id] ?? [];
    $hasReady = false;
    foreach ($items as $it) {
        if (in_array((string) $it['status_item'], ['siap', 'diambil'], true)) {
            $hasReady = true;
            break;
        }
    }
    $customerEmail = trim((string) ($o['customer_email'] ?? ''));
    $row = [
        'id' => $id,
        'table_id' => (int) $o['table_id'],
        'nomor_meja' => $o['nomor_meja'],
        'waktu_order' => $o['waktu_order'],
        'status_order' => $o['status_order'],
        'status_bayar' => $o['status_bayar'],
        'jenis_hidang' => ($o['jenis_hidang'] ?? 'dine_in') === 'takeaway'
            ? 'takeaway'
            : ((($o['jenis_hidang'] ?? '') === 'delivery') ? 'delivery' : 'dine_in'),
        'nama_pelanggan' => $o['nama_pelanggan'] ?? '',
        'phone' => (string) ($o['phone'] ?? ''),
        'alamat' => (string) ($o['alamat'] ?? ''),
        'payment_method' => (string) ($o['payment_method'] ?? 'counter'),
        'payment_proof_url' => uploadUrl((string) ($o['payment_proof_url'] ?? '')),
        'payment_proof_status' => (string) ($o['payment_proof_status'] ?? 'none'),
        'customer_email_masked' => $customerEmail !== '' ? maskEmail($customerEmail) : '',
        'customer_email' => $customerEmail,
        'has_customer_email' => $customerEmail !== '',
        'sumber_order' => ($o['sumber_order'] ?? 'qr') === 'staf' ? 'staf' : 'qr',
        'pickup_alert' => (int) ($o['pickup_alert'] ?? 1) === 1,
        'has_ready' => $hasReady,
        'subtotal' => (float) $o['subtotal'],
        'sst_rate' => (float) $o['sst_rate'],
        'sst_jumlah' => (float) $o['sst_jumlah'],
        'total_harga' => (float) $o['total_harga'],
        'items' => $items,
    ];
    if ($row['jenis_hidang'] === 'delivery') {
        $row['payment_state'] = deliveryPaymentState($row);
        $row['needs_payment_attention'] = deliveryNeedsKasirAttention($row);
    }
    $resultOrders[] = $row;
}

$deliveryOrders = [];
$tableOrders = [];
foreach ($resultOrders as $o) {
    if ($o['jenis_hidang'] === 'delivery') {
        $deliveryOrders[] = $o;
    } else {
        $tableOrders[] = $o;
    }
}

$newDeliveryIds = [];
foreach ($deliveryOrders as $o) {
    if (in_array((int) $o['id'], $newIds, true)) {
        $newDeliveryIds[] = (int) $o['id'];
    }
}

$byTable = [];
foreach ($tableOrders as $o) {
    $key = $o['nomor_meja'];
    if (!isset($byTable[$key])) {
        $byTable[$key] = [
            'nomor_meja' => $key,
            'orders' => [],
            'table_total' => 0.0,
            'has_unpaid' => false,
        ];
    }
    $byTable[$key]['orders'][] = $o;
    if ($o['status_bayar'] === 'belum_bayar') {
        $byTable[$key]['table_total'] += $o['total_harga'];
        $byTable[$key]['has_unpaid'] = true;
    }
}

foreach ($byTable as &$tbl) {
    usort($tbl['orders'], static fn(array $a, array $b): int => $b['id'] <=> $a['id']);
}
unset($tbl);

uksort($byTable, static function (string $a, string $b) use ($byTable): int {
    $maxA = 0;
    $maxB = 0;
    foreach ($byTable[$a]['orders'] as $o) {
        $maxA = max($maxA, (int) $o['id']);
    }
    foreach ($byTable[$b]['orders'] as $o) {
        $maxB = max($maxB, (int) $o['id']);
    }
    return $maxB <=> $maxA;
});

$grandTotal = 0.0;
$unpaidCount = 0;
$deliveryNeedsAction = 0;
foreach ($tableOrders as $o) {
    if ($o['status_bayar'] === 'belum_bayar') {
        $grandTotal += $o['total_harga'];
        $unpaidCount++;
    }
}
foreach ($deliveryOrders as $o) {
    if (!empty($o['needs_payment_attention'])) {
        $deliveryNeedsAction++;
    }
}

jsonResponse([
    'ok' => true,
    'server_time' => date('c'),
    'max_id' => $maxId,
    'new_order_ids' => $newIds,
    'new_delivery_ids' => $newDeliveryIds,
    'fulfillment' => shopFulfillment($shop),
    'sound' => shopSoundSettings($shop),
    'stats' => [
        'active_orders' => count($tableOrders),
        'unpaid_orders' => $unpaidCount,
        'grand_total' => round($grandTotal, 2),
        'delivery_active' => count($deliveryOrders),
        'delivery_needs_action' => $deliveryNeedsAction,
    ],
    'orders' => $resultOrders,
    'delivery_orders' => $deliveryOrders,
    'tables' => array_values($byTable),
]);
