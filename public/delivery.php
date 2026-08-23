<?php
/**
 * Delivery landing — shareable page with OG preview (WA/social) + QR + shop name.
 * GET: shop=slug&token=delivery_token
 * Optional: ?go=1 redirects straight to menu.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

$slug = trim((string) ($_GET['shop'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$lang = currentLang();
$config = getConfig();
$shop = ($slug !== '' && $token !== '') ? findShopByDeliveryAccess($slug, $token) : null;

if ($shop && isset($_GET['go'])) {
    redirect(deliveryBrowseOrderUrl($slug, $token));
}

if (!$shop) {
    ?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($config['app_name']) ?></title>
  <link rel="stylesheet" href="<?= e(assetUrl('css/app.css')) ?>">
</head>
<body>
  <div class="error-page">
    <h1><?= e($config['app_name']) ?></h1>
    <p><?= e(t('delivery_invalid_link')) ?></p>
  </div>
</body>
</html>
    <?php
    exit;
}

$brand = shopBrand($shop);
$selfUrl = deliveryEntryUrl($slug, $token);
$menuUrl = deliveryBrowseOrderUrl($slug, $token);
$qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&margin=12&data=' . rawurlencode($selfUrl);
$ogDesc = t('delivery_share_desc', $brand);
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#e85d04">
  <title><?= e($brand) ?> — <?= e(t('delivery_order_title')) ?></title>
  <meta name="description" content="<?= e($ogDesc) ?>">
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= e($brand . ' · ' . t('delivery_order_title')) ?>">
  <meta property="og:description" content="<?= e($ogDesc) ?>">
  <meta property="og:url" content="<?= e($selfUrl) ?>">
  <meta property="og:image" content="<?= e($qrImg) ?>">
  <meta property="og:image:width" content="600">
  <meta property="og:image:height" content="600">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($brand . ' · ' . t('delivery_order_title')) ?>">
  <meta name="twitter:description" content="<?= e($ogDesc) ?>">
  <meta name="twitter:image" content="<?= e($qrImg) ?>">
  <link rel="stylesheet" href="<?= e(assetUrl('css/app.css')) ?>">
</head>
<body class="delivery-landing">
  <div class="delivery-landing-card">
    <div class="lang-toggle" style="align-self:flex-end;margin-bottom:8px">
      <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
      <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
    </div>
    <p class="delivery-landing-eyebrow"><?= e(t('delivery_order_title')) ?></p>
    <h1 class="delivery-landing-brand"><?= e($brand) ?></h1>
    <p class="delivery-landing-sub"><?= e(t('delivery_landing_hint')) ?></p>
    <div class="delivery-landing-qr">
      <img src="<?= e($qrImg) ?>" alt="QR <?= e($brand) ?>" width="240" height="240">
    </div>
    <a class="btn btn-primary" style="width:100%" href="<?= e($menuUrl) ?>"><?= e(t('delivery_start_order')) ?></a>
  </div>
  <script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
</body>
</html>
