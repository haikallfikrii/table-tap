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
        if (!in_array($cafeVerify, ['email', 'phone', 'email_phone', 'none'], true)) {
            $cafeVerify = 'email';
        }
        $regenShopToken = isset($_POST['regen_shop_token']);
        $deliveryEnabled = isset($_POST['delivery_enabled']) ? 1 : 0;
        $deliveryRequirePhone = isset($_POST['delivery_require_phone']) ? 1 : 0;
        $payCounter = isset($_POST['pay_counter']) ? 1 : 0;
        $payCod = isset($_POST['pay_cod']) ? 1 : 0;
        $payDuitnow = isset($_POST['pay_duitnow']) ? 1 : 0;
        $holdKitchen = isset($_POST['hold_kitchen_until_paid']) ? 1 : 0;
        $regenDeliveryToken = isset($_POST['regen_delivery_token']);
        if ($namaKedai === '') {
            throw new RuntimeException(t('shop_name') . ' required');
        }
        if ($sstRate < 0 || $sstRate > 100) {
            throw new RuntimeException('SST rate invalid');
        }
        if ($deliveryEnabled && !shopHasFeature($shop, 'delivery')) {
            $deliveryEnabled = 0;
        }
        if ($deliveryEnabled && !$payCounter && !$payCod && !$payDuitnow) {
            $payCod = 1;
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

        if (deliveryColumnsExist()) {
            if ($deliveryEnabled) {
                enableDeliveryForShop($shopId);
            } else {
                disableDeliveryForShop($shopId);
            }
            $pdo->prepare(
                'UPDATE shops SET pay_counter = ?, pay_cod = ?, pay_duitnow = ?, hold_kitchen_until_paid = ?
                 WHERE id = ?'
            )->execute([$payCounter, $payCod, $payDuitnow, $holdKitchen, $shopId]);

            $phoneCol = $pdo->query("SHOW COLUMNS FROM shops LIKE 'delivery_require_phone'")->fetch();
            if ($phoneCol) {
                $pdo->prepare('UPDATE shops SET delivery_require_phone = ? WHERE id = ?')
                    ->execute([$deliveryRequirePhone, $shopId]);
            }

            if ($regenDeliveryToken && $deliveryEnabled) {
                $pdo->prepare('UPDATE shops SET delivery_token = ? WHERE id = ?')
                    ->execute([generateToken(24), $shopId]);
            }

            if (!empty($_FILES['duitnow_qr']['tmp_name'])) {
                $file = $_FILES['duitnow_qr'];
                if (($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                    if (($file['size'] ?? 0) > ($config['upload_max_bytes'] ?? 2097152)) {
                        throw new RuntimeException('QR file too large');
                    }
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);
                    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!in_array($mime, $allowed, true)) {
                        throw new RuntimeException('Invalid QR image');
                    }
                    $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
                    $dir = dirname(__DIR__, 2) . '/assets/uploads/duitnow';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $name = 'dn_' . $shopId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
                        throw new RuntimeException('Could not save QR');
                    }
                    $pdo->prepare('UPDATE shops SET duitnow_qr_url = ? WHERE id = ?')
                        ->execute(['assets/uploads/duitnow/' . $name, $shopId]);
                }
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
$canDelivery = shopHasFeature($shop, 'delivery');
$isCafeMode = shopIsCafeMode($shop);
$isDelivery = shopDeliveryEnabled($shop);
$cafeEntryUrl = '';
$deliveryEntryUrl = '';
if ($isCafeMode && orderingModeColumnExists()) {
    $shopToken = ensureShopToken($shop);
    $cafeEntryUrl = cafeEntryUrl((string) $shop['slug'], $shopToken);
}
if ($isDelivery && deliveryColumnsExist()) {
    $dToken = ensureDeliveryToken($shop);
    $deliveryEntryUrl = deliveryEntryUrl((string) $shop['slug'], $dToken);
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
  <form method="post" class="settings-form" enctype="multipart/form-data">
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
          <input type="radio" name="cafe_verify" value="phone" <?= shopCafeVerify($shop) === 'phone' ? 'checked' : '' ?>>
          <span class="option-card-body">
            <strong><?= e(t('cafe_verify_phone')) ?></strong>
            <span><?= e(t('cafe_verify_phone_short')) ?></span>
          </span>
        </label>
        <label class="option-card">
          <input type="radio" name="cafe_verify" value="email_phone" <?= shopCafeVerify($shop) === 'email_phone' ? 'checked' : '' ?>>
          <span class="option-card-body">
            <strong><?= e(t('cafe_verify_email_phone')) ?></strong>
            <span><?= e(t('cafe_verify_email_phone_short')) ?></span>
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

    <?php if (deliveryColumnsExist()): ?>
    <fieldset class="settings-fieldset">
      <legend><?= e(t('delivery_settings')) ?></legend>
      <p class="settings-fieldset-desc"><?= e(t('delivery_settings_hint')) ?></p>
      <?php if (!$canDelivery): ?>
        <p class="settings-upgrade-note"><?= e(t('delivery_upgrade')) ?></p>
      <?php endif; ?>
      <label class="settings-check<?= $canDelivery ? '' : ' is-disabled' ?>">
        <input type="checkbox" name="delivery_enabled" value="1" <?= $isDelivery ? 'checked' : '' ?> <?= $canDelivery ? '' : 'disabled' ?>>
        <span><?= e(t('delivery_enable')) ?></span>
      </label>
      <?php if ($isDelivery && $deliveryEntryUrl !== ''): ?>
        <div class="table-grid" style="margin-top:14px">
          <?php
            $qrTitle = t('delivery_qr_card_title');
            $qrHint = t('delivery_qr_card_hint');
            $qrUrl = $deliveryEntryUrl;
            $qrBadge = t('delivery_order_title');
            $qrId = 'qr-delivery-entry';
            $qrActions = '<a class="btn btn-ghost btn-sm" href="' . e($deliveryEntryUrl) . '" target="_blank" rel="noopener">' . e(t('delivery_open_landing')) . '</a>';
            require dirname(__DIR__, 2) . '/includes/admin_qr_card.php';
          ?>
        </div>
        <label class="settings-check" style="margin-top:8px">
          <input type="checkbox" name="regen_delivery_token" value="1">
          <span><?= e(t('regen_delivery_token')) ?></span>
        </label>
      <?php endif; ?>
      <label class="settings-check" style="margin-top:12px">
        <input type="checkbox" name="delivery_require_phone" value="1" <?= (int) ($shop['delivery_require_phone'] ?? 0) ? 'checked' : '' ?>>
        <span><?= e(t('delivery_require_phone')) ?></span>
      </label>
      <p class="order-meta"><?= e(t('delivery_require_phone_hint')) ?></p>
      <div class="settings-row" style="margin-top:14px;flex-wrap:wrap;gap:12px">
        <label class="settings-check"><input type="checkbox" name="pay_cod" value="1" <?= (int) ($shop['pay_cod'] ?? 1) ? 'checked' : '' ?>> <span><?= e(t('pay_cod')) ?></span></label>
        <label class="settings-check"><input type="checkbox" name="pay_duitnow" value="1" <?= (int) ($shop['pay_duitnow'] ?? 1) ? 'checked' : '' ?>> <span><?= e(t('pay_duitnow')) ?></span></label>
        <label class="settings-check"><input type="checkbox" name="pay_counter" value="1" <?= (int) ($shop['pay_counter'] ?? 1) ? 'checked' : '' ?>> <span><?= e(t('pay_counter')) ?></span></label>
      </div>
      <label class="settings-check" style="margin-top:10px">
        <input type="checkbox" name="hold_kitchen_until_paid" value="1" <?= (int) ($shop['hold_kitchen_until_paid'] ?? 1) ? 'checked' : '' ?>>
        <span><?= e(t('hold_kitchen_until_paid')) ?></span>
      </label>
      <div class="settings-block" style="margin-top:14px">
        <label class="settings-block-label"><?= e(t('duitnow_qr_upload')) ?></label>
        <?php if (!empty($shop['duitnow_qr_url'])): ?>
          <img src="<?= e(baseUrl((string) $shop['duitnow_qr_url'])) ?>" alt="DuitNow" style="width:120px;height:120px;object-fit:contain;border:1px solid var(--border);border-radius:8px;display:block;margin-bottom:8px">
        <?php endif; ?>
        <input type="file" name="duitnow_qr" accept="image/jpeg,image/png,image/webp">
        <p class="order-meta"><?= e(t('duitnow_qr_hint')) ?></p>
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
