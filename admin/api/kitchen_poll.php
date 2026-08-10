<?php
/**
 * Kitchen/drinks polling — shop-scoped, filter by kategori
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';

$kategori = ($_GET['kategori'] ?? '') === 'minuman' ? 'minuman' : 'makanan';

if ($kategori === 'makanan') {
    requireLoginApi(['dapur', 'owner']);
} else {
    requireLoginApi(['minuman', 'owner']);
}

$shopId = requireShopIdApi();
$sinceId = max(0, (int) ($_GET['since_id'] ?? 0));
$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'my';

$pdo = db();

$stmt = $pdo->prepare(
    "SELECT oi.id, oi.order_id, oi.qty, oi.catatan, oi.status_item,
            oi.nama_saat_order_my, oi.nama_saat_order_en, oi.kategori_saat_order,
            o.waktu_order, t.nomor_meja
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     INNER JOIN tables t ON t.id = o.table_id
     WHERE o.shop_id = ?
       AND oi.kategori_saat_order = ?
       AND oi.status_item IN ('menunggu', 'sedang_dimasak')
       AND o.status_order != 'dibatalkan'
     ORDER BY
       FIELD(oi.status_item, 'menunggu', 'sedang_dimasak'),
       o.waktu_order ASC,
       oi.id ASC"
);
$stmt->execute([$shopId, $kategori]);
$items = $stmt->fetchAll();

$maxId = $sinceId;
$newIds = [];
$result = [];

foreach ($items as $it) {
    $id = (int) $it['id'];
    if ($id > $maxId) {
        $maxId = $id;
    }
    if ($id > $sinceId && $it['status_item'] === 'menunggu') {
        $newIds[] = $id;
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
    ];
}

jsonResponse([
    'ok' => true,
    'max_id' => $maxId,
    'new_item_ids' => $newIds,
    'items' => $result,
]);
