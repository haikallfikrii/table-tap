<?php
/**
 * Kasir print hub — all stations' new kitchen items for one cashier printer.
 *
 * Used when the shop has a single thermal printer at kasir: tickets are split
 * by station so staff can tear and walk them to dapur / western / minuman.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/shop.php';
require_once dirname(__DIR__, 2) . '/includes/stations.php';
require_once dirname(__DIR__, 2) . '/includes/menu_categories.php';

$user = requireLoginApi(['kasir', 'owner']);
$shopId = requireShopIdApi();
$shop = findShopById($shopId);
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'my';
$sinceId = max(0, (int) ($_GET['since_id'] ?? 0));

$printer = shopPrinterSettings($shop);
if (!$printer['kasir_print_hub']) {
    jsonResponse([
        'ok' => true,
        'enabled' => false,
        'max_id' => $sinceId,
        'new_item_ids' => [],
        'items' => [],
        'printer' => $printer,
    ]);
}

$pdo = db();
$selfPickup = shopFulfillment($shop) === 'self_pickup';
$statusList = $selfPickup
    ? "'menunggu', 'sedang_dimasak', 'siap'"
    : "'menunggu', 'sedang_dimasak'";

$catSelect = orderMenuCategoryKodColumnExists()
    ? 'COALESCE(oi.menu_category_kod_saat_order, mc.kod) AS menu_category_kod,
                COALESCE(oi.menu_category_nama_my_saat_order, mc.nama_my) AS menu_category_nama_my,
                COALESCE(oi.menu_category_nama_en_saat_order, mc.nama_en) AS menu_category_nama_en'
    : 'mc.kod AS menu_category_kod,
                mc.nama_my AS menu_category_nama_my,
                mc.nama_en AS menu_category_nama_en';

$stationSelect = orderStationColumnExists()
    ? 'oi.station_id_saat_order'
    : 'NULL AS station_id_saat_order';

$stmt = $pdo->prepare(
    "SELECT oi.id, oi.order_id, oi.qty, oi.catatan, oi.status_item,
            oi.nama_saat_order_my, oi.nama_saat_order_en, oi.kategori_saat_order,
            {$stationSelect},
            o.waktu_order, o.jenis_hidang, o.nama_pelanggan, o.status_bayar,
            o.payment_method, o.payment_proof_status, t.nomor_meja,
            {$catSelect}
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     INNER JOIN tables t ON t.id = o.table_id
     LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
     LEFT JOIN menu_categories mc ON mc.id = mi.menu_category_id
     WHERE o.shop_id = ?
       AND oi.status_item IN ($statusList)
       AND o.status_order != 'dibatalkan'
     ORDER BY
       FIELD(oi.status_item, 'menunggu', 'sedang_dimasak', 'siap'),
       o.waktu_order ASC,
       oi.id ASC"
);
$stmt->execute([$shopId]);
$items = $stmt->fetchAll();

$stationsById = [];
$stationsByKod = [];
foreach (shopStations($shopId, true) as $st) {
    $stationsById[(int) $st['id']] = $st;
    $stationsByKod[(string) $st['kod']] = $st;
}

$maxId = $sinceId;
$newIds = [];
$result = [];

foreach ($items as $it) {
    if (orderNeedsPaymentHold($it, $shop)) {
        continue;
    }
    $id = (int) $it['id'];
    if ($id > $maxId) {
        $maxId = $id;
    }
    if ($it['status_item'] === 'menunggu' && $id > $sinceId) {
        $newIds[] = $id;
    }

    $stationId = (int) ($it['station_id_saat_order'] ?? 0);
    $station = $stationsById[$stationId] ?? null;
    if (!$station) {
        $fallbackKod = (($it['kategori_saat_order'] ?? '') === 'minuman') ? 'minuman' : 'dapur';
        $station = $stationsByKod[$fallbackKod] ?? [
            'id' => 0,
            'kod' => $fallbackKod,
            'nama_my' => $fallbackKod === 'minuman' ? 'Minuman' : 'Dapur',
            'nama_en' => $fallbackKod === 'minuman' ? 'Drinks' : 'Kitchen',
        ];
        $stationId = (int) ($station['id'] ?? 0);
    }

    $label = stationLabel($station, $lang);
    $kod = (string) ($station['kod'] ?? 'dapur');

    $result[] = [
        'id' => $id,
        'order_id' => (int) $it['order_id'],
        'qty' => (int) $it['qty'],
        'catatan' => $it['catatan'],
        'status_item' => $it['status_item'],
        'nama' => $lang === 'en' ? $it['nama_saat_order_en'] : $it['nama_saat_order_my'],
        'station_id' => $stationId,
        'station_kod' => $kod,
        'ticket_group' => $kod !== '' ? $kod : 'default',
        'ticket_label' => $label !== '' ? $label : ($lang === 'en' ? 'STATION' : 'STESEN'),
        'nomor_meja' => $it['nomor_meja'],
        'waktu_order' => $it['waktu_order'],
        'jenis_hidang' => ($it['jenis_hidang'] ?? 'dine_in') === 'takeaway'
            ? 'takeaway'
            : (($it['jenis_hidang'] ?? '') === 'delivery' ? 'delivery' : 'dine_in'),
        'nama_pelanggan' => $it['nama_pelanggan'] ?? '',
    ];
}

jsonResponse([
    'ok' => true,
    'enabled' => true,
    'max_id' => $maxId,
    'new_item_ids' => $newIds,
    'items' => $result,
    'printer' => $printer,
]);
