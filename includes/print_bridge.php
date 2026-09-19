<?php
/**
 * Print Bridge — job queue for Wi-Fi / LAN ESC/POS printers.
 *
 * The kasir tablet stays on the HTTPS web app. Chrome cannot call
 * http://127.0.0.1 from an HTTPS page (mixed content), so tickets are queued
 * here and the Android bridge APK polls this server over HTTPS, then pushes
 * raw ESC/POS to each printer over TCP :9100.
 *
 * Bluetooth printing is untouched — both paths can run at the same time.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/shop.php';
require_once __DIR__ . '/stations.php';
require_once __DIR__ . '/menu_categories.php';

const PRINT_BRIDGE_MAX_ATTEMPTS = 5;
const PRINT_BRIDGE_STALE_SECONDS = 120;
const PRINT_BRIDGE_KEEP_HOURS = 48;
const PRINT_BRIDGE_CLAIM_MAX = 20;

function printJobsTableExists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW TABLES LIKE 'print_jobs'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function printBridgeColumnsExist(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $exists = (bool) db()->query("SHOW COLUMNS FROM shops LIKE 'print_bridge_token'")->fetch();
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function printBridgeReady(): bool
{
    return printJobsTableExists() && printBridgeColumnsExist();
}

function shopPrintBridgeEnabled(?array $shop): bool
{
    if (!$shop || !printBridgeReady()) {
        return false;
    }
    return (int) ($shop['print_bridge_enabled'] ?? 0) === 1
        && trim((string) ($shop['print_bridge_token'] ?? '')) !== '';
}

/** Create the device token on first use so owners never see an empty field. */
function ensureShopPrintBridgeToken(array $shop): string
{
    if (!printBridgeColumnsExist()) {
        return '';
    }
    $token = trim((string) ($shop['print_bridge_token'] ?? ''));
    if ($token !== '') {
        return $token;
    }
    return regenerateShopPrintBridgeToken((int) $shop['id']);
}

function regenerateShopPrintBridgeToken(int $shopId): string
{
    if (!printBridgeColumnsExist() || $shopId <= 0) {
        return '';
    }
    $token = generateToken(16);
    db()->prepare('UPDATE shops SET print_bridge_token = ? WHERE id = ?')->execute([$token, $shopId]);
    return $token;
}

function findShopByPrintBridgeToken(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || strlen($token) < 16 || !printBridgeReady()) {
        return null;
    }
    $stmt = db()->prepare(
        "SELECT id FROM shops
         WHERE print_bridge_token = ? AND print_bridge_enabled = 1 AND status = 'aktif'
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $shopId = (int) ($stmt->fetchColumn() ?: 0);
    return $shopId > 0 ? findShopById($shopId) : null;
}

function touchPrintBridgeSeen(int $shopId): void
{
    if (!printBridgeColumnsExist()) {
        return;
    }
    try {
        db()->prepare('UPDATE shops SET print_bridge_seen_at = ? WHERE id = ?')
            ->execute([appNow(), $shopId]);
    } catch (Throwable $e) {
        // never block a poll on bookkeeping
    }
}

/** Words printed on the ticket, resolved server-side so the APK stays dumb. */
function printBridgeLabels(string $lang): array
{
    $en = $lang === 'en';
    return [
        'table' => $en ? 'Table' : 'Meja',
        'order' => $en ? 'Order' : 'Order',
        'guest' => $en ? 'Guest' : 'Pelanggan',
        'kitchen_ticket' => $en ? 'KITCHEN TICKET' : 'TIKET DAPUR',
        'receipt' => $en ? 'RECEIPT' : 'RESIT',
        'paid' => $en ? 'Paid' : 'Dibayar',
        'subtotal' => $en ? 'Subtotal' : 'Subjumlah',
        'total' => $en ? 'Total' : 'Jumlah',
        'split_from' => $en ? 'Split from' : 'Bahagi dari',
        'thank_you' => $en ? 'Thank you!' : 'Terima kasih!',
    ];
}

function printBridgeServeLabel(string $jenisHidang, string $lang): string
{
    $en = $lang === 'en';
    return match ($jenisHidang) {
        'takeaway' => $en ? 'Takeaway' : 'Bungkus',
        'delivery' => $en ? 'Delivery' : 'Delivery',
        default => $en ? 'Dine in' : 'Makan sini',
    };
}

/**
 * Queue one ticket. Returns the job id, or null when the bridge is off.
 *
 * @param array<string,mixed> $payload
 */
function enqueuePrintJob(
    int $shopId,
    string $stationKod,
    string $jenis,
    array $payload,
    ?int $orderId = null
): ?int {
    if (!printJobsTableExists() || $shopId <= 0) {
        return null;
    }
    $stationKod = substr(trim($stationKod) !== '' ? trim($stationKod) : 'kasir', 0, 40);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return null;
    }
    try {
        db()->prepare(
            "INSERT INTO print_jobs (shop_id, station_kod, jenis, order_id, payload_format, payload, status, created_at)
             VALUES (?, ?, ?, ?, 'json', ?, 'pending', ?)"
        )->execute([$shopId, $stationKod, substr($jenis, 0, 20), $orderId, $json, appNow()]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) {
        return null;
    }
}

function printJobExistsForOrder(int $shopId, int $orderId, string $jenis): bool
{
    if (!printJobsTableExists()) {
        return false;
    }
    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM print_jobs WHERE shop_id = ? AND order_id = ? AND jenis = ? LIMIT 1'
        );
        $stmt->execute([$shopId, $orderId, $jenis]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return true; // fail closed: never double-print
    }
}

/**
 * Queue kitchen / drinks / station tickets for one order.
 * Idempotent: an order is only queued once, so a later payment confirm is safe.
 */
function enqueueKitchenPrintJobs(int $shopId, int $orderId, ?array $shop = null, string $lang = 'my'): int
{
    $shop = $shop ?? findShopById($shopId);
    if (!shopPrintBridgeEnabled($shop) || $orderId <= 0) {
        return 0;
    }
    if (printJobExistsForOrder($shopId, $orderId, 'kitchen')) {
        return 0;
    }

    try {
        $pdo = db();
        $ord = $pdo->prepare(
            "SELECT o.id, o.waktu_order, o.jenis_hidang, o.nama_pelanggan, o.status_bayar,
                    t.nomor_meja
             FROM orders o
             INNER JOIN tables t ON t.id = o.table_id
             WHERE o.id = ? AND o.shop_id = ? AND o.status_order != 'dibatalkan'
             LIMIT 1"
        );
        $ord->execute([$orderId, $shopId]);
        $order = $ord->fetch();
        if (!$order) {
            return 0;
        }

        $hasStationCol = orderStationColumnExists();
        $hasCatCol = orderMenuCategoryKodColumnExists();
        $stationSelect = $hasStationCol ? 'oi.station_id_saat_order' : 'NULL AS station_id_saat_order';
        $catSelect = $hasCatCol
            ? 'COALESCE(oi.menu_category_kod_saat_order, mc.kod) AS menu_category_kod,
               COALESCE(oi.menu_category_nama_my_saat_order, mc.nama_my) AS menu_category_nama_my,
               COALESCE(oi.menu_category_nama_en_saat_order, mc.nama_en) AS menu_category_nama_en'
            : 'mc.kod AS menu_category_kod,
               mc.nama_my AS menu_category_nama_my,
               mc.nama_en AS menu_category_nama_en';

        $itemStmt = $pdo->prepare(
            "SELECT oi.id, oi.qty, oi.catatan, oi.nama_saat_order_my, oi.nama_saat_order_en,
                    oi.kategori_saat_order, {$stationSelect}, {$catSelect}
             FROM order_items oi
             LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
             LEFT JOIN menu_categories mc ON mc.id = mi.menu_category_id
             WHERE oi.order_id = ?
             ORDER BY oi.id ASC"
        );
        $itemStmt->execute([$orderId]);
        $items = $itemStmt->fetchAll();
        if (!$items) {
            return 0;
        }

        $stations = [];
        foreach (shopStations($shopId, false) as $st) {
            $stations[(int) $st['id']] = $st;
        }
        $byKod = [];
        foreach ($stations as $st) {
            $byKod[(string) $st['kod']] = $st;
        }

        $labels = printBridgeLabels($lang);
        $printer = shopPrinterSettings($shop);
        $serve = printBridgeServeLabel((string) ($order['jenis_hidang'] ?? 'dine_in'), $lang);
        $tickets = [];

        foreach ($items as $it) {
            $kategori = (string) ($it['kategori_saat_order'] ?? 'makanan');
            $stationId = (int) ($it['station_id_saat_order'] ?? 0);
            $station = $stations[$stationId] ?? null;
            if (!$station) {
                $fallbackKod = $kategori === 'minuman' ? 'minuman' : 'dapur';
                $station = $byKod[$fallbackKod] ?? [
                    'id' => 0,
                    'kod' => $fallbackKod,
                    'nama_my' => $fallbackKod === 'minuman' ? 'Minuman' : 'Dapur',
                    'nama_en' => $fallbackKod === 'minuman' ? 'Drinks' : 'Kitchen',
                ];
            }
            $catName = $lang === 'en'
                ? (string) ($it['menu_category_nama_en'] ?? $it['menu_category_nama_my'] ?? '')
                : (string) ($it['menu_category_nama_my'] ?? $it['menu_category_nama_en'] ?? '');
            $meta = kitchenPrintTicketMeta(
                $station,
                $kategori,
                isset($it['menu_category_kod']) ? (string) $it['menu_category_kod'] : null,
                $catName !== '' ? $catName : null,
                $lang
            );

            $stationKod = (string) ($station['kod'] ?? 'dapur');
            $stationName = stationLabel($station, $lang);
            $hubMode = !empty($printer['kasir_print_hub']);
            // Hub (1 printer at kasir): one slip per station, never merge.
            // Multi-printer: keep category splits within a station (e.g. drinks counter).
            $key = $hubMode ? $stationKod : ($stationKod . '|' . $meta['ticket_group']);
            if (!isset($tickets[$key])) {
                $tickets[$key] = [
                    'type' => 'kitchen',
                    'station' => $stationKod,
                    'station_name' => $stationName,
                    'ticket_label' => $hubMode
                        ? ($stationName !== '' ? $stationName : $meta['ticket_label'])
                        : $meta['ticket_label'],
                    'shop_name' => (string) ($shop['nama_kedai'] ?? 'TableTap'),
                    'order_id' => $orderId,
                    'table' => (string) ($order['nomor_meja'] ?? '-'),
                    'serve' => $serve,
                    'guest' => (string) ($order['nama_pelanggan'] ?? ''),
                    'time' => (string) ($order['waktu_order'] ?? ''),
                    'beep' => (int) $printer['beep_kitchen'],
                    'lang' => $lang,
                    'labels' => $labels,
                    'items' => [],
                ];
            }
            $tickets[$key]['items'][] = [
                'qty' => (int) $it['qty'],
                'name' => (string) ($lang === 'en' ? $it['nama_saat_order_en'] : $it['nama_saat_order_my']),
                'note' => (string) ($it['catatan'] ?? ''),
            ];
        }

        // Hub: all slips print on the kasir device; payload still names the real station.
        $routeKod = !empty($printer['kasir_print_hub']) ? 'kasir' : null;

        $queued = 0;
        foreach ($tickets as $ticket) {
            $target = $routeKod ?? (string) $ticket['station'];
            if (enqueuePrintJob($shopId, $target, 'kitchen', $ticket, $orderId) !== null) {
                $queued++;
            }
        }
        return $queued;
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Queue the kasir receipt for a paid order. Honours the "auto-print on paid" setting.
 */
function enqueueReceiptPrintJob(
    int $shopId,
    int $orderId,
    ?array $shop = null,
    string $lang = 'my',
    bool $force = false
): ?int {
    $shop = $shop ?? findShopById($shopId);
    if (!shopPrintBridgeEnabled($shop) || $orderId <= 0) {
        return null;
    }
    $printer = shopPrinterSettings($shop);
    if (!$force && !$printer['kasir_print_on_paid']) {
        return null;
    }
    if (!$force && printJobExistsForOrder($shopId, $orderId, 'receipt')) {
        return null;
    }

    require_once __DIR__ . '/receipt.php';
    $receipt = fetchOrderReceipt($orderId, $shopId, $lang);
    if (!$receipt) {
        return null;
    }

    $labels = printBridgeLabels($lang);
    $items = [];
    foreach ($receipt['items'] ?? [] as $it) {
        $items[] = [
            'qty' => (int) ($it['qty'] ?? 1),
            'name' => (string) ($it['nama'] ?? ''),
            'note' => (string) ($it['catatan'] ?? ''),
            'amount' => formatMoney((float) ($it['line_total'] ?? 0)),
        ];
    }
    $paidAt = trim((string) ($receipt['waktu_lunas'] ?? ''));
    if ($paidAt === '') {
        $paidAt = (string) ($receipt['waktu_order'] ?? '');
    }

    $payload = [
        'type' => 'receipt',
        'station' => 'kasir',
        'shop_name' => (string) ($receipt['shop_name'] ?? 'TableTap'),
        'order_id' => $orderId,
        'table' => (string) ($receipt['nomor_meja'] ?? '-'),
        'paid_at' => $paidAt,
        'guest' => (string) ($receipt['nama_pelanggan'] ?? ''),
        'serve' => printBridgeServeLabel((string) ($receipt['jenis_hidang'] ?? 'dine_in'), $lang),
        'split_from' => $receipt['split_from_order_id'] ?? null,
        'items' => $items,
        'subtotal' => formatMoney((float) ($receipt['subtotal'] ?? 0)),
        'sst_label' => 'SST (' . number_format((float) ($receipt['sst_rate'] ?? 0), 2) . '%)',
        'sst' => (float) ($receipt['sst_jumlah'] ?? 0) > 0
            ? formatMoney((float) $receipt['sst_jumlah'])
            : '',
        'total' => formatMoney((float) ($receipt['total_harga'] ?? 0)),
        'beep' => (int) $printer['beep_kasir'],
        'lang' => $lang,
        'labels' => $labels,
    ];

    return enqueuePrintJob($shopId, 'kasir', 'receipt', $payload, $orderId);
}

/** "Test print" button in owner settings / bridge app. */
function enqueueTestPrintJob(int $shopId, string $stationKod, ?array $shop = null, string $lang = 'my'): ?int
{
    $shop = $shop ?? findShopById($shopId);
    if (!shopPrintBridgeEnabled($shop)) {
        return null;
    }
    $labels = printBridgeLabels($lang);
    $payload = [
        'type' => 'test',
        'station' => $stationKod,
        'station_name' => strtoupper($stationKod),
        'ticket_label' => strtoupper($stationKod),
        'shop_name' => (string) ($shop['nama_kedai'] ?? 'TableTap'),
        'order_id' => 0,
        'table' => '0',
        'serve' => $lang === 'en' ? 'Test print' : 'Cuba cetak',
        'guest' => '',
        'time' => appNow(),
        'beep' => 1,
        'lang' => $lang,
        'labels' => $labels,
        'items' => [
            [
                'qty' => 1,
                'name' => $lang === 'en' ? 'Test print OK' : 'Test print OK',
                'note' => '',
            ],
        ],
    ];
    return enqueuePrintJob($shopId, $stationKod, 'test', $payload, null);
}

/** Put jobs abandoned by a crashed / disconnected bridge back in the queue. */
function requeueStalePrintJobs(int $shopId): void
{
    if (!printJobsTableExists()) {
        return;
    }
    try {
        $cutoff = date('Y-m-d H:i:s', strtotime(appNow()) - PRINT_BRIDGE_STALE_SECONDS);
        db()->prepare(
            "UPDATE print_jobs
             SET status = 'pending', claim_token = NULL
             WHERE shop_id = ? AND status = 'printing' AND claimed_at < ? AND attempts < ?"
        )->execute([$shopId, $cutoff, PRINT_BRIDGE_MAX_ATTEMPTS]);

        db()->prepare(
            "UPDATE print_jobs
             SET status = 'failed', claim_token = NULL, last_error = 'max_attempts'
             WHERE shop_id = ? AND status = 'printing' AND claimed_at < ? AND attempts >= ?"
        )->execute([$shopId, $cutoff, PRINT_BRIDGE_MAX_ATTEMPTS]);
    } catch (Throwable $e) {
        // queue keeps working with whatever is still pending
    }
}

function purgeOldPrintJobs(int $shopId): void
{
    if (!printJobsTableExists()) {
        return;
    }
    try {
        $cutoff = date('Y-m-d H:i:s', strtotime(appNow()) - PRINT_BRIDGE_KEEP_HOURS * 3600);
        db()->prepare(
            "DELETE FROM print_jobs
             WHERE shop_id = ? AND status IN ('done','failed') AND created_at < ?
             LIMIT 200"
        )->execute([$shopId, $cutoff]);
    } catch (Throwable $e) {
        // housekeeping only
    }
}

/**
 * Atomically hand pending jobs to one bridge device.
 *
 * @param list<string> $stationKods empty = every station this shop prints to
 * @return list<array<string,mixed>>
 */
function claimPrintJobs(int $shopId, array $stationKods, int $limit = 5): array
{
    if (!printJobsTableExists() || $shopId <= 0) {
        return [];
    }
    $limit = max(1, min(PRINT_BRIDGE_CLAIM_MAX, $limit));
    requeueStalePrintJobs($shopId);

    $claim = bin2hex(random_bytes(16));
    $params = [appNow(), $claim, $shopId];
    $filter = '';
    $clean = [];
    foreach ($stationKods as $kod) {
        $kod = substr(trim((string) $kod), 0, 40);
        if ($kod !== '') {
            $clean[$kod] = true;
        }
    }
    if ($clean !== []) {
        $kods = array_keys($clean);
        $filter = ' AND station_kod IN (' . implode(',', array_fill(0, count($kods), '?')) . ')';
        $params = array_merge($params, $kods);
    }

    try {
        $upd = db()->prepare(
            "UPDATE print_jobs
             SET status = 'printing', attempts = attempts + 1, claimed_at = ?, claim_token = ?
             WHERE shop_id = ? AND status = 'pending'{$filter}
             ORDER BY id ASC
             LIMIT {$limit}"
        );
        $upd->execute($params);
        if ($upd->rowCount() === 0) {
            return [];
        }

        $sel = db()->prepare(
            'SELECT id, station_kod, jenis, order_id, payload_format, payload, attempts
             FROM print_jobs
             WHERE shop_id = ? AND claim_token = ?
             ORDER BY id ASC'
        );
        $sel->execute([$shopId, $claim]);

        $jobs = [];
        foreach ($sel->fetchAll() as $row) {
            $jobs[] = [
                'id' => (int) $row['id'],
                'station' => (string) $row['station_kod'],
                'jenis' => (string) $row['jenis'],
                'order_id' => $row['order_id'] !== null ? (int) $row['order_id'] : null,
                'format' => (string) $row['payload_format'],
                'attempts' => (int) $row['attempts'],
                'payload' => json_decode((string) $row['payload'], true) ?: [],
            ];
        }
        return $jobs;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Mark claimed jobs done or failed.
 *
 * @param list<array{id:int,ok:bool,error?:string}> $acks
 */
function ackPrintJobs(int $shopId, array $acks): int
{
    if (!printJobsTableExists() || $acks === []) {
        return 0;
    }
    $done = db()->prepare(
        "UPDATE print_jobs
         SET status = 'done', printed_at = ?, claim_token = NULL, last_error = NULL
         WHERE id = ? AND shop_id = ? AND status = 'printing'"
    );
    $retry = db()->prepare(
        "UPDATE print_jobs
         SET status = IF(attempts >= ?, 'failed', 'pending'), claim_token = NULL, last_error = ?
         WHERE id = ? AND shop_id = ? AND status = 'printing'"
    );

    $count = 0;
    foreach ($acks as $ack) {
        $id = (int) ($ack['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        try {
            if (!empty($ack['ok'])) {
                $done->execute([appNow(), $id, $shopId]);
                $count += $done->rowCount();
            } else {
                $error = substr(trim((string) ($ack['error'] ?? 'print_failed')), 0, 255);
                $retry->execute([PRINT_BRIDGE_MAX_ATTEMPTS, $error, $id, $shopId]);
                $count += $retry->rowCount();
            }
        } catch (Throwable $e) {
            // skip this ack, the stale sweep will recover the job
        }
    }
    return $count;
}

/** @return array{pending:int,printing:int,failed:int,last_seen:?string} */
function printBridgeQueueStats(int $shopId): array
{
    $out = ['pending' => 0, 'printing' => 0, 'failed' => 0, 'last_seen' => null];
    if (!printBridgeReady()) {
        return $out;
    }
    try {
        $stmt = db()->prepare(
            "SELECT status, COUNT(*) AS n FROM print_jobs
             WHERE shop_id = ? AND status IN ('pending','printing','failed')
             GROUP BY status"
        );
        $stmt->execute([$shopId]);
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }
        $seen = db()->prepare('SELECT print_bridge_seen_at FROM shops WHERE id = ? LIMIT 1');
        $seen->execute([$shopId]);
        $val = $seen->fetchColumn();
        $out['last_seen'] = $val !== false && $val !== null ? (string) $val : null;
    } catch (Throwable $e) {
        // stats are cosmetic
    }
    return $out;
}

/**
 * Resolve the calling bridge device from its X-Print-Bridge-Token header.
 * Exits with JSON 401/403 when the token is missing, wrong, or the bridge is off.
 */
function requirePrintBridgeShop(): array
{
    if (!printBridgeReady()) {
        jsonError('Print bridge not installed', 503);
    }
    $token = trim((string) ($_SERVER['HTTP_X_PRINT_BRIDGE_TOKEN'] ?? ''));
    if ($token === '') {
        $token = trim((string) ($_GET['token'] ?? ''));
    }
    if ($token === '') {
        jsonError('Missing bridge token', 401);
    }
    $shop = findShopByPrintBridgeToken($token);
    if (!$shop) {
        jsonError('Invalid bridge token', 403);
    }
    return $shop;
}
