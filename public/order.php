<?php
/**
 * Customer order page
 * Table: ?meja=5&token=xxx
 * Cafe session: ?s=session_token
 * Cafe browse: ?shop=slug&token=shop_token
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

$lang = currentLang();
$config = getConfig();

$sessionToken = trim((string) ($_GET['s'] ?? ''));
$shopSlug = trim((string) ($_GET['shop'] ?? ''));
$shopToken = trim((string) ($_GET['token'] ?? ''));
$nomorMeja = trim((string) ($_GET['meja'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

$session = null;
$table = null;
$cafeMode = false;
$cafeBrowseMode = false;
$cafeVerify = 'email';

if ($sessionToken !== '') {
    $session = findSessionByToken($sessionToken);
    if ($session && ($session['status'] ?? '') === 'active') {
        $cafeMode = true;
        $table = sessionAsTableContext($session);
        $cafeVerify = shopCafeVerify($session);
    }
} elseif ($shopSlug !== '' && $shopToken !== '' && $nomorMeja === '') {
    $shopRow = findShopByAccess($shopSlug, $shopToken);
    if ($shopRow) {
        $cafeBrowseMode = true;
        $cafeMode = true;
        $table = shopAsBrowseContext($shopRow);
        $cafeVerify = shopCafeVerify($shopRow);
    }
} elseif ($nomorMeja !== '' && $token !== '') {
    $table = findTableByAccess($nomorMeja, $token);
}

$brand = $table ? shopBrand($table) : ($config['app_name'] ?? 'TableTap');
$menu = $table ? getMenuGrouped((int) $table['shop_id'], $lang) : [];
$sstEnabled = $table && (int) ($table['sst_enabled'] ?? 0) === 1;
$sstRate = $table ? (float) ($table['sst_rate'] ?? 0) : 0;
$selfPickup = $table && shopFulfillment($table) === 'self_pickup';
$canGallery = $table && shopHasFeature($table, 'menu_gallery');
$staffMode = false;
$staffBackUrl = '';
$staffFrom = '';
$submitUrl = baseUrl('public/api/submit_order.php');
$checkoutUrl = baseUrl('public/api/cafe_checkout.php');
$sendOtpUrl = baseUrl('public/api/cafe_send_otp.php');

if ($cafeMode && $session && !$cafeBrowseMode) {
    $activeOrders = fetchActiveSessionOrders($session, $lang);
    $trackOrdersUrl = cafeSessionTrackUrl(
        $sessionToken,
        $activeOrders !== [] ? (int) $activeOrders[0]['order_id'] : 0
    );
    $sessionOrderUrl = cafeSessionOrderUrl($sessionToken);
    $prefillGuestName = (string) ($session['nama_pelanggan'] ?? '');
    $shopTokenParam = '';
} elseif ($cafeBrowseMode) {
    $activeOrders = [];
    $trackOrdersUrl = '';
    $sessionOrderUrl = '';
    $prefillGuestName = '';
    $sessionToken = '';
    $shopTokenParam = $shopToken;
} else {
    $cafeMode = false;
    $sessionToken = '';
    $activeOrders = $table ? fetchActiveCustomerOrders($table, $lang) : [];
    $trackOrdersUrl = baseUrl(
        'public/confirmation.php?meja=' . urlencode($nomorMeja)
        . '&token=' . urlencode($token)
        . ($activeOrders !== [] ? '&order=' . (int) $activeOrders[0]['order_id'] : '')
    );
    $sessionOrderUrl = '';
    $prefillGuestName = '';
    $shopTokenParam = '';
}

$i18nJs = [
    'cart_empty'    => t('cart_empty'),
    'item_note_ph'  => t('item_note_ph'),
    'remove'        => t('remove'),
    'submit_order'  => t('submit_order'),
    'submitting'    => t('submitting'),
    'order_failed'  => t('order_failed'),
    'select_items'  => t('select_items'),
    'guest_name_required' => t('guest_name_required'),
    'sst_enabled'   => $sstEnabled,
    'sst_rate'      => $sstRate,
    'subtotal'      => t('subtotal'),
    'sst'           => t('sst'),
    'dine_in'       => t('dine_in'),
    'takeaway'      => t('takeaway'),
    'serving_type'  => t('serving_type'),
    'menu_search_ph' => t('menu_search_ph'),
    'menu_search_empty' => t('menu_search_empty'),
    'cafe_link_copied' => t('cafe_link_copied'),
    'cafe_checkout_title' => t('cafe_checkout_title'),
    'cafe_checkout_hint' => t('cafe_checkout_hint'),
    'cafe_email' => t('cafe_email'),
    'cafe_email_ph' => t('cafe_email_ph'),
    'cafe_send_code' => t('cafe_send_code'),
    'cafe_otp_label' => t('cafe_otp_label'),
    'cafe_verify_btn' => t('cafe_verify_btn'),
    'cafe_confirm_order' => t('cafe_confirm_order'),
    'cafe_email_invalid' => t('cafe_email_invalid'),
    'cafe_otp_required' => t('cafe_otp_required'),
    'cafe_sending' => t('cafe_sending'),
    'cafe_spam_note' => t('cafe_spam_note'),
    'guest_name' => t('guest_name'),
    'guest_name_ph' => t('guest_name_ph'),
];

if (!$table):
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#e85d04">
  <title><?= e($config['app_name']) ?></title>
  <link rel="stylesheet" href="<?= e(assetUrl('css/app.css')) ?>">
</head>
<body>
  <div class="error-page">
    <div class="lang-toggle" style="margin:0 auto 24px">
      <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
      <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
    </div>
    <h1><?= e($config['app_name']) ?></h1>
    <p><?= e($sessionToken !== '' ? t('cafe_session_expired') : t('invalid_table')) ?></p>
  </div>
  <script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
</body>
</html>
<?php
    exit;
endif;

require dirname(__DIR__) . '/includes/order_ui.php';
