<?php
/**
 * Owner panel — dashboard hub
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requireLogin(['owner']);

$user = currentUser();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('owner_title');
$showSound = false;
$adminScripts = [];

$pdo = db();

// Today summary
$today = date('Y-m-d');
$incomeStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_harga), 0) AS total
     FROM orders
     WHERE status_bayar = 'lunas' AND DATE(waktu_lunas) = ?"
);
$incomeStmt->execute([$today]);
$incomeToday = (float) $incomeStmt->fetchColumn();

$expStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(jumlah), 0) AS total FROM expenses WHERE tanggal = ?"
);
$expStmt->execute([$today]);
$expenseToday = (float) $expStmt->fetchColumn();

$menuCount = (int) $pdo->query('SELECT COUNT(*) FROM menu_items WHERE is_active = 1')->fetchColumn();
$tableCount = (int) $pdo->query("SELECT COUNT(*) FROM tables WHERE status = 'aktif'")->fetchColumn();

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
    <p class="order-meta">kasir · dapur · minuman</p>
  </a>
  <a class="order-card" href="<?= e(baseUrl('admin/kasir.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('kasir_title')) ?></div>
  </a>
  <a class="order-card" href="<?= e(baseUrl('admin/dapur.php')) ?>" style="text-decoration:none">
    <div class="table-num" style="font-size:1.4rem"><?= e(t('dapur_title')) ?></div>
  </a>
</div>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
