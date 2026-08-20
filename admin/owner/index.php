<?php
/**
 * Owner panel — dashboard hub
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requireLogin(['owner']);

$user = currentUser();
$shopId = requireShopId();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('owner_title');
$showSound = false;
$adminScripts = [];

$pdo = db();
$shop = findShopById($shopId);
if ($shop) {
    $pageTitle = shopBrand($shop);
}

// Today summary (local MYT day bounds — avoids MySQL DATE() vs UTC mismatch)
$today = date('Y-m-d');
[$dayStart, $dayEnd] = appDayBounds($today);
$incomeStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_harga), 0) AS total
     FROM orders
     WHERE shop_id = ? AND status_bayar = 'lunas'
       AND waktu_lunas >= ? AND waktu_lunas < ?"
);
$incomeStmt->execute([$shopId, $dayStart, $dayEnd]);
$incomeToday = (float) $incomeStmt->fetchColumn();

$expStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(jumlah), 0) AS total FROM expenses WHERE shop_id = ? AND tanggal = ?"
);
$expStmt->execute([$shopId, $today]);
$expenseToday = (float) $expStmt->fetchColumn();

$menuCountStmt = $pdo->prepare('SELECT COUNT(*) FROM menu_items WHERE shop_id = ? AND is_active = 1');
$menuCountStmt->execute([$shopId]);
$menuCount = (int) $menuCountStmt->fetchColumn();

$tableCountStmt = $pdo->prepare("SELECT COUNT(*) FROM tables WHERE shop_id = ? AND status = 'aktif'");
$tableCountStmt->execute([$shopId]);
$tableCount = (int) $tableCountStmt->fetchColumn();

$ops = ownerOpsSnapshot($shopId, $shop);
$selfPickup = ($ops['fulfillment'] ?? 'waiter') === 'self_pickup';
$handoverUrl = $selfPickup ? baseUrl('admin/kasir.php') : baseUrl('admin/waiter.php');
$handoverTitle = $selfPickup ? t('ops_pickup') : t('ops_handover');
$handReadyLabel = $selfPickup ? t('ops_collect') : t('ops_ready');

$adminScripts = [assetUrl('js/live-poll.js'), assetUrl('js/owner-ops.js')];

$nav = 'home';

function ownerOpsChip(string $label, int $n, string $id, string $mod = ''): void
{
    $cls = 'ops-chip' . ($mod !== '' ? ' ' . $mod : '') . ($n <= 0 ? ' hidden' : '');
    echo '<span class="' . e($cls) . '">' . e($label) . ' <b id="' . e($id) . '">' . $n . '</b></span>';
}
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>
<?php require __DIR__ . '/_nav.php'; ?>
<?php require dirname(__DIR__, 2) . '/includes/staff_order_flash.php'; ?>

<div class="stat-row">
  <div class="stat-card">
    <div class="label"><?= e(t('income')) ?> (<?= e(t('filter_day')) ?>)</div>
    <div class="value"><?= e(formatMoney($incomeToday)) ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('expenses')) ?> (<?= e(t('filter_day')) ?>)</div>
    <div class="value"><?= e(formatMoney($expenseToday)) ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('profit')) ?></div>
    <div class="value"><?= e(formatMoney($incomeToday - $expenseToday)) ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('manage_menu')) ?> / <?= e(t('manage_tables')) ?></div>
    <div class="value" style="font-size:1.2rem"><?= $menuCount ?> · <?= $tableCount ?></div>
  </div>
</div>

<div class="ops-head">
  <h2><?= e(t('ops_live')) ?></h2>
  <p><?= e(t('ops_open')) ?></p>
</div>
<div class="ops-board" id="ops-board"
     data-poll-url="<?= e(baseUrl('admin/api/owner_ops_poll.php')) ?>"
     data-interval="<?= (int) ($config['poll_interval_ms'] ?? 4000) ?>">
  <?php foreach (($ops['stations'] ?? []) as $st): ?>
    <?php $sid = (int) $st['id']; ?>
    <a class="ops-card<?= $st['items'] > 0 ? ' is-busy' : '' ?>" id="ops-card-st-<?= $sid ?>" href="<?= e($st['url']) ?>">
      <div class="ops-kicker"><?= e($st['name']) ?></div>
      <div class="ops-value"><span id="ops-st-items-<?= $sid ?>"><?= (int) $st['items'] ?></span></div>
      <div class="ops-sub"><span id="ops-st-orders-<?= $sid ?>"><?= (int) $st['orders'] ?></span> <?= e(t('ops_orders_n')) ?></div>
      <div class="ops-chips">
        <?php ownerOpsChip(t('ops_queue'), (int) $st['menunggu'], 'ops-st-queue-' . $sid); ?>
        <?php ownerOpsChip(t('ops_cooking'), (int) $st['sedang_dimasak'], 'ops-st-cook-' . $sid, 'cook'); ?>
      </div>
    </a>
  <?php endforeach; ?>
  <a class="ops-card<?= $ops['handover']['items'] > 0 ? ' is-busy' : '' ?>" id="ops-card-hand" href="<?= e($handoverUrl) ?>">
    <div class="ops-kicker"><?= e($handoverTitle) ?></div>
    <div class="ops-value"><span id="ops-hand-items"><?= (int) $ops['handover']['items'] ?></span></div>
    <div class="ops-sub"><span id="ops-hand-orders"><?= (int) $ops['handover']['orders'] ?></span> <?= e(t('ops_orders_n')) ?></div>
    <div class="ops-chips">
      <?php ownerOpsChip($handReadyLabel, (int) $ops['handover']['siap'], 'ops-hand-ready', 'ready'); ?>
      <?php if (!$selfPickup): ?>
        <?php ownerOpsChip(t('ops_delivering'), (int) $ops['handover']['diambil'], 'ops-hand-deliver', 'deliver'); ?>
      <?php endif; ?>
    </div>
  </a>
  <a class="ops-card<?= $ops['unpaid']['orders'] > 0 ? ' is-busy is-alert' : '' ?>" id="ops-card-unpaid" href="<?= e(baseUrl('admin/kasir.php')) ?>">
    <div class="ops-kicker"><?= e(t('ops_unpaid')) ?></div>
    <div class="ops-value"><span id="ops-unpaid-n"><?= (int) $ops['unpaid']['orders'] ?></span></div>
    <div class="ops-sub" id="ops-unpaid-amt"><?= e($ops['unpaid']['amount_fmt']) ?></div>
  </a>
</div>

<div class="table-grid">
  <a class="order-card" href="<?= e(baseUrl('admin/staff_order.php?from=owner')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('staff_order')) ?></div>
    <p class="order-meta"><?= e(t('staff_pick_table')) ?></p>
  </a>
  <a class="order-card" href="<?= e(baseUrl('admin/owner/menu.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('manage_menu')) ?></div>
    <p class="order-meta">CRUD · stok</p>
  </a>
  <a class="order-card" href="<?= e(baseUrl('admin/owner/tables.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('manage_tables')) ?></div>
    <p class="order-meta">QR code</p>
  </a>
  <a class="order-card" href="<?= e(baseUrl('admin/owner/reports.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('reports')) ?></div>
    <p class="order-meta"><?= e(t('income')) ?> / <?= e(t('expenses')) ?></p>
  </a>
  <a class="order-card" href="<?= e(baseUrl('admin/owner/stations.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('manage_stations')) ?></div>
    <p class="order-meta"><?= e(t('stations_hint_short')) ?></p>
  </a>
  <a class="order-card" href="<?= e(baseUrl('admin/owner/users.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('manage_users')) ?></div>
    <p class="order-meta">kasir · stesen · waiter</p>
  </a>
  <a class="order-card" href="<?= e(baseUrl('admin/kasir.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('kasir_title')) ?></div>
  </a>
  <?php foreach (($ops['stations'] ?? []) as $st): ?>
    <a class="order-card" href="<?= e($st['url']) ?>" style="text-decoration:none">
      <div class="table-num" style="font-size:1.4rem"><?= e($st['name']) ?></div>
    </a>
  <?php endforeach; ?>
  <a class="order-card" href="<?= e(baseUrl('admin/waiter.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('waiter_title')) ?></div>
  </a>
</div>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
