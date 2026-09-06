<?php
/**
 * Cashier billing helpers — totals recalc + quantity-level split bill.
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
 * Normalize split payload: item_ids (full lines) and/or items:[{id,qty}].
 *
 * @param mixed $items
 * @param mixed $itemIds
 * @return array<int,int> map order_item_id => qty (qty -1 = take whole line)
 */
function normalizeSplitSelections(mixed $items, mixed $itemIds = null): array
{
    $map = [];
    if (is_array($items)) {
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);
            if ($id <= 0 || $qty <= 0) {
                continue;
            }
            $map[$id] = ($map[$id] ?? 0) + $qty;
        }
    }
    if (is_array($itemIds)) {
        foreach ($itemIds as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                continue;
            }
            // Legacy: whole line unless already set via items[]
            if (!isset($map[$id])) {
                $map[$id] = -1;
            }
        }
    }
    return $map;
}

/**
 * Split selected quantities from an unpaid order into a new paid order.
 *
 * @param array<int,int> $want map order_item_id => qty (−1 = whole line)
 * @return array{ok:bool,error?:string,source_order_id?:int,paid_order_id?:int,receipt?:array<string,mixed>|null}
 */
function splitOrderBill(int $shopId, int $orderId, array $want, string $lang = 'my', ?string $guestName = null): array
{
    // Allow legacy callers that still pass a flat list of item ids
    if ($want !== [] && array_values($want) === $want) {
        $looksLikeIds = true;
        foreach ($want as $v) {
            if (!is_int($v) && !(is_string($v) && ctype_digit((string) $v))) {
                $looksLikeIds = false;
                break;
            }
        }
        if ($looksLikeIds) {
            $want = normalizeSplitSelections(null, $want);
        }
    }

    $clean = [];
    foreach ($want as $k => $v) {
        $id = (int) $k;
        $qty = (int) $v;
        if ($id > 0 && ($qty > 0 || $qty === -1)) {
            $clean[$id] = $qty;
        }
    }
    $want = $clean;

    if ($orderId <= 0 || $want === []) {
        return ['ok' => false, 'error' => 'invalid_items'];
    }

    $shop = findShopById($shopId);
    if (!$shop) {
        return ['ok' => false, 'error' => 'shop_not_found'];
    }

    $pdo = db();
    $emailCol = orderCustomerEmailColumnExists() ? ', customer_email' : '';
    try {
        $hasSession = (bool) $pdo->query("SHOW COLUMNS FROM orders LIKE 'session_id'")->fetch();
    } catch (Throwable $e) {
        $hasSession = false;
    }
    try {
        $hasGuest = (bool) $pdo->query("SHOW COLUMNS FROM orders LIKE 'guest_token'")->fetch();
    } catch (Throwable $e) {
        $hasGuest = false;
    }

    require_once __DIR__ . '/stations.php';
    require_once __DIR__ . '/menu_categories.php';
    $snapStation = orderStationColumnExists();
    $snapMenuCat = orderMenuCategoryKodColumnExists();

    $itemSelect = 'id, order_id, menu_item_id, qty, catatan, status_item, harga_saat_order,
                   nama_saat_order_my, nama_saat_order_en, kategori_saat_order';
    if ($snapStation) {
        $itemSelect .= ', station_id_saat_order';
    }
    if ($snapMenuCat) {
        $itemSelect .= ', menu_category_kod_saat_order, menu_category_nama_my_saat_order, menu_category_nama_en_saat_order';
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

        $ids = array_keys($want);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $itemStmt = $pdo->prepare(
            "SELECT {$itemSelect}
             FROM order_items
             WHERE order_id = ? AND id IN ($placeholders)
             FOR UPDATE"
        );
        $itemStmt->execute(array_merge([$orderId], $ids));
        $selectedRows = $itemStmt->fetchAll();
        if (count($selectedRows) !== count($ids)) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'invalid_items'];
        }

        $byId = [];
        foreach ($selectedRows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $takePlan = []; // list of [row, takeQty, moveWhole]
        $selectedUnits = 0;
        foreach ($want as $itemId => $reqQty) {
            $row = $byId[$itemId];
            $avail = (int) $row['qty'];
            $take = ($reqQty === -1) ? $avail : min($avail, max(0, (int) $reqQty));
            if ($take <= 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'invalid_items'];
            }
            if ($take > $avail) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'invalid_items'];
            }
            $takePlan[] = [$row, $take, $take >= $avail];
            $selectedUnits += $take;
        }

        $totalUnitsStmt = $pdo->prepare('SELECT COALESCE(SUM(qty), 0) FROM order_items WHERE order_id = ?');
        $totalUnitsStmt->execute([$orderId]);
        $totalUnits = (int) $totalUnitsStmt->fetchColumn();
        if ($selectedUnits <= 0) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'invalid_items'];
        }
        if ($selectedUnits >= $totalUnits) {
            // Paying everything — use "Tandai lunas" instead of leaving an empty residual
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'select_partial'];
        }

        $guest = $guestName !== null ? trim($guestName) : '';
        if ($guest === '') {
            $guest = trim((string) ($order['nama_pelanggan'] ?? ''));
        }
        if (function_exists('mb_strlen') && mb_strlen($guest) > 40) {
            $guest = mb_substr($guest, 0, 40);
        } elseif (strlen($guest) > 40) {
            $guest = substr($guest, 0, 40);
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
            (($order['jenis_hidang'] ?? 'dine_in') === 'takeaway') ? 'takeaway'
                : ((($order['jenis_hidang'] ?? '') === 'delivery') ? 'delivery' : 'dine_in'),
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

        $moveWhole = $pdo->prepare('UPDATE order_items SET order_id = ? WHERE id = ? AND order_id = ?');
        $reduceQty = $pdo->prepare('UPDATE order_items SET qty = qty - ? WHERE id = ? AND order_id = ? AND qty >= ?');

        if ($snapStation && $snapMenuCat) {
            $insertPartial = $pdo->prepare(
                'INSERT INTO order_items
                 (order_id, menu_item_id, qty, catatan, status_item, harga_saat_order, nama_saat_order_my, nama_saat_order_en, kategori_saat_order, station_id_saat_order, menu_category_kod_saat_order, menu_category_nama_my_saat_order, menu_category_nama_en_saat_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
        } elseif ($snapStation) {
            $insertPartial = $pdo->prepare(
                'INSERT INTO order_items
                 (order_id, menu_item_id, qty, catatan, status_item, harga_saat_order, nama_saat_order_my, nama_saat_order_en, kategori_saat_order, station_id_saat_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
        } else {
            $insertPartial = $pdo->prepare(
                'INSERT INTO order_items
                 (order_id, menu_item_id, qty, catatan, status_item, harga_saat_order, nama_saat_order_my, nama_saat_order_en, kategori_saat_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
        }

        foreach ($takePlan as [$row, $take, $whole]) {
            $itemId = (int) $row['id'];
            if ($whole) {
                $moveWhole->execute([$paidOrderId, $itemId, $orderId]);
                continue;
            }
            $reduceQty->execute([$take, $itemId, $orderId, $take]);
            if ($reduceQty->rowCount() < 1) {
                throw new RuntimeException('qty_reduce_failed');
            }
            $args = [
                $paidOrderId,
                (int) $row['menu_item_id'],
                $take,
                $row['catatan'],
                (string) $row['status_item'],
                $row['harga_saat_order'],
                $row['nama_saat_order_my'],
                $row['nama_saat_order_en'],
                $row['kategori_saat_order'],
            ];
            if ($snapStation) {
                $args[] = $row['station_id_saat_order'] !== null ? (int) $row['station_id_saat_order'] : null;
            }
            if ($snapStation && $snapMenuCat) {
                $args[] = $row['menu_category_kod_saat_order'] ?? null;
                $args[] = $row['menu_category_nama_my_saat_order'] ?? null;
                $args[] = $row['menu_category_nama_en_saat_order'] ?? null;
            }
            $insertPartial->execute($args);
        }

        recalcOrderMoney($paidOrderId, $shop);
        recalcOrderMoney($orderId, $shop);

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
