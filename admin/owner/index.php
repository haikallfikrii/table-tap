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

$nav = 'home';
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>
<?php require __DIR__ . '/_nav.php'; ?>

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

<div class="table-grid">
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
  <a class="order-card" href="<?= e(baseUrl('admin/owner/users.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('manage_users')) ?></div>
    <p class="order-meta">kasir · dapur · minuman · waiter</p>
  </a>
  <a class="order-card" href="<?= e(baseUrl('admin/kasir.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('kasir_title')) ?></div>
  </a>
  <a class="order-card" href="<?= e(baseUrl('admin/dapur.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('dapur_title')) ?></div>
  </a>
  <a class="order-card" href="<?= e(baseUrl('admin/minuman.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('minuman_title')) ?></div>
  </a>
  <a class="order-card" href="<?= e(baseUrl('admin/waiter.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('waiter_title')) ?></div>
  </a>
</div>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
