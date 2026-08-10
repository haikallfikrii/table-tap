<?php
/**
 * Owner — manage tables & QR codes
 * QR image via api.qrserver.com (no Node / no Composer needed on shared hosting)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requireLogin(['owner']);

$user = currentUser();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('manage_tables');
$showSound = false;
$adminScripts = [];
$nav = 'tables';
$error = '';
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $nomor = trim((string) ($_POST['nomor_meja'] ?? ''));
            if ($nomor === '') {
                throw new RuntimeException('No. meja wajib');
            }
            $token = generateToken(24);
            $stmt = $pdo->prepare(
                'INSERT INTO tables (nomor_meja, token_akses, status) VALUES (?, ?, ?)'
            );
            $stmt->execute([$nomor, $token, 'aktif']);
            redirect(baseUrl('admin/owner/tables.php?ok=1'));
        }
        if ($action === 'regen') {
            $id = (int) ($_POST['id'] ?? 0);
            $token = generateToken(24);
            $pdo->prepare('UPDATE tables SET token_akses = ? WHERE id = ?')->execute([$token, $id]);
            redirect(baseUrl('admin/owner/tables.php?ok=1'));
        }
        if ($action === 'deactivate') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE tables SET status = 'tidak_aktif' WHERE id = ?")->execute([$id]);
            redirect(baseUrl('admin/owner/tables.php?ok=1'));
        }
        if ($action === 'activate') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE tables SET status = 'aktif' WHERE id = ?")->execute([$id]);
            redirect(baseUrl('admin/owner/tables.php?ok=1'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$tables = $pdo->query('SELECT * FROM tables ORDER BY CAST(nomor_meja AS UNSIGNED), nomor_meja')->fetchAll();
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>
<?php require __DIR__ . '/_nav.php'; ?>

<?php if (isset($_GET['ok'])): ?>
  <div class="stat-card" style="margin-bottom:16px;border-color:var(--success)"><?= e(currentLang() === 'en' ? 'Saved.' : 'Disimpan.') ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="form-error"><?= e($error) ?></div>
<?php endif; ?>

<div class="order-card" style="margin-bottom:20px">
  <h2 style="margin-top:0"><?= e(t('add_table')) ?></h2>
  <form method="post" style="display:flex;flex-wrap:wrap;gap:12px;align-items:end">
    <input type="hidden" name="action" value="add">
    <div class="form-group" style="margin:0;min-width:160px">
      <label><?= e(t('table_number')) ?></label>
      <input name="nomor_meja" required placeholder="1">
    </div>
    <button type="submit" class="btn btn-primary"><?= e(t('save')) ?></button>
  </form>
</div>

<div class="table-grid">
  <?php foreach ($tables as $t):
    $orderLink = orderUrl($t['nomor_meja'], $t['token_akses']);
    $qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($orderLink);
  ?>
    <article class="order-card">
      <div class="table-num"><?= e(t('table')) ?> <?= e($t['nomor_meja']) ?></div>
      <span class="badge <?= $t['status'] === 'aktif' ? 'badge-selesai' : 'badge-belum_bayar' ?>">
        <?= e($t['status']) ?>
      </span>
      <div style="margin:14px 0;text-align:center">
        <img src="<?= e($qrImg) ?>" alt="QR Meja <?= e($t['nomor_meja']) ?>" width="160" height="160" style="margin:0 auto;border-radius:8px">
      </div>
      <p class="order-meta" style="word-break:break-all;font-size:0.75rem"><?= e($orderLink) ?></p>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">
        <a class="btn btn-secondary btn-sm" href="<?= e($qrImg) ?>" target="_blank" rel="noopener"><?= e(t('download_qr')) ?></a>
        <form method="post">
          <input type="hidden" name="action" value="regen">
          <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
          <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('Jana semula token? QR lama akan tidak sah.')"><?= e(t('regenerate_qr')) ?></button>
        </form>
        <?php if ($t['status'] === 'aktif'): ?>
          <form method="post">
            <input type="hidden" name="action" value="deactivate">
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)"><?= e(t('delete')) ?></button>
          </form>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="action" value="activate">
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('stock_available')) ?></button>
          </form>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
