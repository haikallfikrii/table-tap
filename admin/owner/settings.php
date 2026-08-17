<?php
/**
 * Owner — shop settings (SST, nama kedai display info)
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
        $soundMode = (string) ($_POST['sound_mode'] ?? 'until_cleared');
        if (!in_array($soundMode, ['until_cleared', 'count', 'duration'], true)) {
            $soundMode = 'until_cleared';
        }
        $soundCount = max(1, min(50, (int) ($_POST['sound_repeat_count'] ?? 8)));
        $soundDuration = max(3, min(300, (int) ($_POST['sound_duration_sec'] ?? 45)));
        $soundInterval = max(400, min(5000, (int) ($_POST['sound_interval_ms'] ?? 900)));
        $soundVolume = max(20, min(100, (int) ($_POST['sound_volume'] ?? 100)));
        $fulfillment = (string) ($_POST['fulfillment_mode'] ?? 'waiter');
        if (!in_array($fulfillment, ['waiter', 'self_pickup'], true)) {
            $fulfillment = 'waiter';
        }
        if ($fulfillment === 'self_pickup' && !shopHasFeature($shop, 'self_pickup')) {
            $fulfillment = 'waiter';
        }
        $orderingMode = (string) ($_POST['ordering_mode'] ?? 'table');
        if (!in_array($orderingMode, ['table', 'cafe'], true)) {
            $orderingMode = 'table';
        }
        $cafeVerify = (string) ($_POST['cafe_verify'] ?? 'email');
        if (!in_array($cafeVerify, ['email', 'none'], true)) {
            $cafeVerify = 'email';
        }
        $regenShopToken = isset($_POST['regen_shop_token']);
        if ($namaKedai === '') {
            throw new RuntimeException(t('shop_name') . ' required');
        }
        if ($sstRate < 0 || $sstRate > 100) {
            throw new RuntimeException('SST rate invalid');
        }
        $pdo->prepare(
            'UPDATE shops SET nama_kedai = ?, sst_enabled = ?, sst_rate = ?,
                    fulfillment_mode = ?,
                    sound_mode = ?, sound_repeat_count = ?, sound_duration_sec = ?,
                    sound_interval_ms = ?, sound_volume = ?
             WHERE id = ?'
        )->execute([
            $namaKedai, $sstEnabled, $sstRate,
            $fulfillment,
            $soundMode, $soundCount, $soundDuration, $soundInterval, $soundVolume,
            $shopId,
        ]);

        if (orderingModeColumnExists()) {
            if ($orderingMode === 'cafe') {
                enableCafeModeForShop($shopId, $cafeVerify);
            } else {
                disableCafeModeForShop($shopId);
                if (orderingModeColumnExists()) {
                    $pdo->prepare('UPDATE shops SET cafe_verify = ? WHERE id = ?')
                        ->execute([$cafeVerify, $shopId]);
                }
            }
            if ($regenShopToken && $orderingMode === 'cafe') {
                $newToken = generateToken(24);
                $pdo->prepare('UPDATE shops SET shop_token = ? WHERE id = ?')
                    ->execute([$newToken, $shopId]);
            }
        }
        startAppSession();
        $_SESSION['shop_name'] = $namaKedai;

        redirect(baseUrl('admin/owner/settings.php?ok=1'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$shop = getShopOrFail($shopId);
$canSelfPickup = shopHasFeature($shop, 'self_pickup');
$isCafeMode = shopIsCafeMode($shop);
$cafeEntryUrl = '';
if ($isCafeMode && orderingModeColumnExists()) {
    $shopToken = ensureShopToken($shop);
    $cafeEntryUrl = cafeEntryUrl((string) $shop['slug'], $shopToken);
}
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

<div class="order-card settings-panel">
  <h2 style="margin-top:0"><?= e(t('shop_settings')) ?></h2>
  <form method="post" class="settings-form">
    <div class="settings-block">
      <label class="settings-block-label"><?= e(t('shop_name')) ?></label>
      <input name="nama_kedai" required value="<?= e($shop['nama_kedai']) ?>">
      <p class="order-meta"><?= e(t('shop_name_hint')) ?></p>
    </div>

    <div class="settings-row">
      <div class="settings-block">
        <label class="settings-check">
          <input type="checkbox" name="sst_enabled" value="1" <?= (int) $shop['sst_enabled'] ? 'checked' : '' ?>>
          <span><?= e(t('sst_enable')) ?></span>
        </label>
      </div>
      <div class="settings-block" style="max-width:140px">
        <label><?= e(t('sst_rate')) ?> (%)</label>
        <input type="number" step="0.01" min="0" max="100" name="sst_rate" value="<?= e(number_format((float) $shop['sst_rate'], 2, '.', '')) ?>">
      </div>
    </div>

    <fieldset class="settings-fieldset">
      <legend><?= e(t('fulfillment')) ?></legend>
      <p class="settings-fieldset-desc"><?= e(t('fulfillment_hint_short')) ?></p>
      <?php if (!$canSelfPickup): ?>
        <p class="settings-upgrade-note"><?= e(t('fulfillment_upgrade')) ?></p>
      <?php endif; ?>
      <div class="option-cards">
        <label class="option-card">
          <input type="radio" name="fulfillment_mode" value="waiter" <?= shopFulfillment($shop) === 'waiter' ? 'checked' : '' ?>>
          <span class="option-card-body">
            <strong><?= e(t('fulfillment_waiter')) ?></strong>
            <span><?= e(t('fulfillment_waiter_short')) ?></span>
          </span>
        </label>
        <label class="option-card<?= $canSelfPickup ? '' : ' is-disabled' ?>">
          <input type="radio" name="fulfillment_mode" value="self_pickup" <?= shopFulfillment($shop) === 'self_pickup' ? 'checked' : '' ?> <?= $canSelfPickup ? '' : 'disabled' ?>>
          <span class="option-card-body">
            <strong><?= e(t('fulfillment_self')) ?></strong>
            <span><?= e(t('fulfillment_self_short')) ?></span>
          </span>
        </label>
      </div>
    </fieldset>

    <?php if (orderingModeColumnExists()): ?>
    <fieldset class="settings-fieldset">
      <legend><?= e(t('ordering_mode')) ?></legend>
      <p class="settings-fieldset-desc"><?= e(t('ordering_mode_hint_short')) ?></p>
      <div class="option-cards">
        <label class="option-card">
          <input type="radio" name="ordering_mode" value="table" <?= !$isCafeMode ? 'checked' : '' ?>>
          <span class="option-card-body">
            <strong><?= e(t('ordering_table')) ?></strong>
            <span><?= e(t('ordering_table_short')) ?></span>
          </span>
        </label>
        <label class="option-card">
          <input type="radio" name="ordering_mode" value="cafe" <?= $isCafeMode ? 'checked' : '' ?>>
          <span class="option-card-body">
            <strong><?= e(t('ordering_cafe')) ?></strong>
            <span><?= e(t('ordering_cafe_short')) ?></span>
          </span>
        </label>
      </div>
      <?php if ($isCafeMode): ?>
        <p class="settings-link-note"><a href="<?= e(baseUrl('admin/owner/tables.php')) ?>"><?= e(t('cafe_qr_manage_link')) ?></a></p>
      <?php endif; ?>
    </fieldset>

    <fieldset class="settings-fieldset" id="cafe-verify-fieldset">
      <legend><?= e(t('cafe_verify')) ?></legend>
      <p class="settings-fieldset-desc"><?= e(t('cafe_verify_hint_short')) ?></p>
      <div class="option-cards option-cards-compact">
        <label class="option-card">
          <input type="radio" name="cafe_verify" value="email" <?= shopCafeVerify($shop) === 'email' ? 'checked' : '' ?>>
          <span class="option-card-body">
            <strong><?= e(t('cafe_verify_email')) ?></strong>
            <span><?= e(t('cafe_verify_email_short')) ?></span>
          </span>
        </label>
        <label class="option-card">
          <input type="radio" name="cafe_verify" value="none" <?= shopCafeVerify($shop) === 'none' ? 'checked' : '' ?>>
          <span class="option-card-body">
            <strong><?= e(t('cafe_verify_none')) ?></strong>
            <span><?= e(t('cafe_verify_none_short')) ?></span>
          </span>
        </label>
      </div>
    </fieldset>
    <?php endif; ?>

    <fieldset class="settings-fieldset">
      <legend><?= e(t('sound_settings')) ?></legend>
      <p class="settings-fieldset-desc"><?= e(t('sound_settings_hint')) ?></p>
    <div class="form-group">
      <label><?= e(t('sound_mode')) ?></label>
      <select name="sound_mode">
        <option value="until_cleared" <?= ($shop['sound_mode'] ?? '') === 'until_cleared' ? 'selected' : '' ?>><?= e(t('sound_mode_until')) ?></option>
        <option value="count" <?= ($shop['sound_mode'] ?? '') === 'count' ? 'selected' : '' ?>><?= e(t('sound_mode_count')) ?></option>
        <option value="duration" <?= ($shop['sound_mode'] ?? '') === 'duration' ? 'selected' : '' ?>><?= e(t('sound_mode_duration')) ?></option>
      </select>
    </div>
    <div class="form-group">
      <label><?= e(t('sound_repeat_count')) ?></label>
      <input type="number" min="1" max="50" name="sound_repeat_count" value="<?= (int) ($shop['sound_repeat_count'] ?? 8) ?>">
    </div>
    <div class="form-group">
      <label><?= e(t('sound_duration_sec')) ?></label>
      <input type="number" min="3" max="300" name="sound_duration_sec" value="<?= (int) ($shop['sound_duration_sec'] ?? 45) ?>">
    </div>
    <div class="form-group">
      <label><?= e(t('sound_interval_ms')) ?></label>
      <input type="number" min="400" max="5000" step="100" name="sound_interval_ms" value="<?= (int) ($shop['sound_interval_ms'] ?? 900) ?>">
    </div>
    <div class="form-group">
      <label><?= e(t('sound_volume')) ?> (%)</label>
      <input type="number" min="20" max="100" name="sound_volume" value="<?= (int) ($shop['sound_volume'] ?? 100) ?>">
    </div>
    </fieldset>

    <button type="submit" class="btn btn-primary settings-save"><?= e(t('save')) ?></button>
  </form>
</div>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
