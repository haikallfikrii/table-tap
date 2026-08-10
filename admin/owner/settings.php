<?php
/**
 * Owner — shop settings (SST, nama kedai display info)
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';

requireLogin(['owner']);

$user = currentUser();
$shopId = requireShopId();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('shop_settings');
$showSound = false;
$adminScripts = [];
$nav = 'settings';
$error = '';
$pdo = db();

$shop = getShopOrFail($shopId);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        $namaKedai = trim((string) ($_POST['nama_kedai'] ?? ''));
        $sstEnabled = isset($_POST['sst_enabled']) ? 1 : 0;
        $sstRate = (float) ($_POST['sst_rate'] ?? 6);
        if ($namaKedai === '') {
            throw new RuntimeException(t('shop_name') . ' required');
        }
        if ($sstRate < 0 || $sstRate > 100) {
            throw new RuntimeException('SST rate invalid');
        }
        $pdo->prepare(
            'UPDATE shops SET nama_kedai = ?, sst_enabled = ?, sst_rate = ? WHERE id = ?'
        )->execute([$namaKedai, $sstEnabled, $sstRate, $shopId]);

        // refresh session brand
        startAppSession();
        $_SESSION['shop_name'] = $namaKedai;

        redirect(baseUrl('admin/owner/settings.php?ok=1'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$shop = getShopOrFail($shopId);
$retentionLabel = $shop['retention_days'] === null
    ? t('retention_forever')
    : ((int) $shop['retention_days'] . ' ' . t('days'));
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>
<?php require __DIR__ . '/_nav.php'; ?>

<?php if (isset($_GET['ok'])): ?>
  <div class="stat-card" style="margin-bottom:16px;border-color:var(--success)"><?= e(t('saved')) ?></div>
<?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<div class="stat-row">
  <div class="stat-card">
    <div class="label"><?= e(t('package')) ?></div>
    <div class="value" style="font-size:1.1rem">
      <?= e($lang === 'en' ? $shop['package_nama_en'] : $shop['package_nama_my']) ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('order_history')) ?></div>
    <div class="value" style="font-size:1.1rem"><?= e($retentionLabel) ?></div>
  </div>
</div>

<div class="order-card">
  <h2 style="margin-top:0"><?= e(t('shop_settings')) ?></h2>
  <form method="post" style="max-width:480px">
    <div class="form-group">
      <label><?= e(t('shop_name')) ?></label>
      <input name="nama_kedai" required value="<?= e($shop['nama_kedai']) ?>">
      <p class="order-meta" style="margin:6px 0 0"><?= e(t('shop_name_hint')) ?></p>
    </div>
    <div class="form-group">
      <label style="display:flex;gap:8px;align-items:center">
        <input type="checkbox" name="sst_enabled" value="1" <?= (int) $shop['sst_enabled'] ? 'checked' : '' ?>>
        <?= e(t('sst_enable')) ?>
      </label>
    </div>
    <div class="form-group">
      <label><?= e(t('sst_rate')) ?> (%)</label>
      <input type="number" step="0.01" min="0" max="100" name="sst_rate" value="<?= e(number_format((float) $shop['sst_rate'], 2, '.', '')) ?>">
    </div>
    <button type="submit" class="btn btn-primary"><?= e(t('save')) ?></button>
  </form>
</div>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
