<?php
/**
 * Cashier billing helpers — totals recalc + item-level split bill.
 */

declare(strict_types=1);

require_once __DIR__ . '/shop.php';
require_once __DIR__ . '/receipt.php';

function orderSplitFromColumnExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW COLUMNS FROM orders LIKE 'split_from_order_id'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

/**
 * Recalculate subtotal/SST/total from remaining order_items.
 *
 * @return array{subtotal:float,sst_rate:float,sst_jumlah:float,total:float}
 */
function recalcOrderMoney(int $orderId, array $shop): array
{
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(qty * harga_saat_order), 0) AS subtotal
         FROM order_items WHERE order_id = ?'
    );
    $stmt->execute([$orderId]);
    $subtotal = (float) ($stmt->fetchColumn() ?: 0);
    $totals = calculateTotals($subtotal, $shop);

    $upd = db()->prepare(
        'UPDATE orders
         SET subtotal = ?, sst_rate = ?, sst_jumlah = ?, total_harga = ?
         WHERE id = ?'
    );
    $upd->execute([
        $totals['subtotal'],
        $totals['sst_rate'],
        $totals['sst_jumlah'],
        $totals['total'],
        $orderId,
    ]);

    return $totals;
}

/**
 * Split selected items from an unpaid order into a new paid order.
 *
 * @param list<int> $itemIds
 * @return array{ok:bool,error?:string,source_order_id?:int,paid_order_id?:int,receipt?:array<string,mixed>|null}
 */
function splitOrderBill(int $shopId, int $orderId, array $itemIds, string $lang = 'my', ?string $guestName = null): array
{
    $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), static fn(int $id): bool => $id > 0)));
    if ($orderId <= 0 || $itemIds === []) {
        return ['ok' => false, 'error' => 'invalid_items'];
    }

    $shop = findShopById($shopId);
    if (!$shop) {
        return ['ok' => false, 'error' => 'shop_not_found'];
    }

    $pdo = db();
    $emailCol = orderCustomerEmailColumnExists() ? ', customer_email' : '';
    $sessionCol = true; // session_id may exist
    try {
        $hasSession = (bool) $pdo->query("SHOW COLUMNS FROM orders LIKE 'session_id'")->fetch();
    } catch (Throwable $e) {
        $hasSession = false;
    }
    $guestCol = true;
    try {
        $hasGuest = (bool) $pdo->query("SHOW COLUMNS FROM orders LIKE 'guest_token'")->fetch();
    } catch (Throwable $e) {
        $hasGuest = false;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT id, shop_id, table_id, status_order, status_bayar, jenis_hidang,
                    nama_pelanggan, sumber_order, sst_rate
                    " . ($hasSession ? ', session_id' : '') . "
                    " . ($hasGuest ? ', guest_token' : '') . "
                    {$emailCol}
             FROM orders
             WHERE id = ? AND shop_id = ? AND status_order != 'dibatalkan'
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([$orderId, $shopId]);
        $order = $stmt->fetch();
        if (!$order) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'order_not_found'];
        }
        if (($order['status_bayar'] ?? '') !== 'belum_bayar') {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'already_paid'];
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $itemStmt = $pdo->prepare(
            "SELECT id, order_id
             FROM order_items
             WHERE order_id = ? AND id IN ($placeholders)
             FOR UPDATE"
        );
        $itemStmt->execute(array_merge([$orderId], $itemIds));
        $selected = $itemStmt->fetchAll();
        if (count($selected) !== count($itemIds)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'invalid_items'];
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = ?');
        $countStmt->execute([$orderId]);
        $totalItems = (int) $countStmt->fetchColumn();
        if ($totalItems <= count($selected)) {
            // Paying everything — just mark paid (no empty residual order)
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'select_partial'];
        }

        $guest = $guestName !== null ? trim($guestName) : '';
        if ($guest === '') {
            $guest = trim((string) ($order['nama_pelanggan'] ?? ''));
        }
        if (mb_strlen($guest) > 40) {
            $guest = mb_substr($guest, 0, 40);
        }

        $now = appNow();
        $splitCol = orderSplitFromColumnExists();

        $cols = ['shop_id', 'table_id', 'waktu_order', 'status_order', 'status_bayar', 'jenis_hidang', 'nama_pelanggan', 'sumber_order', 'subtotal', 'sst_rate', 'sst_jumlah', 'total_harga', 'waktu_lunas'];
        $vals = [
            $shopId,
            (int) $order['table_id'],
            $now,
            ($order['status_order'] === 'menunggu') ? 'diproses' : (string) $order['status_order'],
            'lunas',
            (($order['jenis_hidang'] ?? 'dine_in') === 'takeaway') ? 'takeaway' : 'dine_in',
            $guest !== '' ? $guest : null,
            (($order['sumber_order'] ?? 'qr') === 'staf') ? 'staf' : 'qr',
            0,
            0,
            0,
            0,
            $now,
        ];

        if ($hasSession) {
            $cols[] = 'session_id';
            $vals[] = $order['session_id'] !== null ? (int) $order['session_id'] : null;
        }
        if ($hasGuest) {
            $cols[] = 'guest_token';
            $vals[] = $order['guest_token'] ?? null;
        }
        if (orderCustomerEmailColumnExists()) {
            $cols[] = 'customer_email';
            $vals[] = $order['customer_email'] ?? null;
        }
        if ($splitCol) {
            $cols[] = 'split_from_order_id';
            $vals[] = $orderId;
        }

        $ph = implode(',', array_fill(0, count($cols), '?'));
        $ins = $pdo->prepare('INSERT INTO orders (' . implode(',', $cols) . ') VALUES (' . $ph . ')');
        $ins->execute($vals);
        $paidOrderId = (int) $pdo->lastInsertId();

        $move = $pdo->prepare('UPDATE order_items SET order_id = ? WHERE id = ? AND order_id = ?');
        foreach ($selected as $row) {
            $move->execute([$paidOrderId, (int) $row['id'], $orderId]);
        }

        // Use shop SST settings for both bills (not frozen rate alone)
        recalcOrderMoney($paidOrderId, $shop);
        recalcOrderMoney($orderId, $shop);

        // If source was menunggu and still has items, bump to diproses so kitchen/ops stay consistent
        if (($order['status_order'] ?? '') === 'menunggu') {
            $pdo->prepare(
                "UPDATE orders SET status_order = 'diproses' WHERE id = ? AND shop_id = ? AND status_order = 'menunggu'"
            )->execute([$orderId, $shopId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'split_failed'];
    }

    $receipt = fetchOrderReceipt($paidOrderId, $shopId, $lang);

    return [
        'ok' => true,
        'source_order_id' => $orderId,
        'paid_order_id' => $paidOrderId,
        'receipt' => $receipt,
        'receipt_url' => orderReceiptUrl($paidOrderId, true),
    ];
}
