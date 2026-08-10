<?php
/**
 * Owner — order history (within package retention)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requireLogin(['owner', 'kasir']);

$user = currentUser();
$shopId = requireShopId();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('order_history');
$showSound = false;
$adminScripts = [];
$nav = 'history';
$pdo = db();

$shop = findShopById($shopId);
purgeExpiredOrderHistory($shopId);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM orders WHERE shop_id = ? AND status_bayar = 'lunas'"
);
$countStmt->execute([$shopId]);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$stmt = $pdo->prepare(
    "SELECT o.id, o.waktu_order, o.waktu_lunas, o.subtotal, o.sst_jumlah, o.total_harga,
            o.status_order, t.nomor_meja
     FROM orders o
     INNER JOIN tables t ON t.id = o.table_id
     WHERE o.shop_id = ? AND o.status_bayar = 'lunas'
     ORDER BY o.waktu_lunas DESC, o.id DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$stmt->execute([$shopId]);
$rows = $stmt->fetchAll();

$retentionLabel = ($shop['retention_days'] ?? null) === null
    ? t('retention_forever')
    : ((int) $shop['retention_days'] . ' ' . t('days'));
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>
<?php if ($user['role'] === 'owner'): ?>
  <?php require __DIR__ . '/_nav.php'; ?>
<?php endif; ?>

<p class="order-meta" style="margin-top:0"><?= e(t('retention_note')) ?>: <strong><?= e($retentionLabel) ?></strong></p>

<div class="table-list-wrap">
  <table class="table-list">
    <thead>
      <tr>
        <th>#</th>
        <th><?= e(t('table')) ?></th>
        <th><?= e(t('subtotal')) ?></th>
        <th><?= e(t('sst')) ?></th>
        <th><?= e(t('total')) ?></th>
        <th><?= e(t('date')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6"><?= e(t('no_data')) ?></td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int) $r['id'] ?></td>
          <td><?= e($r['nomor_meja']) ?></td>
          <td><?= e(formatMoney($r['subtotal'])) ?></td>
          <td><?= e(formatMoney($r['sst_jumlah'])) ?></td>
          <td><strong><?= e(formatMoney($r['total_harga'])) ?></strong></td>
          <td><?= e($r['waktu_lunas'] ?: $r['waktu_order']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
  <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm" href="?page=<?= $i ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
