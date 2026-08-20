<?php
/**
 * Waiter polling — items ready (siap) or picked up (diambil)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/stations.php';

requireLoginApi(['waiter', 'owner']);
$shopId = requireShopIdApi();
$sinceId = max(0, (int) ($_GET['since_id'] ?? 0));
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'my';

$pdo = db();
$shop = findShopById($shopId);
if (shopFulfillment($shop) === 'self_pickup') {
    jsonResponse([
        'ok' => true,
        'max_id' => $sinceId,
        'new_item_ids' => [],
        'pending_alerts' => 0,
        'sound' => shopSoundSettings($shop),
        'items' => [],
    ]);
}

$stationCol = orderStationColumnExists() ? ', oi.station_id_saat_order' : '';
$stmt = $pdo->prepare(
    "SELECT oi.id, oi.order_id, oi.qty, oi.catatan, oi.status_item,
            oi.nama_saat_order_my, oi.nama_saat_order_en, oi.kategori_saat_order
            {$stationCol},
            o.waktu_order, o.jenis_hidang, o.nama_pelanggan, t.nomor_meja
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     INNER JOIN tables t ON t.id = o.table_id
     WHERE o.shop_id = ?
       AND oi.status_item IN ('siap', 'diambil')
       AND o.status_order != 'dibatalkan'
     ORDER BY
       FIELD(oi.status_item, 'siap', 'diambil'),
       o.waktu_order ASC,
       oi.id ASC"
);
$stmt->execute([$shopId]);
$items = $stmt->fetchAll();

$stationNames = [];
foreach (shopStations($shopId, false) as $st) {
    $stationNames[(int) $st['id']] = stationLabel($st, $lang);
}

$maxId = $sinceId;
$newIds = [];
$result = [];
$pendingAlerts = 0;

foreach ($items as $it) {
    $id = (int) $it['id'];
    if ($id > $maxId) {
        $maxId = $id;
    }
    if ($it['status_item'] === 'siap') {
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
        'kategori' => $it['kategori_saat_order'],
        'station' => $stationNames[(int) ($it['station_id_saat_order'] ?? 0)]
            ?? (($it['kategori_saat_order'] ?? '') === 'minuman'
                ? (t('minuman_title'))
                : (t('dapur_title'))),
        'nama' => $lang === 'en' ? $it['nama_saat_order_en'] : $it['nama_saat_order_my'],
        'nomor_meja' => $it['nomor_meja'],
        'waktu_order' => $it['waktu_order'],
        'jenis_hidang' => ($it['jenis_hidang'] ?? 'dine_in') === 'takeaway' ? 'takeaway' : 'dine_in',
        'nama_pelanggan' => $it['nama_pelanggan'] ?? '',
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
