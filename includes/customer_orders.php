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

/** @return array{table_burst_seconds:int,table_burst_max_orders:int,cart_max_qty_per_item:int,cart_max_distinct_items:int,cart_max_total_qty:int} */
function orderLimits(): array
{
    $c = getConfig()['order_limits'] ?? [];
    return [
        'table_burst_seconds' => max(10, (int) ($c['table_burst_seconds'] ?? 60)),
        'table_burst_max_orders' => max(1, (int) ($c['table_burst_max_orders'] ?? 15)),
        'cart_max_qty_per_item' => max(1, (int) ($c['cart_max_qty_per_item'] ?? 99)),
        'cart_max_distinct_items' => max(1, (int) ($c['cart_max_distinct_items'] ?? 80)),
        'cart_max_total_qty' => max(1, (int) ($c['cart_max_total_qty'] ?? 200)),
    ];
}

function assertTableOrderRateLimit(int $tableId, int $shopId, ?string $jenisHidang = null): void
{
    // Shared virtual "Delivery" table must NOT block concurrent customers.
    if ($jenisHidang === 'delivery') {
        return;
    }
    $limits = orderLimits();
    $seconds = (int) $limits['table_burst_seconds'];
    $max = (int) $limits['table_burst_max_orders'];
    // Anti double-tap: unpaid orders in the burst window (per physical table).
    $burst = db()->prepare(
        "SELECT COUNT(*) FROM orders
         WHERE table_id = ? AND shop_id = ?
           AND status_bayar = 'belum_bayar'
           AND status_order != 'dibatalkan'
           AND waktu_order >= DATE_SUB(NOW(), INTERVAL {$seconds} SECOND)"
    );
    $burst->execute([$tableId, $shopId]);
    if ((int) $burst->fetchColumn() >= $max) {
        jsonError(t('order_rate_limited'), 429);
    }
}

/**
 * Soft anti-spam for delivery: same email can't hammer submit; different customers OK.
 */
function assertDeliveryContactRateLimit(int $shopId, string $email): void
{
    $email = strtolower(trim($email));
    if ($email === '' || !orderCustomerEmailColumnExists()) {
        return;
    }
    // Double-tap: 2+ in 8 seconds from same email
    $burst = db()->prepare(
        "SELECT COUNT(*) FROM orders
         WHERE shop_id = ? AND customer_email = ?
           AND jenis_hidang = 'delivery'
           AND status_order != 'dibatalkan'
           AND waktu_order >= DATE_SUB(NOW(), INTERVAL 8 SECOND)"
    );
    $burst->execute([$shopId, $email]);
    if ((int) $burst->fetchColumn() >= 2) {
        jsonError(t('order_rate_limited'), 429);
    }
}

/** @param array<int, array{qty:int}> $normalized */
function assertCartLimits(array $normalized): void
{
    $limits = orderLimits();
    $distinct = count($normalized);
    $totalQty = 0;
    foreach ($normalized as $line) {
        $qty = (int) ($line['qty'] ?? 0);
        if ($qty > $limits['cart_max_qty_per_item']) {
            jsonError(t('cart_qty_too_high'));
        }
        $totalQty += $qty;
    }
    if ($distinct > $limits['cart_max_distinct_items']) {
        jsonError(t('cart_too_many_items'));
    }
    if ($totalQty > $limits['cart_max_total_qty']) {
        jsonError(t('cart_total_qty_too_high'));
    }
}

/** @return list<array<string, mixed>> */
function fetchActiveCustomerOrders(array $table, string $lang, ?string $guestToken = null, ?string $customerEmail = null): array
{
    $shopId = (int) $table['shop_id'];
    $tableId = (int) $table['id'];
    $hasGuestToken = orderGuestTokenColumnExists();
    $hasEmail = orderCustomerEmailColumnExists();
    $guestCol = $hasGuestToken ? ', guest_token' : '';
    $emailCol = $hasEmail ? ', customer_email' : '';
    $payCol = orderDeliveryColumnsExist()
        ? ', payment_method, payment_proof_url, payment_proof_status, status_bayar, phone, alamat'
        : '';

    $isDeliveryTable = (($table['nomor_meja'] ?? '') === DELIVERY_TABLE_NUMBER)
        || (($table['channel'] ?? '') === 'delivery');

    $sql = "SELECT id, subtotal, sst_rate, sst_jumlah, total_harga, status_order, waktu_order,
                   jenis_hidang, nama_pelanggan, pickup_alert{$guestCol}{$emailCol}{$payCol}
            FROM orders
            WHERE table_id = ? AND shop_id = ?
              AND status_bayar = 'belum_bayar'
              AND status_order != 'dibatalkan'";
    $params = [$tableId, $shopId];

    // Delivery: isolate by guest_token and/or email so customers never see each other.
    if ($isDeliveryTable) {
        $guestToken = trim((string) $guestToken);
        $customerEmail = $customerEmail !== null ? strtolower(trim($customerEmail)) : '';
        if ($guestToken !== '' && $hasGuestToken && $customerEmail !== '' && $hasEmail) {
            $sql .= ' AND (guest_token = ? OR customer_email = ?)';
            $params[] = $guestToken;
            $params[] = $customerEmail;
        } elseif ($guestToken !== '' && $hasGuestToken) {
            $sql .= ' AND guest_token = ?';
            $params[] = $guestToken;
        } elseif ($customerEmail !== '' && $hasEmail) {
            $sql .= ' AND customer_email = ?';
            $params[] = $customerEmail;
        } else {
            // No identity → return nothing rather than leaking all delivery orders
            return [];
        }
    }

    $sql .= ' ORDER BY id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
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

/**
 * Resolve email for a delivery order so multi-order tracking stays per customer.
 */
function findOrderCustomerEmail(int $orderId, int $shopId): string
{
    if (!orderCustomerEmailColumnExists() || $orderId <= 0) {
        return '';
    }
    $stmt = db()->prepare(
        'SELECT customer_email FROM orders WHERE id = ? AND shop_id = ? LIMIT 1'
    );
    $stmt->execute([$orderId, $shopId]);
    $row = $stmt->fetch();
    return strtolower(trim((string) ($row['customer_email'] ?? '')));
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
        'stage' => customerOrderStage($order, $items, $fulfillment),
        'fulfillment' => $fulfillment,
        'pickup_alert' => $fulfillment !== 'self_pickup' || (int) ($order['pickup_alert'] ?? 1) === 1,
        'items' => $items,
    ];
    if ($includeGuestToken && orderGuestTokenColumnExists()) {
        $payload['guest_token'] = (string) ($order['guest_token'] ?? '');
    }
    if (orderDeliveryColumnsExist()) {
        $payload['payment_method'] = (string) ($order['payment_method'] ?? 'counter');
        $payload['payment_proof_url'] = (string) ($order['payment_proof_url'] ?? '');
        $payload['payment_proof_status'] = (string) ($order['payment_proof_status'] ?? 'none');
        $payload['status_bayar'] = (string) ($order['status_bayar'] ?? 'belum_bayar');
        $payload['phone'] = (string) ($order['phone'] ?? '');
        $payload['alamat'] = (string) ($order['alamat'] ?? '');
    }
    return $payload;
}

/** @param list<array<string, mixed>> $items */
function customerOrderStage(array $order, array $items, string $fulfillment): string
{
    if (($order['status_order'] ?? '') === 'selesai') {
        return 'done';
    }
    return trackStageFromItems($items, $fulfillment);
}

function findCustomerOrderForTable(array $table, int $orderId, string $lang, ?string $guestToken = null): ?array
{
    $email = findOrderCustomerEmail($orderId, (int) $table['shop_id']);
    foreach (fetchActiveCustomerOrders($table, $lang, $guestToken, $email !== '' ? $email : null) as $order) {
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
