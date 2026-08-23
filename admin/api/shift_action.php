<?php
/**
 * Open / close cash shift.
 * POST JSON: { action: open|close|save_hours, opening_float?, close_cash?, close_tng?, close_bank?, close_other?, close_notes?,
 *              hours_enabled?, open_time?, close_time? }
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/shift.php';

$user = requireLoginApi(['owner', 'kasir']);
$shopId = requireShopIdApi();
if (!shiftColumnsExist()) {
    jsonError('Feature not available', 503);
}

requirePost();
$body = readJsonBody();
$action = (string) ($body['action'] ?? '');
$pdo = db();
$shop = getShopOrFail($shopId);
$userId = (int) ($user['id'] ?? 0);

if ($action === 'save_hours') {
    if (($user['role'] ?? '') !== 'owner') {
        jsonError('Owner only', 403);
    }
    $enabled = !empty($body['hours_enabled']) ? 1 : 0;
    $openTime = normalizeTimeInput((string) ($body['open_time'] ?? ''));
    $closeTime = normalizeTimeInput((string) ($body['close_time'] ?? ''));
    if ($enabled && ($openTime === '' || $closeTime === '')) {
        jsonError(t('shift_hours_required'), 400);
    }
    $pdo->prepare(
        'UPDATE shops SET hours_enabled = ?, open_time = ?, close_time = ? WHERE id = ?'
    )->execute([
        $enabled,
        $enabled ? $openTime : null,
        $enabled ? $closeTime : null,
        $shopId,
    ]);
    jsonResponse(['ok' => true, 'hours_label' => shopHoursLabel(findShopById($shopId))]);
}

if ($action === 'open') {
    if (findOpenCashShift($shopId)) {
        jsonError(t('shift_already_open'), 409);
    }
    $float = round(max(0, (float) ($body['opening_float'] ?? 0)), 2);
    $biz = shopBusinessDate($shop);
    $now = appNow();
    $pdo->prepare(
        "INSERT INTO cash_shifts (shop_id, business_date, opened_at, opened_by, opening_float, status)
         VALUES (?, ?, ?, ?, ?, 'open')"
    )->execute([$shopId, $biz, $now, $userId > 0 ? $userId : null, $float]);
    jsonResponse([
        'ok' => true,
        'shift_id' => (int) $pdo->lastInsertId(),
        'message' => t('shift_opened'),
    ]);
}

if ($action === 'close') {
    $shift = findOpenCashShift($shopId);
    if (!$shift) {
        jsonError(t('shift_not_open'), 404);
    }
    $cash = round(max(0, (float) ($body['close_cash'] ?? 0)), 2);
    $tng = round(max(0, (float) ($body['close_tng'] ?? 0)), 2);
    $bank = round(max(0, (float) ($body['close_bank'] ?? 0)), 2);
    $other = round(max(0, (float) ($body['close_other'] ?? 0)), 2);
    $notes = trim((string) ($body['close_notes'] ?? ''));
    if (strlen($notes) > 500) {
        $notes = substr($notes, 0, 500);
    }
    $now = appNow();
    $sales = shiftSalesSummary($shopId, (string) $shift['opened_at'], $now);
    $expectedCash = shiftExpectedCash($shift, $sales);

    $pdo->prepare(
        "UPDATE cash_shifts
         SET closed_at = ?, closed_by = ?, close_cash = ?, close_tng = ?, close_bank = ?, close_other = ?,
             close_notes = ?, status = 'closed'
         WHERE id = ? AND shop_id = ? AND status = 'open'"
    )->execute([
        $now,
        $userId > 0 ? $userId : null,
        $cash,
        $tng,
        $bank,
        $other,
        $notes !== '' ? $notes : null,
        (int) $shift['id'],
        $shopId,
    ]);

    jsonResponse([
        'ok' => true,
        'message' => t('shift_closed'),
        'summary' => [
            'sales' => $sales,
            'expected_cash' => $expectedCash,
            'counted_cash' => $cash,
            'cash_variance' => round($cash - $expectedCash, 2),
            'counted_total' => round($cash + $tng + $bank + $other, 2),
            'sales_total' => $sales['total'],
        ],
    ]);
}

jsonError('Invalid action', 400);

function normalizeTimeInput(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^\d{1,2}:\d{2}$/', $raw)) {
        return $raw . ':00';
    }
    if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $raw)) {
        return $raw;
    }
    return '';
}
