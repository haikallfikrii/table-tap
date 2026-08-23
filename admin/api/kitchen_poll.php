<?php
/**
 * Kitchen poll — filter by station (legacy kategori= still works).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/shop.php';
require_once dirname(__DIR__, 2) . '/includes/stations.php';

$user = requireLoginApi(['dapur', 'minuman', 'owner']);
$shopId = requireShopIdApi();
$shop = findShopById($shopId);
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'my';
$sinceId = max(0, (int) ($_GET['since_id'] ?? 0));

$requestedId = (int) ($_GET['station_id'] ?? 0);
$kategori = ($_GET['kategori'] ?? '') === 'minuman' ? 'minuman' : 'makanan';
$fallbackKod = $requestedId > 0 ? 'dapur' : $kategori;
if ($requestedId <= 0 && $kategori === 'minuman') {
    $fallbackKod = 'minuman';
}

$station = resolveKitchenStation($user, $shopId, $requestedId > 0 ? $requestedId : null, $fallbackKod);
if (!$station || !userCanAccessStation($user, $station)) {
    jsonError('Forbidden', 403);
}

$pdo = db();
$selfPickup = shopFulfillment($shop) === 'self_pickup';
$statusList = $selfPickup
    ? "'menunggu', 'sedang_dimasak', 'siap'"
    : "'menunggu', 'sedang_dimasak'";

$stationId = (int) ($station['id'] ?? 0);
$useStationCol = orderStationColumnExists() && $stationId > 0;

if ($useStationCol) {
    $stmt = $pdo->prepare(
        "SELECT oi.id, oi.order_id, oi.qty, oi.catatan, oi.status_item,
                oi.nama_saat_order_my, oi.nama_saat_order_en, oi.kategori_saat_order,
                o.waktu_order, o.jenis_hidang, o.nama_pelanggan, o.status_bayar,
                o.payment_method, o.payment_proof_status, t.nomor_meja
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         INNER JOIN tables t ON t.id = o.table_id
         WHERE o.shop_id = ?
           AND oi.station_id_saat_order = ?
           AND oi.status_item IN ($statusList)
           AND o.status_order != 'dibatalkan'
         ORDER BY
           FIELD(oi.status_item, 'menunggu', 'sedang_dimasak', 'siap'),
           o.waktu_order ASC,
           oi.id ASC"
    );
    $stmt->execute([$shopId, $stationId]);
} else {
    $kat = (($station['kod'] ?? $kategori) === 'minuman') ? 'minuman' : 'makanan';
    $stmt = $pdo->prepare(
        "SELECT oi.id, oi.order_id, oi.qty, oi.catatan, oi.status_item,
                oi.nama_saat_order_my, oi.nama_saat_order_en, oi.kategori_saat_order,
                o.waktu_order, o.jenis_hidang, o.nama_pelanggan, o.status_bayar,
                o.payment_method, o.payment_proof_status, t.nomor_meja
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         INNER JOIN tables t ON t.id = o.table_id
         WHERE o.shop_id = ?
           AND oi.kategori_saat_order = ?
           AND oi.status_item IN ($statusList)
           AND o.status_order != 'dibatalkan'
         ORDER BY
           FIELD(oi.status_item, 'menunggu', 'sedang_dimasak', 'siap'),
           o.waktu_order ASC,
           oi.id ASC"
    );
    $stmt->execute([$shopId, $kat]);
}
$items = $stmt->fetchAll();

$maxId = $sinceId;
$newIds = [];
$result = [];
$pendingAlerts = 0;

foreach ($items as $it) {
    if (orderNeedsPaymentHold($it, $shop)) {
        continue;
    }
    $id = (int) $it['id'];
    if ($id > $maxId) {
        $maxId = $id;
    }
    if ($it['status_item'] === 'menunggu') {
        $pendingAlerts++;
        if ($id > $sinceId) {
            $newIds[] = $id;
        }
    }
    $result[] = [
        'id' => $id,
        'order_id' => (int) $it['order_id'],
        'qty' => (int) $it['qty'],
        'catatan' => $it['catatan'],
        'status_item' => $it['status_item'],
        'nama' => $lang === 'en' ? $it['nama_saat_order_en'] : $it['nama_saat_order_my'],
        'nomor_meja' => $it['nomor_meja'],
        'waktu_order' => $it['waktu_order'],
        'jenis_hidang' => ($it['jenis_hidang'] ?? 'dine_in') === 'takeaway'
            ? 'takeaway'
            : (($it['jenis_hidang'] ?? '') === 'delivery' ? 'delivery' : 'dine_in'),
        'nama_pelanggan' => $it['nama_pelanggan'] ?? '',
        'fulfillment' => $selfPickup ? 'self_pickup' : 'waiter',
    ];
}

jsonResponse([
    'ok' => true,
    'max_id' => $maxId,
    'new_item_ids' => $newIds,
    'pending_alerts' => $pendingAlerts,
    'sound' => shopSoundSettings($shop),
    'items' => $result,
]);
