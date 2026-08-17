<?php
/**
 * Customer order page — table QR: ?meja=5&token=xxx | cafe session: ?s=session_token
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

$lang = currentLang();
$config = getConfig();

$sessionToken = trim((string) ($_GET['s'] ?? ''));
$nomorMeja = trim((string) ($_GET['meja'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

$session = null;
$table = null;
$cafeMode = false;

if ($sessionToken !== '') {
    $session = findSessionByToken($sessionToken);
    if ($session && ($session['status'] ?? '') === 'active') {
        $cafeMode = true;
        $table = sessionAsTableContext($session);
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

if ($cafeMode && $session) {
    $activeOrders = fetchActiveSessionOrders($session, $lang);
    $trackOrdersUrl = cafeSessionTrackUrl(
        $sessionToken,
        $activeOrders !== [] ? (int) $activeOrders[0]['order_id'] : 0
    );
    $sessionOrderUrl = cafeSessionOrderUrl($sessionToken);
    $prefillGuestName = (string) ($session['nama_pelanggan'] ?? '');
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
    <p><?= e($cafeMode || $sessionToken !== '' ? t('cafe_session_expired') : t('invalid_table')) ?></p>
  </div>
  <script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
</body>
</html>
<?php
    exit;
endif;

require dirname(__DIR__) . '/includes/order_ui.php';
