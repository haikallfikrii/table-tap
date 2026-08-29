<?php
/**
 * Owner — income / expenses / profit reports
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requireLogin(['owner']);

$user = currentUser();
$shopId = requireShopId();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('reports');
$showSound = false;
$adminScripts = [];
$nav = 'reports';
$error = '';
$pdo = db();

$filter = $_GET['filter'] ?? 'day';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$today = date('Y-m-d');
switch ($filter) {
    case 'week':
        $dateFrom = date('Y-m-d', strtotime('monday this week'));
        $dateTo = $today;
        break;
    case 'month':
        $dateFrom = date('Y-m-01');
        $dateTo = $today;
        break;
    case 'custom':
        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : $today;
        $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : $today;
        break;
    default:
        $filter = 'day';
        $dateFrom = $today;
        $dateTo = $today;
}

// Add expense
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'add_expense') {
    try {
        $kategori = trim((string) ($_POST['kategori'] ?? ''));
        $jumlah = (float) ($_POST['jumlah'] ?? 0);
        $tanggal = (string) ($_POST['tanggal'] ?? $today);
        $catatan = trim((string) ($_POST['catatan'] ?? ''));
        if ($kategori === '' || $jumlah <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            throw new RuntimeException('Data pengeluaran tidak lengkap');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO expenses (shop_id, kategori, jumlah, tanggal, catatan, created_by) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$shopId, $kategori, $jumlah, $tanggal, $catatan ?: null, $user['id']]);
        redirect(baseUrl('admin/owner/reports.php?filter=' . urlencode($filter) . '&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo) . '&ok=1'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'delete_expense') {
    $id = (int) ($_POST['id'] ?? 0);
    $pdo->prepare('DELETE FROM expenses WHERE id = ? AND shop_id = ?')->execute([$id, $shopId]);
    redirect(baseUrl('admin/owner/reports.php?filter=' . urlencode($filter) . '&ok=1'));
}

[$rangeStart] = appDayBounds($dateFrom);
[, $rangeEnd] = appDayBounds($dateTo);

$inc = $pdo->prepare(
    "SELECT COALESCE(SUM(total_harga), 0)
     FROM orders
     WHERE shop_id = ? AND status_bayar = 'lunas'
       AND status_order != 'dibatalkan'
       AND waktu_lunas >= ? AND waktu_lunas < ?"
);
$inc->execute([$shopId, $rangeStart, $rangeEnd]);
$income = (float) $inc->fetchColumn();

$exp = $pdo->prepare(
    'SELECT COALESCE(SUM(jumlah), 0) FROM expenses WHERE shop_id = ? AND tanggal BETWEEN ? AND ?'
);
$exp->execute([$shopId, $dateFrom, $dateTo]);
$expenses = (float) $exp->fetchColumn();

$ordersList = $pdo->prepare(
    "SELECT o.id, o.total_harga, o.subtotal, o.sst_jumlah, o.waktu_lunas, t.nomor_meja
     FROM orders o
     INNER JOIN tables t ON t.id = o.table_id
     WHERE o.shop_id = ? AND o.status_bayar = 'lunas'
       AND o.status_order != 'dibatalkan'
       AND o.waktu_lunas >= ? AND o.waktu_lunas < ?
     ORDER BY o.waktu_lunas DESC
     LIMIT 100"
);
$ordersList->execute([$shopId, $rangeStart, $rangeEnd]);
$paidOrders = $ordersList->fetchAll();

$expList = $pdo->prepare(
    'SELECT * FROM expenses WHERE shop_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal DESC, id DESC LIMIT 100'
);
$expList->execute([$shopId, $dateFrom, $dateTo]);
$expenseRows = $expList->fetchAll();
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>
<?php require __DIR__ . '/_nav.php'; ?>

<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
  <?php foreach (['day' => t('filter_day'), 'week' => t('filter_week'), 'month' => t('filter_month')] as $key => $label): ?>
    <a class="btn <?= $filter === $key ? 'btn-primary' : 'btn-secondary' ?> btn-sm"
       href="?filter=<?= e($key) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-bottom:20px" class="order-card">
  <input type="hidden" name="filter" value="custom">
  <div class="form-group" style="margin:0">
    <label><?= e(t('date')) ?> from</label>
    <input type="date" name="from" value="<?= e($dateFrom) ?>">
  </div>
  <div class="form-group" style="margin:0">
    <label>to</label>
    <input type="date" name="to" value="<?= e($dateTo) ?>">
  </div>
  <button type="submit" class="btn btn-secondary"><?= e(t('filter_custom')) ?></button>
</form>

<div class="stat-row">
  <div class="stat-card">
    <div class="label"><?= e(t('income')) ?></div>
    <div class="value"><?= e(formatMoney($income)) ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('expenses')) ?></div>
    <div class="value"><?= e(formatMoney($expenses)) ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('profit')) ?></div>
    <div class="value"><?= e(formatMoney($income - $expenses)) ?></div>
  </div>
</div>

<div class="order-card" style="margin-bottom:20px">
  <h2 style="margin-top:0"><?= e(t('add_expense')) ?></h2>
  <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
    <input type="hidden" name="action" value="add_expense">
    <div class="form-group" style="margin:0">
      <label><?= e(t('category')) ?></label>
      <input name="kategori" required placeholder="Bahan mentah / Sewa / Utiliti">
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('amount')) ?> (RM)</label>
      <input type="number" step="0.01" min="0.01" name="jumlah" required>
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('date')) ?></label>
      <input type="date" name="tanggal" value="<?= e($today) ?>" required>
    </div>
    <div class="form-group" style="margin:0">
      <label><?= e(t('notes')) ?></label>
      <input name="catatan">
    </div>
    <div style="display:flex;align-items:end">
      <button type="submit" class="btn btn-primary"><?= e(t('save')) ?></button>
    </div>
  </form>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px">
  <div>
    <h3><?= e(t('income')) ?></h3>
    <div class="table-list-wrap">
      <table class="table-list">
        <thead>
          <tr>
            <th>#</th>
            <th><?= e(t('table')) ?></th>
            <th><?= e(t('amount')) ?></th>
            <th><?= e(t('date')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$paidOrders): ?>
            <tr><td colspan="4"><?= e(t('no_data')) ?></td></tr>
          <?php endif; ?>
          <?php foreach ($paidOrders as $o): ?>
            <tr>
              <td><?= (int) $o['id'] ?></td>
              <td><?= e($o['nomor_meja']) ?></td>
              <td><?= e(formatMoney($o['total_harga'])) ?></td>
              <td><?= e($o['waktu_lunas']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div>
    <h3><?= e(t('expenses')) ?></h3>
    <div class="table-list-wrap">
      <table class="table-list">
        <thead>
          <tr>
            <th><?= e(t('category')) ?></th>
            <th><?= e(t('amount')) ?></th>
            <th><?= e(t('date')) ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$expenseRows): ?>
            <tr><td colspan="4"><?= e(t('no_data')) ?></td></tr>
          <?php endif; ?>
          <?php foreach ($expenseRows as $ex): ?>
            <tr>
              <td>
                <strong><?= e($ex['kategori']) ?></strong>
                <?php if ($ex['catatan']): ?><br><span class="order-meta"><?= e($ex['catatan']) ?></span><?php endif; ?>
              </td>
              <td><?= e(formatMoney($ex['jumlah'])) ?></td>
              <td><?= e($ex['tanggal']) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Padam?')">
                  <input type="hidden" name="action" value="delete_expense">
                  <input type="hidden" name="id" value="<?= (int) $ex['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"><?= e(t('delete')) ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
