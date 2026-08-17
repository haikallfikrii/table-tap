<?php
/**
 * Customer-facing order helpers — multi-order tracking & security.
 */

declare(strict_types=1);

function orderGuestTokenColumnExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW COLUMNS FROM orders LIKE 'guest_token'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function generateOrderGuestToken(): string
{
    return bin2hex(random_bytes(16));
}

function assertTableOrderRateLimit(int $tableId, int $shopId): void
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM orders
         WHERE table_id = ? AND shop_id = ?
           AND waktu_order >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
    );
    $stmt->execute([$tableId, $shopId]);
    if ((int) $stmt->fetchColumn() >= 10) {
        jsonError(t('order_rate_limited'), 429);
    }
}

/** @param array<int, array{qty:int}> $normalized */
function assertCartLimits(array $normalized): void
{
    $distinct = count($normalized);
    $totalQty = 0;
    foreach ($normalized as $line) {
        $qty = (int) ($line['qty'] ?? 0);
        if ($qty > 20) {
            jsonError(t('cart_qty_too_high'));
        }
        $totalQty += $qty;
    }
    if ($distinct > 25) {
        jsonError(t('cart_too_many_items'));
    }
    if ($totalQty > 50) {
        jsonError(t('cart_total_qty_too_high'));
    }
}

/** @return list<array<string, mixed>> */
function fetchActiveCustomerOrders(array $table, string $lang): array
{
    $shopId = (int) $table['shop_id'];
    $tableId = (int) $table['id'];
    $hasGuestToken = orderGuestTokenColumnExists();
    $guestCol = $hasGuestToken ? ', guest_token' : '';

    $stmt = db()->prepare(
        "SELECT id, subtotal, sst_rate, sst_jumlah, total_harga, status_order, waktu_order,
                jenis_hidang, nama_pelanggan, pickup_alert{$guestCol}
         FROM orders
         WHERE table_id = ? AND shop_id = ?
           AND status_order != 'dibatalkan'
           AND (status_bayar = 'belum_bayar' OR status_order IN ('menunggu', 'diproses'))
         ORDER BY id DESC"
    );
    $stmt->execute([$tableId, $shopId]);
    $rows = $stmt->fetchAll();
    if ($rows === []) {
        return [];
    }

    $orderIds = array_map(static fn(array $r): int => (int) $r['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = db()->prepare(
        "SELECT order_id, id, qty, status_item, nama_saat_order_my, nama_saat_order_en, kategori_saat_order
         FROM order_items
         WHERE order_id IN ($placeholders)
         ORDER BY order_id ASC, id ASC"
    );
    $itemStmt->execute($orderIds);
    $itemsByOrder = [];
    foreach ($itemStmt->fetchAll() as $it) {
        $oid = (int) $it['order_id'];
        $itemsByOrder[$oid][] = $it;
    }

    $fulfillment = shopFulfillment($table);
    $orders = [];
    foreach ($rows as $row) {
        $oid = (int) $row['id'];
        $items = customerOrderItemsPayload($itemsByOrder[$oid] ?? [], $lang);
        $orders[] = customerOrderPayload($row, $items, $fulfillment, $hasGuestToken);
    }
    return $orders;
}

/** @param list<array<string, mixed>> $rows */
function customerOrderItemsPayload(array $rows, string $lang): array
{
    $items = [];
    foreach ($rows as $it) {
        $items[] = [
            'id' => (int) $it['id'],
            'qty' => (int) $it['qty'],
            'status_item' => (string) $it['status_item'],
            'nama' => $lang === 'en' ? $it['nama_saat_order_en'] : $it['nama_saat_order_my'],
            'kategori' => $it['kategori_saat_order'],
        ];
    }
    return $items;
}

/** @param list<array<string, mixed>> $items */
function customerOrderPayload(array $order, array $items, string $fulfillment, bool $includeGuestToken = true): array
{
    $payload = [
        'order_id' => (int) $order['id'],
        'status_order' => (string) $order['status_order'],
        'nama_pelanggan' => (string) ($order['nama_pelanggan'] ?? ''),
        'jenis_hidang' => (string) ($order['jenis_hidang'] ?? 'dine_in'),
        'subtotal' => (float) $order['subtotal'],
        'sst_rate' => (float) $order['sst_rate'],
        'sst_jumlah' => (float) $order['sst_jumlah'],
        'total_harga' => (float) $order['total_harga'],
        'total_formatted' => formatMoney((float) $order['total_harga']),
        'waktu_order' => (string) $order['waktu_order'],
        'stage' => trackStageFromItems($items),
        'fulfillment' => $fulfillment,
        'pickup_alert' => $fulfillment !== 'self_pickup' || (int) ($order['pickup_alert'] ?? 1) === 1,
        'items' => $items,
    ];
    if ($includeGuestToken && orderGuestTokenColumnExists()) {
        $payload['guest_token'] = (string) ($order['guest_token'] ?? '');
    }
    return $payload;
}

function findCustomerOrderForTable(array $table, int $orderId, string $lang): ?array
{
    foreach (fetchActiveCustomerOrders($table, $lang) as $order) {
        if ((int) ($order['order_id'] ?? 0) === $orderId) {
            return $order;
        }
    }
    return null;
}

function verifyOrderGuestToken(array $orderRow, string $guestToken): bool
{
    if (!orderGuestTokenColumnExists()) {
        return true;
    }
    $stored = trim((string) ($orderRow['guest_token'] ?? ''));
    if ($stored === '') {
        return true;
    }
    $guestToken = trim($guestToken);
    return $guestToken !== '' && hash_equals($stored, $guestToken);
}
