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
    "SELECT COUNT(*) FROM orders WHERE shop_id = ? AND status_bayar = 'lunas' AND status_order != 'dibatalkan'"
);
$countStmt->execute([$shopId]);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$emailCol = orderCustomerEmailColumnExists() ? ', o.customer_email, o.nama_pelanggan' : ', o.nama_pelanggan';
$stmt = $pdo->prepare(
    "SELECT o.id, o.waktu_order, o.waktu_lunas, o.subtotal, o.sst_jumlah, o.total_harga,
            o.status_order, o.jenis_hidang, t.nomor_meja{$emailCol}
     FROM orders o
     INNER JOIN tables t ON t.id = o.table_id
     WHERE o.shop_id = ? AND o.status_bayar = 'lunas' AND o.status_order != 'dibatalkan'
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
        <th><?= e(t('serving_type')) ?></th>
        <th><?= e(t('subtotal')) ?></th>
        <th><?= e(t('sst')) ?></th>
        <th><?= e(t('total')) ?></th>
        <th><?= e(t('date')) ?></th>
        <th><?= e(t('guest_name')) ?></th>
        <th><?= e(t('customer_email')) ?></th>
        <th><?= e(t('actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="10"><?= e(t('no_data')) ?></td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int) $r['id'] ?></td>
          <td><?= e($r['nomor_meja']) ?></td>
          <td><?= e(($r['jenis_hidang'] ?? '') === 'takeaway' ? t('takeaway') : t('dine_in')) ?></td>
          <td><?= e(formatMoney($r['subtotal'])) ?></td>
          <td><?= e(formatMoney($r['sst_jumlah'])) ?></td>
          <td><strong><?= e(formatMoney($r['total_harga'])) ?></strong></td>
          <td><?= e($r['waktu_lunas'] ?: $r['waktu_order']) ?></td>
          <td><?= e((string) ($r['nama_pelanggan'] ?? '')) ?></td>
          <td><?php if (!empty($r['customer_email'])): ?><a href="mailto:<?= e($r['customer_email']) ?>"><?= e($r['customer_email']) ?></a><?php else: ?>—<?php endif; ?></td>
          <td>
            <a class="btn btn-secondary btn-sm" href="<?= e(baseUrl('admin/receipt.php?order=' . (int) $r['id'] . '&print=1')) ?>" target="_blank" rel="noopener"><?= e(t('print_receipt')) ?></a>
            <button type="button" class="btn btn-ghost btn-sm btn-void-order" style="color:var(--danger)" data-order="<?= (int) $r['id'] ?>"><?= e(t('cancel_order')) ?></button>
          </td>
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

<script>
(function () {
  const url = <?= json_encode(baseUrl('admin/api/cancel_order.php')) ?>;
  const confirmMsg = <?= json_encode(t('cancel_order_confirm')) ?>;
  document.querySelectorAll('.btn-void-order').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const orderId = Number(btn.getAttribute('data-order'));
      if (!orderId || !confirm(confirmMsg)) return;
      btn.disabled = true;
      try {
        const res = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ order_id: orderId }),
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Failed');
        btn.closest('tr')?.remove();
      } catch (err) {
        alert(err.message || 'Error');
        btn.disabled = false;
      }
    });
  });
})();
</script>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
