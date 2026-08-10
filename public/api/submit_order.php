<?php
/**
 * AJAX: submit customer order
 * POST JSON: { meja, token, items: [{ menu_item_id, qty, catatan }] }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';

requirePost();

$body = readJsonBody();
$nomorMeja = trim((string) ($body['meja'] ?? ''));
$token = trim((string) ($body['token'] ?? ''));
$items = $body['items'] ?? [];

if ($nomorMeja === '' || $token === '') {
    jsonError('Invalid table access', 403);
}

if (!is_array($items) || $items === []) {
    jsonError('Cart is empty');
}

$table = findTableByAccess($nomorMeja, $token);
if (!$table) {
    jsonError('Invalid table access', 403);
}

// Normalize & validate items
$normalized = [];
foreach ($items as $row) {
    $menuId = (int) ($row['menu_item_id'] ?? 0);
    $qty = (int) ($row['qty'] ?? 0);
    $catatan = trim((string) ($row['catatan'] ?? ''));
    if ($menuId <= 0 || $qty <= 0) {
        continue;
    }
    if (mb_strlen($catatan) > 255) {
        $catatan = mb_substr($catatan, 0, 255);
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

$pdo = db();
$placeholders = implode(',', array_fill(0, count($normalized), '?'));
$ids = array_keys($normalized);

$stmt = $pdo->prepare(
    "SELECT id, nama_my, nama_en, harga, kategori, status_stok, is_active
     FROM menu_items
     WHERE id IN ($placeholders)"
);
$stmt->execute($ids);
$menuRows = $stmt->fetchAll();
$menuById = [];
foreach ($menuRows as $m) {
    $menuById[(int) $m['id']] = $m;
}

$total = 0.0;
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
    $total += $harga * $qty;
    $lines[] = [
        'menu_item_id' => $menuId,
        'qty' => $qty,
        'catatan' => $line['catatan'] !== '' ? $line['catatan'] : null,
        'harga_saat_order' => $harga,
        'nama_saat_order_my' => $m['nama_my'],
        'nama_saat_order_en' => $m['nama_en'],
        'kategori_saat_order' => $m['kategori'],
    ];
}

try {
    $pdo->beginTransaction();

    $insOrder = $pdo->prepare(
        'INSERT INTO orders (table_id, waktu_order, status_order, status_bayar, total_harga)
         VALUES (?, NOW(), ?, ?, ?)'
    );
    $insOrder->execute([
        (int) $table['id'],
        'menunggu',
        'belum_bayar',
        round($total, 2),
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $insItem = $pdo->prepare(
        'INSERT INTO order_items
         (order_id, menu_item_id, qty, catatan, status_item, harga_saat_order, nama_saat_order_my, nama_saat_order_en, kategori_saat_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($lines as $line) {
        $insItem->execute([
            $orderId,
            $line['menu_item_id'],
            $line['qty'],
            $line['catatan'],
            'menunggu',
            $line['harga_saat_order'],
            $line['nama_saat_order_my'],
            $line['nama_saat_order_en'],
            $line['kategori_saat_order'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonError('Failed to save order', 500);
}

$redirect = baseUrl('public/confirmation.php?order=' . $orderId . '&meja=' . urlencode($nomorMeja) . '&token=' . urlencode($token));

jsonResponse([
    'ok' => true,
    'order_id' => $orderId,
    'total' => round($total, 2),
    'redirect' => $redirect,
]);
