<?php
/**
 * Shop hours + cash shift (buka / tutup) for owner & kasir.
 */

declare(strict_types=1);

function shiftColumnsExist(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $ok = (bool) db()->query("SHOW TABLES LIKE 'cash_shifts'")->fetch();
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

function shopHoursEnabled(?array $shop): bool
{
    return shiftColumnsExist() && $shop && (int) ($shop['hours_enabled'] ?? 0) === 1;
}

/** Minutes since midnight from TIME or HH:MM string. */
function shopTimeToMinutes(?string $time): int
{
    $time = trim((string) $time);
    if ($time === '') {
        return 0;
    }
    $parts = explode(':', $time);
    return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
}

/**
 * Business calendar date — after midnight but before close_time counts as previous day
 * (e.g. open 16:00, close 02:00 → 01:30 still belongs to yesterday's business day).
 */
function shopBusinessDate(?array $shop, ?string $at = null): string
{
    $ts = $at !== null ? strtotime($at) : time();
    $ymd = date('Y-m-d', $ts);
    if (!shopHoursEnabled($shop)) {
        return $ymd;
    }
    $close = shopTimeToMinutes((string) ($shop['close_time'] ?? ''));
    $open = shopTimeToMinutes((string) ($shop['open_time'] ?? ''));
    if ($close <= $open && $close > 0) {
        $nowM = ((int) date('H', $ts)) * 60 + (int) date('i', $ts);
        if ($nowM < $close) {
            return date('Y-m-d', strtotime($ymd . ' -1 day'));
        }
    }
    return $ymd;
}

function shopIsOpenForOrders(?array $shop): bool
{
    if (!$shop || ($shop['status'] ?? '') !== 'aktif') {
        return false;
    }
    if (!shopHoursEnabled($shop)) {
        return true;
    }
    $open = shopTimeToMinutes((string) ($shop['open_time'] ?? ''));
    $close = shopTimeToMinutes((string) ($shop['close_time'] ?? ''));
    if ($open === $close) {
        return true;
    }
    $nowM = ((int) date('H')) * 60 + (int) date('i');
    if ($close <= $open) {
        return $nowM >= $open || $nowM < $close;
    }
    return $nowM >= $open && $nowM < $close;
}

function shopHoursLabel(?array $shop): string
{
    if (!shopHoursEnabled($shop)) {
        return '';
    }
    $open = substr((string) ($shop['open_time'] ?? ''), 0, 5);
    $close = substr((string) ($shop['close_time'] ?? ''), 0, 5);
    if ($open === '' || $close === '') {
        return '';
    }
    $overnight = shopTimeToMinutes($shop['close_time']) <= shopTimeToMinutes($shop['open_time']);
    return $open . ' – ' . $close . ($overnight ? ' (+1)' : '');
}

function findOpenCashShift(int $shopId): ?array
{
    if (!shiftColumnsExist()) {
        return null;
    }
    $stmt = db()->prepare(
        "SELECT * FROM cash_shifts WHERE shop_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$shopId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Paid order totals during shift grouped by payment_method. */
function shiftSalesSummary(int $shopId, string $openedAt, ?string $closedAt = null): array
{
    $end = $closedAt ?? appNow();
    $hasPay = orderDeliveryColumnsExist();
    $payCol = $hasPay ? ', payment_method' : '';
    $stmt = db()->prepare(
        "SELECT id, total_harga, status_bayar{$payCol}
         FROM orders
         WHERE shop_id = ? AND status_bayar = 'lunas'
           AND waktu_lunas >= ? AND waktu_lunas < ?
           AND status_order != 'dibatalkan'"
    );
    $stmt->execute([$shopId, $openedAt, $end]);
    $rows = $stmt->fetchAll();

    $total = 0.0;
    $byMethod = ['counter' => 0.0, 'cod' => 0.0, 'duitnow' => 0.0, 'other' => 0.0];
    foreach ($rows as $r) {
        $amt = (float) ($r['total_harga'] ?? 0);
        $total += $amt;
        $m = $hasPay ? (string) ($r['payment_method'] ?? 'counter') : 'counter';
        if (!isset($byMethod[$m])) {
            $byMethod['other'] += $amt;
        } else {
            $byMethod[$m] += $amt;
        }
    }

    $cashSales = $byMethod['counter'] + $byMethod['cod'];
    return [
        'order_count' => count($rows),
        'total' => round($total, 2),
        'counter' => round($byMethod['counter'], 2),
        'cod' => round($byMethod['cod'], 2),
        'duitnow' => round($byMethod['duitnow'], 2),
        'cash_sales' => round($cashSales, 2),
    ];
}

function shiftCloseTotals(array $shift): array
{
    $cash = (float) ($shift['close_cash'] ?? 0);
    $tng = (float) ($shift['close_tng'] ?? 0);
    $bank = (float) ($shift['close_bank'] ?? 0);
    $other = (float) ($shift['close_other'] ?? 0);
    return [
        'cash' => $cash,
        'tng' => $tng,
        'bank' => $bank,
        'other' => $other,
        'all' => round($cash + $tng + $bank + $other, 2),
    ];
}

/** Expected cash in drawer = opening float + counter + COD sales. */
function shiftExpectedCash(array $shift, ?array $sales = null): float
{
    $sales ??= shiftSalesSummary((int) $shift['shop_id'], (string) $shift['opened_at'], $shift['closed_at'] ?? null);
    return round((float) ($shift['opening_float'] ?? 0) + ($sales['cash_sales'] ?? 0), 2);
}

/** @return list<array<string, mixed>> */
function recentCashShifts(int $shopId, int $limit = 14): array
{
    if (!shiftColumnsExist()) {
        return [];
    }
    $stmt = db()->prepare(
        "SELECT * FROM cash_shifts WHERE shop_id = ? ORDER BY id DESC LIMIT ?"
    );
    $stmt->bindValue(1, $shopId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function assertShopAcceptingOrders(?array $shop): void
{
    if (!shopIsOpenForOrders($shop)) {
        jsonError(t('shop_closed_hours'), 403);
    }
}

/**
 * Inclusive business-day window [start, end) for a Y-m-d date.
 * Overnight hours (open 16:00, close 02:00) span into the next calendar day.
 *
 * @return array{0:string,1:string}
 */
function shopBusinessDayBounds(?array $shop, string $ymd): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        $ymd = date('Y-m-d');
    }
    if (!shopHoursEnabled($shop)) {
        return appDayBounds($ymd);
    }
    $openRaw = trim((string) ($shop['open_time'] ?? '00:00:00'));
    $closeRaw = trim((string) ($shop['close_time'] ?? '23:59:59'));
    $open = strlen($openRaw) === 5 ? $openRaw . ':00' : $openRaw;
    $close = strlen($closeRaw) === 5 ? $closeRaw . ':00' : $closeRaw;
    $openM = shopTimeToMinutes($openRaw);
    $closeM = shopTimeToMinutes($closeRaw);
    $start = $ymd . ' ' . $open;
    if ($closeM <= $openM && $closeM > 0) {
        $end = date('Y-m-d', strtotime($ymd . ' +1 day')) . ' ' . $close;
    } else {
        $end = $ymd . ' ' . $close;
    }
    return [$start, $end];
}

/**
 * Paid orders + summary for closing report (one business day).
 *
 * @return array{
 *   date:string,start:string,end:string,
 *   order_count:int,subtotal:float,sst:float,total:float,
 *   unpaid_count:int,unpaid_total:float,
 *   by_pay:array<string,float>,by_serve:array<string,float>,
 *   orders:list<array<string,mixed>>
 * }
 */
function dailyClosingReport(int $shopId, ?array $shop, string $ymd): array
{
    [$start, $end] = shopBusinessDayBounds($shop, $ymd);
    $pdo = db();
    $hasPay = orderDeliveryColumnsExist();
    $payCol = $hasPay ? ', o.payment_method' : '';
    $stmt = $pdo->prepare(
        "SELECT o.id, o.waktu_order, o.waktu_lunas, o.subtotal, o.sst_jumlah, o.total_harga,
                o.jenis_hidang, o.nama_pelanggan, t.nomor_meja{$payCol}
         FROM orders o
         INNER JOIN tables t ON t.id = o.table_id
         WHERE o.shop_id = ?
           AND o.status_bayar = 'lunas'
           AND o.status_order != 'dibatalkan'
           AND o.waktu_lunas >= ? AND o.waktu_lunas < ?
         ORDER BY o.waktu_lunas ASC, o.id ASC"
    );
    $stmt->execute([$shopId, $start, $end]);
    $orders = $stmt->fetchAll() ?: [];

    $subtotal = 0.0;
    $sst = 0.0;
    $total = 0.0;
    $byPay = ['counter' => 0.0, 'cod' => 0.0, 'duitnow' => 0.0, 'other' => 0.0];
    $byServe = ['dine_in' => 0.0, 'takeaway' => 0.0, 'delivery' => 0.0];
    foreach ($orders as $r) {
        $amt = (float) ($r['total_harga'] ?? 0);
        $subtotal += (float) ($r['subtotal'] ?? 0);
        $sst += (float) ($r['sst_jumlah'] ?? 0);
        $total += $amt;
        $serve = (string) ($r['jenis_hidang'] ?? 'dine_in');
        if (!isset($byServe[$serve])) {
            $byServe[$serve] = 0.0;
        }
        $byServe[$serve] += $amt;
        $m = $hasPay ? (string) ($r['payment_method'] ?? 'counter') : 'counter';
        if ($m === '' || $m === 'null') {
            $m = 'counter';
        }
        if (!isset($byPay[$m])) {
            $byPay['other'] += $amt;
        } else {
            $byPay[$m] += $amt;
        }
    }

    $unpaid = $pdo->prepare(
        "SELECT COUNT(*) AS c, COALESCE(SUM(total_harga), 0) AS t
         FROM orders
         WHERE shop_id = ?
           AND status_bayar = 'belum_bayar'
           AND status_order != 'dibatalkan'
           AND waktu_order >= ? AND waktu_order < ?"
    );
    $unpaid->execute([$shopId, $start, $end]);
    $u = $unpaid->fetch() ?: ['c' => 0, 't' => 0];

    return [
        'date' => $ymd,
        'start' => $start,
        'end' => $end,
        'order_count' => count($orders),
        'subtotal' => round($subtotal, 2),
        'sst' => round($sst, 2),
        'total' => round($total, 2),
        'unpaid_count' => (int) ($u['c'] ?? 0),
        'unpaid_total' => round((float) ($u['t'] ?? 0), 2),
        'by_pay' => [
            'counter' => round($byPay['counter'], 2),
            'cod' => round($byPay['cod'], 2),
            'duitnow' => round($byPay['duitnow'], 2),
            'other' => round($byPay['other'], 2),
        ],
        'by_serve' => [
            'dine_in' => round($byServe['dine_in'], 2),
            'takeaway' => round($byServe['takeaway'], 2),
            'delivery' => round($byServe['delivery'], 2),
        ],
        'orders' => $orders,
    ];
}
