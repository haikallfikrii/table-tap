<?php
/**
 * Helper functions — formatting, JSON responses, tokens, etc.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/shop.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatMoney(float|string $amount): string
{
    $c = getConfig();
    $decimals = (int) ($c['currency_decimals'] ?? 2);
    $symbol = $c['currency'] ?? 'RM';
    return $symbol . ' ' . number_format((float) $amount, $decimals, '.', ',');
}

function orderCustomerEmailColumnExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW COLUMNS FROM orders LIKE 'customer_email'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function saveOrderCustomerEmail(int $orderId, ?string $email): void
{
    if (!orderCustomerEmailColumnExists()) {
        return;
    }
    $email = strtolower(trim((string) $email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    if (strlen($email) > 255) {
        return;
    }
    db()->prepare('UPDATE orders SET customer_email = ? WHERE id = ? LIMIT 1')
        ->execute([$email, $orderId]);
}

function generateToken(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/** Public hosts that may serve this app (login, QR, assets stay on the current domain). */
function allowedAppHosts(): array
{
    $c = getConfig();
    $hosts = $c['allowed_hosts'] ?? [
        'tabletap.my',
        'www.tabletap.my',
        'tabletap.jomsite.com',
        'localhost',
        '127.0.0.1',
    ];
    $configured = parse_url((string) ($c['app_url'] ?? ''), PHP_URL_HOST);
    if (is_string($configured) && $configured !== '') {
        $hosts[] = $configured;
    }
    $clean = [];
    foreach ($hosts as $host) {
        $host = strtolower(trim((string) $host));
        if ($host !== '') {
            $clean[$host] = true;
        }
    }
    return array_keys($clean);
}

function requestHost(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);
    return trim($host);
}

/** Canonical origin for this request: tabletap.my stays on .my, jomsite stays on jomsite. */
function appBaseUrl(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $c = getConfig();
    $fallback = rtrim((string) ($c['app_url'] ?? ''), '/');
    $host = requestHost();

    if ($host !== '' && in_array($host, allowedAppHosts(), true)) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        if (str_ends_with($host, 'tabletap.my') || str_ends_with($host, 'jomsite.com')) {
            $https = true;
        }
        $base = ($https ? 'https' : 'http') . '://' . $host;
        return $base;
    }

    $base = $fallback;
    return $base;
}

function baseUrl(string $path = ''): string
{
    $base = rtrim(appBaseUrl(), '/');
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
}

function orderUrl(string|int $nomorMeja, string $token): string
{
    return baseUrl('public/order.php?meja=' . urlencode((string) $nomorMeja) . '&token=' . urlencode($token));
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $status = 400, array $extra = []): never
{
    jsonResponse(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

function requirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonError('Method not allowed', 405);
    }
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function assetUrl(string $path): string
{
    $rel = ltrim($path, '/');
    $url = baseUrl('assets/' . $rel);
    $file = dirname(__DIR__) . '/assets/' . $rel;
    if (is_file($file)) {
        $url .= '?v=' . filemtime($file);
    }
    return $url;
}

/** ChatLM sales/support popup. Empty api key disables the widget. */
function chatlmWidgetHtml(): string
{
    $c = getConfig();
    if (array_key_exists('chatlm_enabled', $c) && !$c['chatlm_enabled']) {
        return '';
    }
    $key = trim((string) ($c['chatlm_api_key'] ?? 'df78d68075cf72f71568061bf7e17726424993dcd938fb16b1331cd12dd63b41'));
    $base = rtrim((string) ($c['chatlm_base_url'] ?? 'https://chatlm.tech'), '/');
    if ($key === '' || $base === '') {
        return '';
    }
    return '<script src="' . e($base) . '/widget/widget.js" data-api-key="' . e($key) . '" data-base-url="' . e($base) . '" defer></script>' . "\n";
}

function uploadPath(string $filename = ''): string
{
    $dir = dirname(__DIR__) . '/assets/uploads/menu';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $filename === '' ? $dir : $dir . '/' . $filename;
}

/**
 * Verify table access via nomor + token.
 * Returns table row + shop fields, or null.
 */
function findTableByAccess(string $nomorMeja, string $token): ?array
{
    $stmt = db()->prepare(
        "SELECT t.*, s.nama_kedai, s.slug, s.status AS shop_status,
                s.sst_enabled, s.sst_rate, s.package_id, s.fulfillment_mode,
                p.kod AS package_kod
         FROM tables t
         INNER JOIN shops s ON s.id = t.shop_id
         INNER JOIN packages p ON p.id = s.package_id
         WHERE t.nomor_meja = ? AND t.token_akses = ? AND t.status = 'aktif' AND s.status = 'aktif'
         LIMIT 1"
    );
    $stmt->execute([$nomorMeja, $token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function findTableByIdForShop(int $tableId, int $shopId): ?array
{
    $stmt = db()->prepare(
        "SELECT t.*, s.nama_kedai, s.slug, s.status AS shop_status,
                s.sst_enabled, s.sst_rate, s.package_id, s.fulfillment_mode,
                p.kod AS package_kod
         FROM tables t
         INNER JOIN shops s ON s.id = t.shop_id
         INNER JOIN packages p ON p.id = s.package_id
         WHERE t.id = ? AND t.shop_id = ? AND t.status = 'aktif' AND s.status = 'aktif'
         LIMIT 1"
    );
    $stmt->execute([$tableId, $shopId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function listActiveShopTables(int $shopId): array
{
    $stmt = db()->prepare(
        "SELECT id, nomor_meja FROM tables
         WHERE shop_id = ? AND status = 'aktif'
         ORDER BY CAST(nomor_meja AS UNSIGNED), nomor_meja"
    );
    $stmt->execute([$shopId]);
    return $stmt->fetchAll();
}

function staffStationHome(string $from): string
{
    return match ($from) {
        'kasir' => baseUrl('admin/kasir.php'),
        'owner' => baseUrl('admin/owner/index.php'),
        default => baseUrl('admin/waiter.php'),
    };
}

/**
 * Create an order from a cart payload. Calls jsonError() on validation failure.
 *
 * @param array<int, mixed> $items
 * @return array{order_id:int,totals:array}
 */
function createShopOrder(
    array $table,
    array $shop,
    array $items,
    string $jenisHidang,
    string $guestName,
    string $sumber,
    bool $optionalGuestName = false,
    ?int $sessionId = null,
    ?string $customerEmail = null
): array {
    if (!is_array($items) || $items === []) {
        jsonError('Cart is empty');
    }

    if ($jenisHidang === 'delivery') {
        $jenisHidang = 'delivery';
    } elseif ($jenisHidang === 'takeaway') {
        $jenisHidang = 'takeaway';
    } else {
        $jenisHidang = 'dine_in';
    }

    $shopId = (int) $table['shop_id'];
    $selfPickup = shopFulfillment($shop) === 'self_pickup';
    $guestName = normalizeCustomerName($guestName);
    if ($jenisHidang === 'delivery' || $selfPickup) {
        if (!isValidCustomerName($guestName)) {
            jsonError('Name required');
        }
    } elseif (!$optionalGuestName || !isValidCustomerName($guestName)) {
        $guestName = '';
    }

    $normalized = [];
    foreach ($items as $row) {
        $menuId = (int) ($row['menu_item_id'] ?? 0);
        $qty = (int) ($row['qty'] ?? 0);
        $catatan = trim((string) ($row['catatan'] ?? ''));
        if ($menuId <= 0 || $qty <= 0) {
            continue;
        }
        if (function_exists('mb_strlen') && mb_strlen($catatan) > 255) {
            $catatan = mb_substr($catatan, 0, 255);
        } elseif (strlen($catatan) > 255) {
            $catatan = substr($catatan, 0, 255);
        }
        if (isset($normalized[$menuId])) {
            $normalized[$menuId]['qty'] += $qty;
            if ($catatan !== '') {
                $normalized[$menuId]['catatan'] = $catatan;
            }
        } else {
            $normalized[$menuId] = [
                'menu_item_id' => $menuId,
                'qty' => $qty,
                'catatan' => $catatan,
            ];
        }
    }

    if ($normalized === []) {
        jsonError('No valid items');
    }

    assertCartLimits($normalized);
    if ($sessionId !== null && $sessionId > 0 && function_exists('assertSessionOrderRateLimit')) {
        assertSessionOrderRateLimit($sessionId, $shopId);
    } else {
        assertTableOrderRateLimit((int) $table['id'], $shopId);
    }

    $pdo = db();
    $placeholders = implode(',', array_fill(0, count($normalized), '?'));
    $ids = array_keys($normalized);
    require_once __DIR__ . '/stations.php';
    $stationCols = menuStationColumnExists();
    $menuSql = $stationCols
        ? "SELECT id, nama_my, nama_en, harga, kategori, station_id, status_stok, is_active
           FROM menu_items
           WHERE shop_id = ? AND id IN ($placeholders)"
        : "SELECT id, nama_my, nama_en, harga, kategori, status_stok, is_active
           FROM menu_items
           WHERE shop_id = ? AND id IN ($placeholders)";
    $stmt = $pdo->prepare($menuSql);
    $stmt->execute(array_merge([$shopId], $ids));
    $menuById = [];
    foreach ($stmt->fetchAll() as $m) {
        $menuById[(int) $m['id']] = $m;
    }

    $subtotal = 0.0;
    $lines = [];
    foreach ($normalized as $menuId => $line) {
        if (!isset($menuById[$menuId])) {
            jsonError('Menu item not found');
        }
        $m = $menuById[$menuId];
        if (!(int) $m['is_active'] || $m['status_stok'] === 'habis') {
            jsonError('Item unavailable: ' . $m['nama_my']);
        }
        $harga = (float) $m['harga'];
        $qty = (int) $line['qty'];
        $subtotal += $harga * $qty;
        $stationId = null;
        if ($stationCols) {
            $stationId = (int) ($m['station_id'] ?? 0);
            if ($stationId <= 0) {
                $fallback = defaultStationForKategori($shopId, (string) $m['kategori']);
                $stationId = $fallback ? (int) $fallback['id'] : null;
            }
        }
        $lines[] = [
            'menu_item_id' => $menuId,
            'qty' => $qty,
            'catatan' => $line['catatan'] !== '' ? $line['catatan'] : null,
            'harga_saat_order' => $harga,
            'nama_saat_order_my' => $m['nama_my'],
            'nama_saat_order_en' => $m['nama_en'],
            'kategori_saat_order' => $m['kategori'],
            'station_id_saat_order' => $stationId,
        ];
    }

    $totals = calculateTotals($subtotal, $shop);
    $sumber = $sumber === 'staf' ? 'staf' : 'qr';
    $hasSumber = (bool) $pdo->query("SHOW COLUMNS FROM orders LIKE 'sumber_order'")->fetch();
    $hasGuestToken = orderGuestTokenColumnExists();
    $hasSessionId = orderSessionColumnExists();
    $guestToken = $hasGuestToken ? generateOrderGuestToken() : null;
    $useSession = $hasSessionId && $sessionId !== null && $sessionId > 0;

    try {
        $pdo->beginTransaction();
        if ($useSession && $hasSumber && $hasGuestToken) {
            $insOrder = $pdo->prepare(
                'INSERT INTO orders
                 (shop_id, table_id, session_id, waktu_order, status_order, status_bayar, jenis_hidang, nama_pelanggan, guest_token, sumber_order, subtotal, sst_rate, sst_jumlah, total_harga)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insOrder->execute([
                $shopId,
                (int) $table['id'],
                $sessionId,
                appNow(),
                'menunggu',
                'belum_bayar',
                $jenisHidang,
                $guestName !== '' ? $guestName : null,
                $guestToken,
                $sumber,
                $totals['subtotal'],
                $totals['sst_rate'],
                $totals['sst_jumlah'],
                $totals['total'],
            ]);
        } elseif ($hasSumber && $hasGuestToken) {
            $insOrder = $pdo->prepare(
                'INSERT INTO orders
                 (shop_id, table_id, waktu_order, status_order, status_bayar, jenis_hidang, nama_pelanggan, guest_token, sumber_order, subtotal, sst_rate, sst_jumlah, total_harga)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insOrder->execute([
                $shopId,
                (int) $table['id'],
                appNow(),
                'menunggu',
                'belum_bayar',
                $jenisHidang,
                $guestName !== '' ? $guestName : null,
                $guestToken,
                $sumber,
                $totals['subtotal'],
                $totals['sst_rate'],
                $totals['sst_jumlah'],
                $totals['total'],
            ]);
        } elseif ($hasSumber) {
            $insOrder = $pdo->prepare(
                'INSERT INTO orders
                 (shop_id, table_id, waktu_order, status_order, status_bayar, jenis_hidang, nama_pelanggan, sumber_order, subtotal, sst_rate, sst_jumlah, total_harga)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insOrder->execute([
                $shopId,
                (int) $table['id'],
                appNow(),
                'menunggu',
                'belum_bayar',
                $jenisHidang,
                $guestName !== '' ? $guestName : null,
                $sumber,
                $totals['subtotal'],
                $totals['sst_rate'],
                $totals['sst_jumlah'],
                $totals['total'],
            ]);
        } elseif ($hasGuestToken) {
            $insOrder = $pdo->prepare(
                'INSERT INTO orders
                 (shop_id, table_id, waktu_order, status_order, status_bayar, jenis_hidang, nama_pelanggan, guest_token, subtotal, sst_rate, sst_jumlah, total_harga)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insOrder->execute([
                $shopId,
                (int) $table['id'],
                appNow(),
                'menunggu',
                'belum_bayar',
                $jenisHidang,
                $guestName !== '' ? $guestName : null,
                $guestToken,
                $totals['subtotal'],
                $totals['sst_rate'],
                $totals['sst_jumlah'],
                $totals['total'],
            ]);
        } else {
            $insOrder = $pdo->prepare(
                'INSERT INTO orders
                 (shop_id, table_id, waktu_order, status_order, status_bayar, jenis_hidang, nama_pelanggan, subtotal, sst_rate, sst_jumlah, total_harga)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insOrder->execute([
                $shopId,
                (int) $table['id'],
                appNow(),
                'menunggu',
                'belum_bayar',
                $jenisHidang,
                $guestName !== '' ? $guestName : null,
                $totals['subtotal'],
                $totals['sst_rate'],
                $totals['sst_jumlah'],
                $totals['total'],
            ]);
        }
        $orderId = (int) $pdo->lastInsertId();

        if ($customerEmail !== null && $customerEmail !== '') {
            saveOrderCustomerEmail($orderId, $customerEmail);
        }

        $snapStation = orderStationColumnExists();
        $insItem = $pdo->prepare(
            $snapStation
                ? 'INSERT INTO order_items
                   (order_id, menu_item_id, qty, catatan, status_item, harga_saat_order, nama_saat_order_my, nama_saat_order_en, kategori_saat_order, station_id_saat_order)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                : 'INSERT INTO order_items
                   (order_id, menu_item_id, qty, catatan, status_item, harga_saat_order, nama_saat_order_my, nama_saat_order_en, kategori_saat_order)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lines as $line) {
            $args = [
                $orderId,
                $line['menu_item_id'],
                $line['qty'],
                $line['catatan'],
                'menunggu',
                $line['harga_saat_order'],
                $line['nama_saat_order_my'],
                $line['nama_saat_order_en'],
                $line['kategori_saat_order'],
            ];
            if ($snapStation) {
                $args[] = $line['station_id_saat_order'];
            }
            $insItem->execute($args);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonError('Failed to save order', 500);
    }

    if ($sessionId !== null && $sessionId > 0) {
        touchSessionActivity($sessionId);
    }

    return [
        'order_id' => $orderId,
        'totals' => $totals,
        'guest_token' => $guestToken ?? '',
    ];
}

function getMenuGrouped(int $shopId, string $lang = 'my'): array
{
    require_once __DIR__ . '/menu_categories.php';

    $useCategories = menuCategoriesTableExists() && menuCategoryColumnExists();
    if ($useCategories) {
        ensureShopMenuCategories($shopId);
        backfillMenuItemCategories();
    }

    $orderBy = $useCategories
        ? 'menu_category_id ASC, urutan ASC, id ASC'
        : 'kategori ASC, urutan ASC, id ASC';
    $stmt = db()->prepare(
        "SELECT id, nama_my, nama_en, deskripsi_my, deskripsi_en, harga, kategori, foto_url, status_stok
                " . ($useCategories ? ', menu_category_id' : '') . "
         FROM menu_items
         WHERE shop_id = ? AND is_active = 1
         ORDER BY {$orderBy}"
    );
    $stmt->execute([$shopId]);
    $items = $stmt->fetchAll();
    $ids = array_map(static fn($row) => (int) $row['id'], $items);
    $gallery = [];
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $g = db()->prepare(
                "SELECT id, menu_item_id, foto_url
                 FROM menu_photos
                 WHERE shop_id = ? AND menu_item_id IN ($placeholders)
                 ORDER BY urutan ASC, id ASC"
            );
            $g->execute(array_merge([$shopId], $ids));
            foreach ($g->fetchAll() as $photo) {
                $gallery[(int) $photo['menu_item_id']][] = $photo;
            }
        } catch (Throwable $e) {
            $gallery = [];
        }
    }

    $itemRows = [];
    foreach ($items as $item) {
        $item['nama'] = $lang === 'en' ? $item['nama_en'] : $item['nama_my'];
        $item['deskripsi'] = $lang === 'en' ? $item['deskripsi_en'] : $item['deskripsi_my'];
        $photos = [];
        if (!empty($item['foto_url'])) {
            $photos[] = $item['foto_url'];
        }
        foreach ($gallery[(int) $item['id']] ?? [] as $photo) {
            if (!empty($photo['foto_url']) && !in_array($photo['foto_url'], $photos, true)) {
                $photos[] = $photo['foto_url'];
            }
        }
        $item['gallery'] = $photos;
        $itemRows[] = $item;
    }

    if ($useCategories) {
        $cats = shopMenuCategories($shopId, true);
        $byCat = [];
        foreach ($cats as $cat) {
            $byCat[(int) $cat['id']] = [];
        }
        foreach ($itemRows as $item) {
            $cid = (int) ($item['menu_category_id'] ?? 0);
            if ($cid <= 0 || !isset($byCat[$cid])) {
                $legacy = menuCategoryByKod($shopId, (string) ($item['kategori'] ?? 'makanan'));
                $cid = $legacy ? (int) $legacy['id'] : 0;
            }
            if ($cid > 0 && isset($byCat[$cid])) {
                $byCat[$cid][] = $item;
            }
        }
        $categories = [];
        foreach ($cats as $cat) {
            $cid = (int) $cat['id'];
            $categories[] = [
                'id' => $cid,
                'kod' => (string) $cat['kod'],
                'label' => menuCategoryLabel($cat, $lang),
                'kind' => menuCategoryKind($cat),
                'items' => $byCat[$cid] ?? [],
            ];
        }
        return ['categories' => $categories];
    }

    $grouped = ['makanan' => [], 'minuman' => []];
    foreach ($itemRows as $item) {
        $kat = (string) ($item['kategori'] ?? 'makanan');
        if (!isset($grouped[$kat])) {
            $kat = 'makanan';
        }
        $grouped[$kat][] = $item;
    }
    return [
        'categories' => [
            [
                'id' => 0,
                'kod' => 'makanan',
                'label' => t('makanan'),
                'kind' => 'makanan',
                'items' => $grouped['makanan'],
            ],
            [
                'id' => 0,
                'kod' => 'minuman',
                'label' => t('minuman'),
                'kind' => 'minuman',
                'items' => $grouped['minuman'],
            ],
        ],
    ];
}

/** Overall track stage from order_items rows. */
function trackStageFromItems(array $items, string $fulfillment = 'waiter'): string
{
    if ($items === []) {
        return 'queue';
    }
    $allDone = true;
    $anyDeliver = false;
    $anyReady = false;
    $anyCook = false;
    $selfPickup = $fulfillment === 'self_pickup';
    foreach ($items as $it) {
        $st = (string) ($it['status_item'] ?? '');
        if ($st !== 'dihantar') {
            $allDone = false;
        }
        if ($st === 'diambil') {
            $anyDeliver = !$selfPickup;
            if ($selfPickup) {
                $anyReady = true;
            }
        }
        if ($st === 'siap') {
            $anyReady = true;
        }
        if ($st === 'sedang_dimasak') {
            $anyCook = true;
        }
    }
    if ($allDone) {
        return 'done';
    }
    if ($anyDeliver) {
        return 'delivering';
    }
    if ($anyReady) {
        return 'ready';
    }
    if ($anyCook) {
        return 'cooking';
    }
    return 'queue';
}

require_once __DIR__ . '/customer_orders.php';
require_once __DIR__ . '/cafe_sessions.php';
require_once __DIR__ . '/delivery.php';
