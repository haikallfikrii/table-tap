<?php
/**
 * Big Tree Western — seed / refresh real menu + photos + 3 stations.
 *
 * Requires shop already created (slug big-tree-western).
 * Re-run safe: upserts menu by nama_my, deactivates old demo items not in catalog,
 * copies photos into storage/uploads/menu.
 *
 *   curl -s "https://tabletap.my/cron/seed_big_tree_western.php?key=CRON_SECRET"
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/stations.php';
require_once dirname(__DIR__) . '/includes/menu_categories.php';
require_once dirname(__DIR__) . '/includes/menu_addons.php';

$config = getConfig();
$key = (string) ($_GET['key'] ?? '');
if ($key === '' || !hash_equals((string) ($config['cron_secret'] ?? ''), $key)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Forbidden');
}

header('Content-Type: application/json; charset=utf-8');

@ini_set('max_execution_time', '300');
@ini_set('memory_limit', '512M');
@set_time_limit(300);

try {
$pdo = db();
$slug = 'big-tree-western';
$shopStmt = $pdo->prepare('SELECT * FROM shops WHERE slug = ? LIMIT 1');
$shopStmt->execute([$slug]);
$shop = $shopStmt->fetch();
if (!$shop) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Shop not found: ' . $slug], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$shopId = (int) $shop['id'];
$catalogFile = __DIR__ . '/data/big_tree_western_menu.php';
if (!is_file($catalogFile)) {
    throw new RuntimeException('Catalog missing: cron/data/big_tree_western_menu.php');
}
$catalog = require $catalogFile;
if (!is_array($catalog) || empty($catalog['items']) || empty($catalog['categories'])) {
    throw new RuntimeException('Catalog invalid or empty');
}
$out = [
    'ok' => true,
    'shop_id' => $shopId,
    'slug' => $slug,
    'created' => [
        'tables' => 0,
        'users' => 0,
        'categories' => 0,
        'items' => 0,
        'updated_items' => 0,
        'deactivated_items' => 0,
        'addons' => 0,
        'photos' => 0,
    ],
    'notes' => [],
];

ensureShopStations($shopId);
ensureShopMenuCategories($shopId);

$pdo->prepare(
    "UPDATE stations SET nama_my = 'Bar Minuman', nama_en = 'Drinks Bar' WHERE shop_id = ? AND kod = 'minuman'"
)->execute([$shopId]);
$pdo->prepare(
    "UPDATE stations SET nama_my = 'Dapur Panas', nama_en = 'Hot Kitchen' WHERE shop_id = ? AND kod = 'dapur'"
)->execute([$shopId]);

$western = shopStationByKod($shopId, 'western');
if (!$western) {
    $ord = $pdo->prepare('SELECT COALESCE(MAX(urutan), 0) + 1 FROM stations WHERE shop_id = ?');
    $ord->execute([$shopId]);
    $urutan = (int) $ord->fetchColumn();
    $pdo->prepare(
        "INSERT INTO stations (shop_id, kod, nama_my, nama_en, is_system, urutan, is_active)
         VALUES (?, 'western', 'Western', 'Western', 0, ?, 1)"
    )->execute([$shopId, $urutan]);
    $western = shopStationByKod($shopId, 'western');
    $out['notes'][] = 'created station: western';
} else {
    $pdo->prepare(
        "UPDATE stations SET nama_my = 'Western', nama_en = 'Western', is_active = 1 WHERE id = ? AND shop_id = ?"
    )->execute([(int) $western['id'], $shopId]);
}

$dapur = shopStationByKod($shopId, 'dapur');
$minuman = shopStationByKod($shopId, 'minuman');
$western = shopStationByKod($shopId, 'western');
$dapurId = $dapur ? (int) $dapur['id'] : null;
$minumanId = $minuman ? (int) $minuman['id'] : null;
$westernId = $western ? (int) $western['id'] : null;

$hubCol = $pdo->query("SHOW COLUMNS FROM shops LIKE 'kasir_print_hub'")->fetch();
if ($hubCol) {
    $pdo->prepare(
        'UPDATE shops SET kasir_print_hub = 1, kasir_open_drawer = 1, kasir_print_on_paid = 1 WHERE id = ?'
    )->execute([$shopId]);
    $out['notes'][] = 'kasir_print_hub ON (all station slips → kasir printer, separate per station) + drawer + print on paid';
}

$insTable = $pdo->prepare(
    'INSERT INTO tables (shop_id, nomor_meja, token_akses, status) VALUES (?, ?, ?, ?)'
);
$chkTable = $pdo->prepare('SELECT id FROM tables WHERE shop_id = ? AND nomor_meja = ? LIMIT 1');
$tableTarget = 25;
for ($i = 1; $i <= $tableTarget; $i++) {
    $nomor = (string) $i;
    $chkTable->execute([$shopId, $nomor]);
    if ($chkTable->fetch()) {
        continue;
    }
    $insTable->execute([$shopId, $nomor, generateToken(24), 'aktif']);
    $out['created']['tables']++;
}

$hash = password_hash('Demo1234!', PASSWORD_DEFAULT);
$staff = [
    ['kasirbigtree', 'kasir', 'Kasir Big Tree', null],
    ['dapurbigtree', 'dapur', 'Dapur Panas Big Tree', $dapurId],
    ['westernbigtree', 'dapur', 'Western Big Tree', $westernId],
    ['minumanbigtree', 'minuman', 'Bar Minuman Big Tree', $minumanId],
    ['waiterbigtree', 'waiter', 'Waiter Big Tree', null],
];
$chkUser = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
$hasStationCol = userStationColumnExists();
foreach ($staff as [$username, $role, $nama, $stationId]) {
    $chkUser->execute([$username]);
    $existingUser = $chkUser->fetch();
    if ($existingUser) {
        if ($hasStationCol && $stationId) {
            $pdo->prepare('UPDATE users SET station_id = ?, nama_paparan = ? WHERE id = ?')
                ->execute([$stationId, $nama, (int) $existingUser['id']]);
        }
        continue;
    }
    if ($hasStationCol) {
        $pdo->prepare(
            'INSERT INTO users (shop_id, username, password_hash, role, station_id, nama_paparan, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        )->execute([$shopId, $username, $hash, $role, $stationId, $nama]);
    } else {
        $pdo->prepare(
            'INSERT INTO users (shop_id, username, password_hash, role, nama_paparan, is_active)
             VALUES (?, ?, ?, ?, ?, 1)'
        )->execute([$shopId, $username, $hash, $role, $nama]);
    }
    $out['created']['users']++;
}

$catIds = [];
foreach ($catalog['categories'] as [$kod, $my, $en, $kind, $urutan]) {
    $existing = menuCategoryByKod($shopId, $kod);
    if ($existing) {
        $pdo->prepare(
            'UPDATE menu_categories SET nama_my = ?, nama_en = ?, kind = ?, urutan = ?, is_active = 1 WHERE id = ? AND shop_id = ?'
        )->execute([$my, $en, $kind, $urutan, (int) $existing['id'], $shopId]);
        $catIds[$kod] = (int) $existing['id'];
        continue;
    }
    $pdo->prepare(
        'INSERT INTO menu_categories (shop_id, kod, nama_my, nama_en, kind, is_system, urutan, is_active)
         VALUES (?, ?, ?, ?, ?, 0, ?, 1)'
    )->execute([$shopId, $kod, $my, $en, $kind, $urutan]);
    $catIds[$kod] = (int) $pdo->lastInsertId();
    $out['created']['categories']++;
}

// Copy real photos into storage/uploads/menu
$photoDir = dirname(__DIR__) . '/assets/img/demo/big-tree-western/real';
$uploadDir = storageRoot() . '/uploads/menu';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$copiedPhotos = [];
foreach (glob($photoDir . '/*.jpg') ?: [] as $src) {
    $slugKey = pathinfo($src, PATHINFO_FILENAME);
    $destName = 'bigtree-' . $slugKey . '-' . substr(sha1($slugKey . $shopId), 0, 8) . '.jpg';
    $dest = $uploadDir . '/' . $destName;
    if (!is_file($dest)) {
        if (!@copy($src, $dest)) {
            $out['notes'][] = 'copy failed: ' . $slugKey;
            continue;
        }
        $out['created']['photos']++;
    }
    $copiedPhotos[$slugKey] = storageUploadRel('menu', $destName);
}

$addonPresets = [
    'drink' => [
        ['pilihan', 'Normal manis', 'Regular sugar', 0.0],
        ['pilihan', 'Kurang manis', 'Less sugar', 0.0],
        ['pilihan', 'Tanpa gula', 'No sugar', 0.0],
    ],
    'grill_fried' => [
        ['pilihan', 'Grill', 'Grill', 0.0],
        ['pilihan', 'Fried', 'Fried', 0.0],
    ],
    'pasta' => [
        ['pilihan', 'Spaghetti', 'Spaghetti', 0.0],
        ['pilihan', 'Macaroni', 'Macaroni', 0.0],
    ],
    'noodle' => [
        ['pilihan', 'Maggi', 'Maggi', 0.0],
        ['pilihan', 'Mee', 'Mee', 0.0],
        ['pilihan', 'Kuew Teow', 'Kuew Teow', 0.0],
        ['pilihan', 'Bihun', 'Bihun', 0.0],
    ],
    'lauk_style' => [
        ['pilihan', 'Merah', 'Red sauce', 0.0],
        ['pilihan', 'Paprik', 'Paprik', 0.0],
        ['pilihan', 'Kicap', 'Soy sauce', 0.0],
        ['pilihan', 'Pedas', 'Spicy', 0.0],
        ['pilihan', 'Kunyit', 'Turmeric', 0.0],
        ['pilihan', 'Halia', 'Ginger', 0.0],
        ['pilihan', 'Tiram', 'Oyster sauce', 0.0],
        ['pilihan', 'Asam', 'Tamarind', 0.0],
    ],
    'roti_spread' => [
        ['pilihan', 'Planta gula', 'Butter sugar', 0.0],
        ['pilihan', 'Susu pekat', 'Condensed milk', 0.0],
        ['pilihan', 'Strawberry', 'Strawberry', 0.0],
        ['pilihan', 'Madu', 'Honey', 0.0],
    ],
    'kuah_style' => [
        ['pilihan', 'Kuah', 'Soup', 0.0],
        ['pilihan', 'Hailam', 'Hailam', 0.0],
        ['pilihan', 'Bandung', 'Bandung', 0.0],
        ['pilihan', 'Tomyam', 'Tom Yam', 0.0],
        ['pilihan', 'Ladna', 'Ladna', 0.0],
        ['pilihan', 'Maggi', 'Maggi', 0.0],
        ['pilihan', 'Mee', 'Mee', 0.0],
        ['pilihan', 'Kuew Teow', 'Kuew Teow', 0.0],
        ['pilihan', 'Bihun', 'Bihun', 0.0],
    ],
];

$stationIdForKod = static function (string $kod) use ($dapurId, $minumanId, $westernId): ?int {
    if ($kod === 'minuman') {
        return $minumanId;
    }
    if ($kod === 'western') {
        return $westernId ?: $dapurId;
    }
    return $dapurId;
};

$chkItem = $pdo->prepare('SELECT id FROM menu_items WHERE shop_id = ? AND nama_my = ? LIMIT 1');
$hasCatCol = menuCategoryColumnExists();
$hasStationColMenu = menuStationColumnExists();
$keepNames = [];

foreach ($catalog['items'] as $i => $row) {
    [$catKod, $namaMy, $namaEn, $desc, $harga, $photoKey, $stationKod, $addonKey] = $row;
    $keepNames[$namaMy] = true;
    $chkItem->execute([$shopId, $namaMy]);
    $existingId = $chkItem->fetchColumn();
    $stationId = $stationIdForKod((string) $stationKod);
    $kategori = $stationKod === 'minuman' ? 'minuman' : 'makanan';
    $catId = $catIds[$catKod] ?? null;
    $foto = ($photoKey && isset($copiedPhotos[$photoKey])) ? $copiedPhotos[$photoKey] : null;
    $urutan = $i + 1;
    $descMy = $desc !== '' ? $desc : $namaMy;
    $descEn = $desc !== '' ? $desc : $namaEn;

    if ($existingId) {
        $itemId = (int) $existingId;
        if ($hasCatCol && $hasStationColMenu) {
            $pdo->prepare(
                'UPDATE menu_items SET nama_en = ?, deskripsi_my = ?, deskripsi_en = ?, harga = ?, kategori = ?,
                        menu_category_id = ?, station_id = ?, foto_url = COALESCE(?, foto_url), status_stok = ?,
                        urutan = ?, is_active = 1
                 WHERE id = ? AND shop_id = ?'
            )->execute([
                $namaEn, $descMy, $descEn, $harga, $kategori, $catId, $stationId, $foto, 'tersedia', $urutan,
                $itemId, $shopId,
            ]);
        } elseif ($hasStationColMenu) {
            $pdo->prepare(
                'UPDATE menu_items SET nama_en = ?, deskripsi_my = ?, deskripsi_en = ?, harga = ?, kategori = ?,
                        station_id = ?, foto_url = COALESCE(?, foto_url), status_stok = ?, urutan = ?, is_active = 1
                 WHERE id = ? AND shop_id = ?'
            )->execute([
                $namaEn, $descMy, $descEn, $harga, $kategori, $stationId, $foto, 'tersedia', $urutan,
                $itemId, $shopId,
            ]);
        } else {
            $pdo->prepare(
                'UPDATE menu_items SET nama_en = ?, deskripsi_my = ?, deskripsi_en = ?, harga = ?, kategori = ?,
                        foto_url = COALESCE(?, foto_url), status_stok = ?, urutan = ?, is_active = 1
                 WHERE id = ? AND shop_id = ?'
            )->execute([
                $namaEn, $descMy, $descEn, $harga, $kategori, $foto, 'tersedia', $urutan, $itemId, $shopId,
            ]);
        }
        $out['created']['updated_items']++;
    } else {
        if ($hasCatCol && $hasStationColMenu) {
            $pdo->prepare(
                'INSERT INTO menu_items
                 (shop_id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, menu_category_id, station_id, foto_url, status_stok, urutan, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
            )->execute([
                $shopId, $namaMy, $namaEn, $descMy, $descEn, $harga, $kategori, $catId, $stationId, $foto, 'tersedia', $urutan,
            ]);
        } elseif ($hasStationColMenu) {
            $pdo->prepare(
                'INSERT INTO menu_items
                 (shop_id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, station_id, foto_url, status_stok, urutan, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
            )->execute([
                $shopId, $namaMy, $namaEn, $descMy, $descEn, $harga, $kategori, $stationId, $foto, 'tersedia', $urutan,
            ]);
        } else {
            $pdo->prepare(
                'INSERT INTO menu_items
                 (shop_id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, foto_url, status_stok, urutan, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
            )->execute([
                $shopId, $namaMy, $namaEn, $descMy, $descEn, $harga, $kategori, $foto, 'tersedia', $urutan,
            ]);
        }
        $itemId = (int) $pdo->lastInsertId();
        $out['created']['items']++;
    }

    if ($addonKey && menuAddonsTableExists() && $itemId > 0 && isset($addonPresets[$addonKey])) {
        $pdo->prepare('DELETE FROM menu_addons WHERE shop_id = ? AND menu_item_id = ?')
            ->execute([$shopId, $itemId]);
        $insAddon = $pdo->prepare(
            'INSERT INTO menu_addons (shop_id, menu_item_id, nama_my, nama_en, harga_delta, jenis, urutan, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );
        foreach ($addonPresets[$addonKey] as $ai => [$jenis, $aMy, $aEn, $delta]) {
            $insAddon->execute([$shopId, $itemId, $aMy, $aEn, $delta, $jenis, $ai]);
            $out['created']['addons']++;
        }
    }
}

// Deactivate old demo / leftover items not in the real catalog
$allItems = $pdo->prepare('SELECT id, nama_my FROM menu_items WHERE shop_id = ?');
$allItems->execute([$shopId]);
foreach ($allItems->fetchAll() as $row) {
    if (!isset($keepNames[$row['nama_my']])) {
        $pdo->prepare('UPDATE menu_items SET is_active = 0 WHERE id = ? AND shop_id = ?')
            ->execute([(int) $row['id'], $shopId]);
        $out['created']['deactivated_items']++;
    }
}

$limitCol = $pdo->query("SHOW COLUMNS FROM shops LIKE 'order_burst_max'")->fetch();
if ($limitCol) {
    $pdo->prepare(
        'UPDATE shops SET order_burst_seconds = 120, order_burst_max = 80,
                cart_max_qty_per_item = 99, cart_max_distinct_items = 150, cart_max_total_qty = 400
         WHERE id = ?'
    )->execute([$shopId]);
}

$out['counts'] = [
    'catalog_items' => count($catalog['items']),
    'photos_available' => count($copiedPhotos),
    'categories' => count($catIds),
];
$out['login'] = [
    'owner' => ['username' => 'ownerbigtree', 'password' => 'BigtreeDemo2026!'],
    'kasir' => ['username' => 'kasirbigtree', 'password' => 'Demo1234!'],
    'dapur' => ['username' => 'dapurbigtree', 'password' => 'Demo1234!'],
    'western' => ['username' => 'westernbigtree', 'password' => 'Demo1234!'],
    'minuman' => ['username' => 'minumanbigtree', 'password' => 'Demo1234!'],
    'waiter' => ['username' => 'waiterbigtree', 'password' => 'Demo1234!'],
];
$out['stations'] = [
    'dapur' => 'Dapur Panas',
    'western' => 'Western',
    'minuman' => 'Bar Minuman',
];

$tableRows = $pdo->prepare(
    'SELECT nomor_meja, token_akses FROM tables WHERE shop_id = ? AND status = ? ORDER BY CAST(nomor_meja AS UNSIGNED), nomor_meja'
);
$tableRows->execute([$shopId, 'aktif']);
$out['tables'] = [];
foreach ($tableRows->fetchAll() as $tr) {
    $nomor = (string) $tr['nomor_meja'];
    $token = (string) $tr['token_akses'];
    $out['tables'][$nomor] = [
        'token' => $token,
        'url' => 'https://tabletap.my/public/order.php?meja=' . rawurlencode($nomor) . '&token=' . rawurlencode($token),
    ];
}
$out['counts']['tables_active'] = count($out['tables']);

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
