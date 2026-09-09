<?php
/**
 * Print Bridge — acknowledge claimed jobs.
 *
 * POST with header: X-Print-Bridge-Token: <shop token>
 * Body: { "jobs": [ { "id": 12, "ok": true }, { "id": 13, "ok": false, "error": "timeout" } ] }
 *
 * A failed job goes back to `pending` until PRINT_BRIDGE_MAX_ATTEMPTS, then `failed`.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/helpers.php';
require_once dirname(__DIR__, 3) . '/includes/print_bridge.php';

requirePost();
$shop = requirePrintBridgeShop();
$shopId = (int) $shop['id'];

$body = readJsonBody();
$rows = $body['jobs'] ?? [];
if (!is_array($rows)) {
    jsonError('Invalid jobs');
}

$acks = [];
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }
    $acks[] = [
        'id' => (int) ($row['id'] ?? 0),
        'ok' => !empty($row['ok']),
        'error' => (string) ($row['error'] ?? ''),
    ];
}

touchPrintBridgeSeen($shopId);
$updated = ackPrintJobs($shopId, $acks);

jsonResponse([
    'ok' => true,
    'updated' => $updated,
    'server_time' => appNow(),
]);
