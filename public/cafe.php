<?php
/**
 * Cafe entry — shared shop QR. Customer verifies email then gets private order link.
 * GET: shop=slug&token=shop_token
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

$lang = currentLang();
$config = getConfig();

$slug = trim((string) ($_GET['shop'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

$shop = null;
if ($slug !== '' && $token !== '') {
    $shop = findShopByAccess($slug, $token);
}

$brand = $shop ? shopBrand($shop) : ($config['app_name'] ?? 'TableTap');
$verifyMode = $shop ? shopCafeVerify($shop) : 'email';
$sendOtpUrl = baseUrl('public/api/cafe_send_otp.php');
$verifyUrl = baseUrl('public/api/cafe_verify.php');
$startUrl = baseUrl('public/api/cafe_start.php');

$i18nJs = [
    'cafe_name_required' => t('guest_name_required'),
    'cafe_email_invalid' => t('cafe_email_invalid'),
    'cafe_otp_required' => t('cafe_otp_required'),
    'cafe_sending' => t('cafe_sending'),
    'cafe_verifying' => t('cafe_verifying'),
    'cafe_starting' => t('cafe_starting'),
    'order_failed' => t('order_failed'),
];
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#e85d04">
  <title><?= e($brand) ?> — <?= e(t('cafe_entry_title')) ?></title>
  <link rel="stylesheet" href="<?= e(assetUrl('css/app.css')) ?>">
</head>
<body>
<?php if (!$shop): ?>
  <div class="error-page">
    <div class="lang-toggle" style="margin:0 auto 24px">
      <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
      <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
    </div>
    <h1><?= e($config['app_name']) ?></h1>
    <p><?= e(t('cafe_invalid_link')) ?></p>
  </div>
<?php else: ?>
  <div class="cafe-entry" id="cafe-entry"
       data-shop="<?= e($slug) ?>"
       data-token="<?= e($token) ?>"
       data-verify="<?= e($verifyMode) ?>"
       data-send-url="<?= e($sendOtpUrl) ?>"
       data-verify-url="<?= e($verifyUrl) ?>"
       data-start-url="<?= e($startUrl) ?>"
       data-i18n="<?= e(json_encode($i18nJs, JSON_UNESCAPED_UNICODE)) ?>">
    <div class="lang-toggle cafe-lang">
      <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
      <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
    </div>

    <header class="cafe-entry-head">
      <h1><?= e($brand) ?></h1>
      <p><?= e(t('cafe_entry_sub')) ?></p>
    </header>

    <div class="cafe-entry-card" id="cafe-step-form">
      <h2><?= e(t('cafe_get_link')) ?></h2>
      <p class="order-meta"><?= e($verifyMode === 'email' ? t('cafe_verify_email_hint') : t('cafe_no_verify_hint')) ?></p>

      <div class="form-group">
        <label for="cafe-name"><?= e(t('guest_name')) ?></label>
        <input id="cafe-name" type="text" maxlength="40" placeholder="<?= e(t('guest_name_ph')) ?>" autocomplete="name">
      </div>

      <?php if ($verifyMode === 'email'): ?>
      <div class="form-group">
        <label for="cafe-email"><?= e(t('cafe_email')) ?></label>
        <input id="cafe-email" type="email" maxlength="255" placeholder="<?= e(t('cafe_email_ph')) ?>" autocomplete="email">
        <p class="order-meta"><?= e(t('cafe_email_spam_hint')) ?></p>
      </div>
      <?php endif; ?>

      <button type="button" class="btn btn-primary" id="cafe-btn-start" style="width:100%">
        <?= e($verifyMode === 'email' ? t('cafe_send_code') : t('cafe_start_order')) ?>
      </button>
    </div>

    <div class="cafe-entry-card" id="cafe-step-otp" hidden>
      <h2><?= e(t('cafe_enter_code')) ?></h2>
      <p class="order-meta" id="cafe-otp-sent"></p>
      <div class="form-group">
        <label for="cafe-otp"><?= e(t('cafe_otp_label')) ?></label>
        <input id="cafe-otp" type="text" inputmode="numeric" maxlength="6" pattern="[0-9]*" autocomplete="one-time-code" placeholder="000000">
      </div>
      <button type="button" class="btn btn-primary" id="cafe-btn-verify" style="width:100%"><?= e(t('cafe_verify_btn')) ?></button>
      <button type="button" class="btn btn-ghost" id="cafe-btn-back" style="width:100%;margin-top:8px"><?= e(t('cafe_change_email')) ?></button>
    </div>

    <p class="cafe-foot order-meta"><?= e(t('cafe_privacy_note')) ?></p>
  </div>
<?php endif; ?>
<script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
<?php if ($shop): ?>
<script src="<?= e(assetUrl('js/cafe.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
