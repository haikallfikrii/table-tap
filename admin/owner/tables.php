<?php
/**
 * Owner — manage tables & QR codes
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requireLogin(['owner']);

$user = currentUser();
$shopId = requireShopId();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('manage_tables');
$showSound = false;
$adminScripts = [assetUrl('js/admin-qr.js')];
$nav = 'tables';
$error = '';
$pdo = db();
$shop = getShopOrFail($shopId);
$isCafeMode = shopIsCafeMode($shop);
$cafeEntryUrl = '';
if ($isCafeMode && orderingModeColumnExists()) {
    $cafeEntryUrl = cafeEntryUrl((string) $shop['slug'], ensureShopToken($shop));
}

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
                'INSERT INTO tables (shop_id, nomor_meja, token_akses, status) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$shopId, $nomor, $token, 'aktif']);
            redirect(baseUrl('admin/owner/tables.php?ok=1'));
        }
        if ($action === 'regen') {
            $id = (int) ($_POST['id'] ?? 0);
            $token = generateToken(24);
            $pdo->prepare('UPDATE tables SET token_akses = ? WHERE id = ? AND shop_id = ?')->execute([$token, $id, $shopId]);
            redirect(baseUrl('admin/owner/tables.php?ok=1'));
        }
        if ($action === 'regen_shop') {
            if (!$isCafeMode) {
                throw new RuntimeException('Not cafe mode');
            }
            $newToken = generateToken(24);
            $pdo->prepare('UPDATE shops SET shop_token = ? WHERE id = ?')->execute([$newToken, $shopId]);
            redirect(baseUrl('admin/owner/tables.php?ok=1'));
        }
        if ($action === 'deactivate') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE tables SET status = 'tidak_aktif' WHERE id = ? AND shop_id = ?")->execute([$id, $shopId]);
            redirect(baseUrl('admin/owner/tables.php?ok=1'));
        }
        if ($action === 'activate') {
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE tables SET status = 'aktif' WHERE id = ? AND shop_id = ?")->execute([$id, $shopId]);
            redirect(baseUrl('admin/owner/tables.php?ok=1'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$tablesStmt = $pdo->prepare(
    "SELECT * FROM tables WHERE shop_id = ? AND nomor_meja != ?
     ORDER BY CAST(nomor_meja AS UNSIGNED), nomor_meja"
);
$tablesStmt->execute([$shopId, CAFE_TABLE_NUMBER]);
$tables = $tablesStmt->fetchAll();

if ($isCafeMode && $cafeEntryUrl === '') {
    $cafeEntryUrl = cafeEntryUrl((string) $shop['slug'], ensureShopToken($shop));
}
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>
<?php require __DIR__ . '/_nav.php'; ?>

<?php if (isset($_GET['ok'])): ?>
  <div class="stat-card" style="margin-bottom:16px;border-color:var(--success)"><?= e(t('saved')) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="form-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($isCafeMode && $cafeEntryUrl !== ''): ?>
<section class="tables-section">
  <h2 class="section-title"><?= e(t('cafe_shop_qr')) ?></h2>
  <p class="section-desc"><?= e(t('cafe_shop_qr_hint')) ?></p>
  <div class="table-grid">
    <?php
      $qrTitle = t('cafe_shop_qr');
      $qrHint = '';
      $qrUrl = $cafeEntryUrl;
      $qrBadge = t('ordering_cafe');
      $qrId = 'qr-shop-entry';
      ob_start();
    ?>
    <form method="post" class="qr-inline-form">
      <input type="hidden" name="action" value="regen_shop">
      <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('<?= e(t('cafe_regen_confirm')) ?>')"><?= e(t('cafe_regen_token')) ?></button>
    </form>
    <?php
      $qrActions = ob_get_clean();
      require dirname(__DIR__, 2) . '/includes/admin_qr_card.php';
    ?>
  </div>
</section>
<?php endif; ?>

<?php if (!$isCafeMode): ?>
<section class="tables-section">
  <h2 class="section-title"><?= e(t('add_table')) ?></h2>
  <div class="order-card">
    <form method="post" class="inline-form-row">
      <input type="hidden" name="action" value="add">
      <div class="form-group" style="margin:0;flex:1;min-width:140px">
        <label><?= e(t('table_number')) ?></label>
        <input name="nomor_meja" required placeholder="1">
      </div>
      <button type="submit" class="btn btn-primary"><?= e(t('save')) ?></button>
    </form>
  </div>
</section>

<section class="tables-section">
  <h2 class="section-title"><?= e(t('manage_tables')) ?></h2>
  <div class="table-grid">
    <?php foreach ($tables as $t):
      $orderLink = orderUrl($t['nomor_meja'], $t['token_akses']);
      $qrTitle = t('table') . ' ' . $t['nomor_meja'];
      $qrHint = '';
      $qrUrl = $orderLink;
      $qrBadge = $t['status'] === 'aktif' ? t('stock_available') : $t['status'];
      $qrId = 'qr-table-' . (int) $t['id'];
      ob_start();
    ?>
      <form method="post">
        <input type="hidden" name="action" value="regen">
        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
        <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('<?= e(t('cafe_regen_confirm')) ?>')"><?= e(t('regenerate_qr')) ?></button>
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
    <?php
      $qrActions = ob_get_clean();
      require dirname(__DIR__, 2) . '/includes/admin_qr_card.php';
    endforeach; ?>
  </div>
</section>
<?php else: ?>
<section class="tables-section">
  <p class="section-desc"><?= e(t('ordering_cafe_tables_note')) ?></p>
</section>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
