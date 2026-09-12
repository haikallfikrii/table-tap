<?php
/**
 * One-shot seed for Big Tree Western demo shop (Kangar, Perlis).
 *
 * Requires shop already created (slug big-tree-western).
 * Safe to re-run: skips existing usernames / menu names / tables.
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
$out = [
    'ok' => true,
    'shop_id' => $shopId,
    'slug' => $slug,
    'created' => [
        'tables' => 0,
        'users' => 0,
        'categories' => 0,
        'items' => 0,
        'addons' => 0,
        'photos' => 0,
    ],
    'notes' => [],
];

ensureShopStations($shopId);
ensureShopMenuCategories($shopId);

// Rename system stations for Big Tree field ops
$pdo->prepare(
    "UPDATE stations SET nama_my = 'Bar Minuman', nama_en = 'Drinks Bar' WHERE shop_id = ? AND kod = 'minuman'"
)->execute([$shopId]);
$pdo->prepare(
    "UPDATE stations SET nama_my = 'Dapur Panas', nama_en = 'Hot Kitchen' WHERE shop_id = ? AND kod = 'dapur'"
)->execute([$shopId]);

// Pro custom station: Western (separate tickets / screen from hot kitchen)
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

// One Bluetooth printer at kasir prints all station tickets + optional drawer kick
$hubCol = $pdo->query("SHOW COLUMNS FROM shops LIKE 'kasir_print_hub'")->fetch();
if ($hubCol) {
    $pdo->prepare(
        'UPDATE shops SET kasir_print_hub = 1, kasir_open_drawer = 1 WHERE id = ?'
    )->execute([$shopId]);
    $out['notes'][] = 'kasir_print_hub + kasir_open_drawer enabled';
}

// More tables for dine-in demo (6–12)
$insTable = $pdo->prepare(
    'INSERT INTO tables (shop_id, nomor_meja, token_akses, status) VALUES (?, ?, ?, ?)'
);
$chkTable = $pdo->prepare('SELECT id FROM tables WHERE shop_id = ? AND nomor_meja = ? LIMIT 1');
for ($i = 6; $i <= 12; $i++) {
    $nomor = (string) $i;
    $chkTable->execute([$shopId, $nomor]);
    if ($chkTable->fetch()) {
        continue;
    }
    $insTable->execute([$shopId, $nomor, generateToken(24), 'aktif']);
    $out['created']['tables']++;
}

// Staff accounts
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
        $out['notes'][] = "user exists: {$username}";
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

// Customer categories
$wantedCats = [
    ['western', 'Western', 'Western', 'makanan', 1],
    ['burger', 'Burger', 'Burgers', 'makanan', 2],
    ['local', 'Nasi & Mee', 'Rice & Noodles', 'makanan', 3],
    ['sides', 'Sampingan', 'Sides', 'makanan', 4],
    ['minuman', 'Minuman', 'Drinks', 'minuman', 5],
];
$catIds = [];
foreach ($wantedCats as [$kod, $my, $en, $kind, $urutan]) {
    $existing = menuCategoryByKod($shopId, $kod);
    if ($existing) {
        $pdo->prepare(
            'UPDATE menu_categories SET nama_my = ?, nama_en = ?, kind = ?, urutan = ?, is_active = 1 WHERE id = ? AND shop_id = ?'
        )->execute([$my, $en, $kind, $urutan, (int) $existing['id'], $shopId]);
        $catIds[$kod] = (int) $existing['id'];
        continue;
    }
    // System makanan/minuman already exist — update labels if needed
    if (in_array($kod, ['makanan', 'minuman'], true)) {
        $row = menuCategoryByKod($shopId, $kod);
        if ($row) {
            $pdo->prepare(
                'UPDATE menu_categories SET nama_my = ?, nama_en = ?, urutan = ? WHERE id = ? AND shop_id = ?'
            )->execute([$my, $en, $urutan, (int) $row['id'], $shopId]);
            $catIds[$kod] = (int) $row['id'];
            continue;
        }
    }
    $pdo->prepare(
        'INSERT INTO menu_categories (shop_id, kod, nama_my, nama_en, kind, is_system, urutan, is_active)
         VALUES (?, ?, ?, ?, ?, 0, ?, 1)'
    )->execute([$shopId, $kod, $my, $en, $kind, $urutan]);
    $catIds[$kod] = (int) $pdo->lastInsertId();
    $out['created']['categories']++;
}

// Copy demo photos into storage/uploads/menu
$demoDir = dirname(__DIR__) . '/assets/img/demo/big-tree-western';
$uploadDir = storageRoot() . '/uploads/menu';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$photoMap = [
    'chicken-chop' => 'bigtree-chicken-chop.png',
    'burger' => 'bigtree-burger.png',
    'spaghetti' => 'bigtree-spaghetti.png',
    'nasi-lemak' => 'bigtree-nasi-lemak.png',
    'thai-tea' => 'bigtree-thai-tea.png',
    'fries' => 'bigtree-fries.png',
    'nasi-goreng' => 'bigtree-nasi-goreng.png',
    'maccheese' => 'bigtree-maccheese.png',
];

$copiedPhotos = [];
foreach ($photoMap as $key => $file) {
    $src = $demoDir . '/' . $file;
    if (!is_file($src)) {
        $out['notes'][] = "missing photo: {$file}";
        continue;
    }
    $destName = 'bigtree-' . $key . '-' . substr(sha1($file . $shopId), 0, 8) . '.png';
    $dest = $uploadDir . '/' . $destName;
    if (!is_file($dest)) {
        if (!@copy($src, $dest)) {
            $out['notes'][] = "copy failed: {$file}";
            continue;
        }
        $out['created']['photos']++;
    }
    $copiedPhotos[$key] = storageUploadRel('menu', $destName);
}

/**
 * @return list<array{jenis:string,nama_my:string,nama_en:string,harga:float}>
 */
$addonsLevel = static function (): array {
    return [
        ['jenis' => 'pilihan', 'nama_my' => 'Biasa', 'nama_en' => 'Regular', 'harga' => 0],
        ['jenis' => 'pilihan', 'nama_my' => 'Large / Besar', 'nama_en' => 'Large', 'harga' => 3.0],
    ];
};
$addonsBurger = static function (): array {
    return [
        ['jenis' => 'tambahan', 'nama_my' => '+ Keju', 'nama_en' => '+ Cheese', 'harga' => 1.5],
        ['jenis' => 'tambahan', 'nama_my' => '+ Telur', 'nama_en' => '+ Egg', 'harga' => 1.0],
        ['jenis' => 'tambahan', 'nama_my' => '+ Extra patty', 'nama_en' => '+ Extra patty', 'harga' => 3.0],
    ];
};
$addonsDrink = static function (): array {
    return [
        ['jenis' => 'pilihan', 'nama_my' => 'Normal manis', 'nama_en' => 'Regular sugar', 'harga' => 0],
        ['jenis' => 'pilihan', 'nama_my' => 'Kurang manis', 'nama_en' => 'Less sugar', 'harga' => 0],
        ['jenis' => 'pilihan', 'nama_my' => 'Tanpa gula', 'nama_en' => 'No sugar', 'harga' => 0],
    ];
};
$addonsNasi = static function (): array {
    return [
        ['jenis' => 'tambahan', 'nama_my' => '+ Telur mata', 'nama_en' => '+ Fried egg', 'harga' => 2.5],
        ['jenis' => 'tambahan', 'nama_my' => '+ Ayam goreng', 'nama_en' => '+ Fried chicken', 'harga' => 4.0],
    ];
};

$menu = [
    // Western station
    ['western', 'Spaghetti Aglio Olio', 'Spaghetti Aglio Olio', 'By Chef Faizal — garlic, chili, olive oil.', 18.50, 'spaghetti', 'western', $addonsLevel],
    ['western', 'Macaroni Aglio Olio', 'Macaroni Aglio Olio', 'Garlic olive oil macaroni with a kick.', 17.90, 'spaghetti', 'western', $addonsLevel],
    ['western', 'Mac & Cheese', 'Mac & Cheese', 'By Chef Faizal — creamy cheese macaroni.', 16.90, 'maccheese', 'western', null],
    ['western', 'Chicken Chop', 'Chicken Chop', 'Crispy chicken chop with fries & coleslaw.', 18.90, 'chicken-chop', 'western', null],
    ['western', 'Nasi Goreng Chicken Chop Besar', 'Big Fried Rice Chicken Chop', 'Savory fried rice with large crispy chop.', 31.90, 'chicken-chop', 'western', null],
    ['western', 'Macaroni Tomyam', 'Macaroni Tomyam', 'Tangy spicy tomyam macaroni.', 23.90, 'maccheese', 'western', $addonsLevel],

    // Hot kitchen / goreng
    ['burger', 'Burger Daging Special', 'Special Beef Burger', 'Ramly-style beef, egg, coleslaw, mayo & chili.', 10.00, 'burger', 'dapur', $addonsBurger],
    ['burger', 'Marvellous Burger', 'Marvellous Burger', 'Sesame bun, chicken & beef, fries, sauce.', 18.90, 'burger', 'dapur', $addonsBurger],
    ['burger', 'Western Chicken Burger', 'Western Chicken Burger', 'Crispy chicken burger with salad & sauce.', 13.50, 'burger', 'dapur', $addonsBurger],

    ['local', 'Nasi Goreng Kampung', 'Kampung Fried Rice', 'Village-style fried rice, fragrant & spicy.', 10.50, 'nasi-goreng', 'dapur', $addonsNasi],
    ['local', 'Mee Goreng Mamak', 'Mamak Fried Mee', 'Classic mamak fried noodles.', 12.50, 'nasi-goreng', 'dapur', $addonsNasi],
    ['local', 'Bihun Goreng By Chef Min', 'Fried Vermicelli by Chef Min', 'Signature fried bihun.', 10.90, 'nasi-goreng', 'dapur', $addonsNasi],
    ['local', 'Nasi Lemak Ayam Goreng', 'Nasi Lemak Fried Chicken', 'Coconut rice, sambal, fried chicken.', 11.90, 'nasi-lemak', 'dapur', null],
    ['local', 'Nasi Lemak Sahaja', 'Nasi Lemak Plain', 'Coconut rice with sambal, bilis, egg & cucumber.', 4.20, 'nasi-lemak', 'dapur', $addonsNasi],
    ['local', 'Kuew Teow Goreng', 'Fried Kuey Teow', 'By Chef — wok-fried flat noodles.', 10.90, 'nasi-goreng', 'dapur', $addonsNasi],

    ['sides', 'Cheezy Mayo Fries', 'Cheezy Mayo Fries', 'Crispy fries with cheese mayo drizzle.', 13.50, 'fries', 'dapur', null],
    ['sides', 'Kentang Goreng', 'French Fries', 'Crispy shoestring fries.', 8.60, 'fries', 'dapur', null],
    ['sides', 'Telur Mata Kerbau', 'Sunny Side Egg', 'Soft fried egg.', 2.50, null, 'dapur', null],
    ['sides', 'Popcorn Chicken', 'Popcorn Chicken', 'Crispy bite-size fried chicken.', 8.90, 'chicken-chop', 'dapur', null],

    // Drinks
    ['minuman', 'Thai Iced Milk Tea', 'Thai Iced Milk Tea', 'Sweet creamy Thai tea over ice.', 4.00, 'thai-tea', 'minuman', $addonsDrink],
    ['minuman', 'Teh Ais', 'Iced Tea', 'Classic Malaysian iced tea.', 3.50, 'thai-tea', 'minuman', $addonsDrink],
    ['minuman', 'Kopi Ais', 'Iced Coffee', 'Iced local coffee.', 3.99, 'thai-tea', 'minuman', $addonsDrink],
    ['minuman', 'Bandung Cincau', 'Bandung Grass Jelly', 'Rose syrup drink with cincau.', 4.90, 'thai-tea', 'minuman', $addonsDrink],
    ['minuman', 'Thai Tea Cincau', 'Thai Tea with Cincau', 'Thai tea recipe with grass jelly.', 4.90, 'thai-tea', 'minuman', $addonsDrink],
    ['minuman', 'Epal Asam Boi Ice', 'Apple Assam Boi Ice', 'Refreshing apple + asam boi iced drink.', 8.99, 'thai-tea', 'minuman', $addonsDrink],
];

$chkItem = $pdo->prepare(
    'SELECT id FROM menu_items WHERE shop_id = ? AND nama_my = ? LIMIT 1'
);
$hasCatCol = menuCategoryColumnExists();
$hasStationColMenu = menuStationColumnExists();

$stationIdForKod = static function (string $kod) use ($dapurId, $minumanId, $westernId): ?int {
    if ($kod === 'minuman') {
        return $minumanId;
    }
    if ($kod === 'western') {
        return $westernId ?: $dapurId;
    }
    return $dapurId;
};

foreach ($menu as $i => $row) {
    [$catKod, $namaMy, $namaEn, $desc, $harga, $photoKey, $stationKod, $addonFn] = $row;
    $chkItem->execute([$shopId, $namaMy]);
    $existingId = $chkItem->fetchColumn();
    $stationId = $stationIdForKod((string) $stationKod);
    $kategori = $stationKod === 'minuman' ? 'minuman' : 'makanan';
    $catId = $catIds[$catKod] ?? null;
    $foto = ($photoKey && isset($copiedPhotos[$photoKey])) ? $copiedPhotos[$photoKey] : null;
    $urutan = $i + 1;

    if ($existingId) {
        if ($hasStationColMenu && $stationId) {
            $pdo->prepare('UPDATE menu_items SET station_id = ?, kategori = ? WHERE id = ? AND shop_id = ?')
                ->execute([$stationId, $kategori, (int) $existingId, $shopId]);
        }
        $out['notes'][] = "item exists: {$namaMy}";
        continue;
    }

    if ($hasCatCol && $hasStationColMenu) {
        $pdo->prepare(
            'INSERT INTO menu_items
             (shop_id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, menu_category_id, station_id, foto_url, status_stok, urutan, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        )->execute([
            $shopId, $namaMy, $namaEn, $desc, $desc, $harga, $kategori, $catId, $stationId, $foto, 'tersedia', $urutan,
        ]);
    } elseif ($hasStationColMenu) {
        $pdo->prepare(
            'INSERT INTO menu_items
             (shop_id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, station_id, foto_url, status_stok, urutan, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        )->execute([
            $shopId, $namaMy, $namaEn, $desc, $desc, $harga, $kategori, $stationId, $foto, 'tersedia', $urutan,
        ]);
    } else {
        $pdo->prepare(
            'INSERT INTO menu_items
             (shop_id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, foto_url, status_stok, urutan, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        )->execute([
            $shopId, $namaMy, $namaEn, $desc, $desc, $harga, $kategori, $foto, 'tersedia', $urutan,
        ]);
    }

    $itemId = (int) $pdo->lastInsertId();
    $out['created']['items']++;

    if ($addonFn && menuAddonsTableExists() && $itemId > 0) {
        $insAddon = $pdo->prepare(
            'INSERT INTO menu_addons (shop_id, menu_item_id, nama_my, nama_en, harga_delta, jenis, urutan, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );
        foreach ($addonFn() as $ai => $ad) {
            $insAddon->execute([
                $shopId, $itemId, $ad['nama_my'], $ad['nama_en'], $ad['harga'], $ad['jenis'], $ai,
            ]);
            $out['created']['addons']++;
        }
    }
}

// Raise order limits for busy demo
$limitCol = $pdo->query("SHOW COLUMNS FROM shops LIKE 'order_burst_max'")->fetch();
if ($limitCol) {
    $pdo->prepare(
        'UPDATE shops SET order_burst_seconds = 120, order_burst_max = 50,
                cart_max_qty_per_item = 99, cart_max_distinct_items = 100, cart_max_total_qty = 300
         WHERE id = ?'
    )->execute([$shopId]);
}

$out['login'] = [
    'owner' => ['username' => 'ownerbigtree', 'password' => 'BigtreeDemo2026!'],
    'kasir' => ['username' => 'kasirbigtree', 'password' => 'Demo1234!'],
    'dapur' => ['username' => 'dapurbigtree', 'password' => 'Demo1234!'],
    'western' => ['username' => 'westernbigtree', 'password' => 'Demo1234!'],
    'minuman' => ['username' => 'minumanbigtree', 'password' => 'Demo1234!'],
    'waiter' => ['username' => 'waiterbigtree', 'password' => 'Demo1234!'],
];
$out['stations'] = [
    'dapur' => 'Dapur Panas (goreng / hot)',
    'western' => 'Western',
    'minuman' => 'Bar Minuman',
];
$out['print'] = [
    'mode' => 'kasir_print_hub',
    'note' => 'One Bluetooth printer at kasir auto-prints one ticket per station; staff tear and deliver. Cash drawer kicks on paid receipt.',
];
$out['urls'] = [
    'login' => 'https://tabletap.my/admin/login.php',
    'owner' => 'https://tabletap.my/admin/owner/index.php',
];

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
