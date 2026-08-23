<?php
/**
 * Order confirmation — tracks all active orders for the table.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

$lang = currentLang();
$config = getConfig();

$orderId = (int) ($_GET['order'] ?? 0);
$sessionToken = trim((string) ($_GET['s'] ?? ''));
$nomorMeja = trim((string) ($_GET['meja'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$guestTokenParam = trim((string) ($_GET['gt'] ?? ''));

$session = null;
$table = null;
$allOrders = [];
$focusOrder = null;
$brand = $config['app_name'] ?? 'TableTap';
$selfPickup = false;
$cafeMode = false;
$deliveryMode = (($_GET['channel'] ?? '') === 'delivery');

if ($sessionToken !== '') {
    $session = findSessionByToken($sessionToken);
    if ($session && ($session['status'] ?? '') === 'active') {
        $cafeMode = true;
        $table = sessionAsTableContext($session);
        $brand = shopBrand($table);
        $selfPickup = !$deliveryMode && shopFulfillment($table) === 'self_pickup';
        $allOrders = fetchActiveSessionOrders($session, $lang);
        if ($orderId <= 0 && $allOrders !== []) {
            $orderId = (int) $allOrders[0]['order_id'];
        }
        foreach ($allOrders as $o) {
            if ((int) ($o['order_id'] ?? 0) === $orderId) {
                $focusOrder = $o;
                break;
            }
        }
        if ($focusOrder === null && $allOrders !== []) {
            $focusOrder = $allOrders[0];
            $orderId = (int) $focusOrder['order_id'];
        }
        if (!$deliveryMode && (($focusOrder['jenis_hidang'] ?? '') === 'delivery' || ($table['nomor_meja'] ?? '') === DELIVERY_TABLE_NUMBER)) {
            $deliveryMode = true;
            $selfPickup = false;
        }
    }
} elseif ($nomorMeja !== '' && $token !== '') {
    $table = findTableByAccess($nomorMeja, $token);
    if ($table) {
        $brand = shopBrand($table);
        if (!$deliveryMode && (($table['nomor_meja'] ?? '') === DELIVERY_TABLE_NUMBER)) {
            $deliveryMode = true;
        }
        $selfPickup = !$deliveryMode && shopFulfillment($table) === 'self_pickup';
        $scopeEmail = '';
        if ($deliveryMode || (($table['nomor_meja'] ?? '') === DELIVERY_TABLE_NUMBER)) {
            $deliveryMode = true;
            $selfPickup = false;
            if ($orderId > 0) {
                $scopeEmail = findOrderCustomerEmail($orderId, (int) $table['shop_id']);
            }
            // Prefer guest token from URL; also group same customer's multi-orders via email
            $allOrders = fetchActiveCustomerOrders($table, $lang, $guestTokenParam !== '' ? $guestTokenParam : null, $scopeEmail !== '' ? $scopeEmail : null);
            // Fallback: single order by gt if email lookup empty but gt present
            if ($allOrders === [] && $guestTokenParam !== '') {
                $allOrders = fetchActiveCustomerOrders($table, $lang, $guestTokenParam, null);
            }
        } else {
            $allOrders = fetchActiveCustomerOrders($table, $lang);
        }
        if ($orderId <= 0 && $allOrders !== []) {
            $orderId = (int) $allOrders[0]['order_id'];
        }
        foreach ($allOrders as $o) {
            if ((int) ($o['order_id'] ?? 0) === $orderId) {
                $focusOrder = $o;
                break;
            }
        }
        if ($focusOrder === null && $allOrders !== []) {
            $focusOrder = $allOrders[0];
            $orderId = (int) $focusOrder['order_id'];
        }
        if (!$deliveryMode && ($focusOrder['jenis_hidang'] ?? '') === 'delivery') {
            $deliveryMode = true;
            $selfPickup = false;
        }
    }
}

/** @param array<string, mixed> $it */
function confirmItemLabel(array $it, bool $selfPickup): string
{
    $st = (string) ($it['status_item'] ?? '');
    if ($st === 'sedang_dimasak') {
        return t('status_item_sedang');
    }
    if ($st === 'siap') {
        return $selfPickup ? t('status_item_siap') : t('status_item_ready_short');
    }
    if ($st === 'diambil') {
        return t('status_item_diambil');
    }
    if ($st === 'dihantar') {
        return $selfPickup ? t('status_item_dihantar') : t('status_item_served');
    }
    return t('status_item_menunggu');
}

/** @param array<string, mixed> $it */
function confirmItemClass(array $it): string
{
    $st = (string) ($it['status_item'] ?? '');
    if ($st === 'sedang_dimasak') {
        return 'cooking';
    }
    if ($st === 'siap') {
        return 'ready';
    }
    if ($st === 'diambil') {
        return 'delivering';
    }
    if ($st === 'dihantar') {
        return 'done';
    }
    return 'queue';
}

$trackI18n = [
    'status_queue'    => t('status_item_menunggu'),
    'status_cooking'  => t('status_item_sedang'),
    'status_ready'    => $selfPickup ? t('status_item_siap') : t('status_item_ready_short'),
    'status_deliver'  => $deliveryMode ? t('status_item_delivery_out') : t('status_item_diambil'),
    'status_done'     => $selfPickup ? t('status_item_dihantar') : ($deliveryMode ? t('status_item_delivered') : t('status_item_served')),
    'order_queue'     => t('order_queue'),
    'order_cooking'   => t('order_cooking'),
    'order_ready'     => $selfPickup ? t('order_ready') : ($deliveryMode ? t('order_ready_delivery') : t('order_ready_waiter')),
    'order_delivering'=> $deliveryMode ? t('order_delivering_courier') : t('order_delivering'),
    'order_collected' => $selfPickup ? t('order_collected') : ($deliveryMode ? t('order_delivered_home') : t('order_arrived')),
    'title_queue'     => t('track_title_queue'),
    'title_cooking'   => t('track_title_cooking'),
    'title_ready'     => $selfPickup ? t('track_title_ready') : ($deliveryMode ? t('track_title_ready_delivery') : t('track_title_ready_waiter')),
    'title_delivering'=> $deliveryMode ? t('track_title_delivering_courier') : t('track_title_delivering'),
    'title_done'      => $selfPickup ? t('track_title_done') : ($deliveryMode ? t('track_title_delivered') : t('track_title_served')),
    'sound_popup_ok'  => t('sound_popup_ok'),
    'i_collected'     => t('i_collected'),
    'your_orders'     => t('your_orders'),
    'order_latest'    => t('order_latest'),
    'order_earlier'   => t('order_earlier'),
    'dine_in'         => t('dine_in'),
    'takeaway'        => t('takeaway'),
    'delivery'        => t('delivery_order_title'),
    'proof_upload_hint' => t('proof_upload_hint'),
    'proof_choose_file' => t('proof_choose_file'),
    'proof_no_file' => t('proof_no_file'),
    'proof_upload_btn' => t('proof_upload_btn'),
    'proof_upload_required' => t('proof_upload_required'),
    'proof_upload_failed' => t('proof_upload_failed'),
    'proof_waiting_kasir' => t('proof_waiting_kasir'),
    'duitnow_scan_hint' => t('duitnow_scan_hint'),
    'download_qr'     => t('download_qr'),
];

$stage = $focusOrder ? (string) ($focusOrder['stage'] ?? 'queue') : 'queue';
$titles = [
    'queue' => t('track_title_queue'),
    'cooking' => t('track_title_cooking'),
    'ready' => $selfPickup ? t('track_title_ready') : ($deliveryMode ? t('track_title_ready_delivery') : t('track_title_ready_waiter')),
    'delivering' => $deliveryMode ? t('track_title_delivering_courier') : t('track_title_delivering'),
    'done' => $selfPickup ? t('track_title_done') : ($deliveryMode ? t('track_title_delivered') : t('track_title_served')),
];
$banners = [
    'queue' => t('order_queue'),
    'cooking' => t('order_cooking'),
    'ready' => $selfPickup ? t('order_ready') : ($deliveryMode ? t('order_ready_delivery') : t('order_ready_waiter')),
    'delivering' => $deliveryMode ? t('order_delivering_courier') : t('order_delivering'),
    'done' => $selfPickup ? t('order_collected') : ($deliveryMode ? t('order_delivered_home') : t('order_arrived')),
];
$trackTitle = $titles[$stage] ?? t('order_sent');
$stepOrder = $selfPickup
    ? ['queue', 'cooking', 'ready', 'done']
    : ['queue', 'cooking', 'ready', 'delivering', 'done'];

function trackStepClass(string $step, string $stage, array $order): string
{
    $i = array_search($step, $order, true);
    $now = array_search($stage, $order, true);
    if ($i === false || $now === false) {
        return $step === $stage ? 'is-current' : '';
    }
    if ($i === $now) {
        return 'is-current';
    }
    return $i < $now ? 'is-done' : '';
}

$pollUrl = '';
if ($cafeMode && $sessionToken !== '') {
    $pollUrl = baseUrl('public/api/session_orders.php?s=' . urlencode($sessionToken) . '&focus=' . $orderId);
} elseif ($table) {
    $pollUrl = baseUrl(
        'public/api/table_orders.php?meja=' . urlencode($nomorMeja)
        . '&token=' . urlencode($token)
        . '&focus=' . $orderId
        . ($guestTokenParam !== '' ? '&gt=' . urlencode($guestTokenParam) : '')
    );
}
$orderAgainUrl = '';
if ($cafeMode && $sessionToken !== '') {
    $orderAgainUrl = cafeSessionOrderUrl($sessionToken);
} elseif ($deliveryMode && $table) {
    $shopRow = findShopById((int) ($table['shop_id'] ?? 0));
    if ($shopRow && shopDeliveryEnabled($shopRow)) {
        $orderAgainUrl = deliveryBrowseOrderUrl((string) $shopRow['slug'], ensureDeliveryToken($shopRow));
    }
} elseif ($table) {
    $orderAgainUrl = orderUrl($table['nomor_meja'], $table['token_akses']);
}
$trackSubtitle = $deliveryMode
    ? t('delivery_order_title')
    : ($cafeMode
        ? t('cafe_session_label', (string) ($session['nama_pelanggan'] ?? '—'))
        : t('table') . ' ' . ($table['nomor_meja'] ?? ''));
$shopRow = $table ? findShopById((int) ($table['shop_id'] ?? 0)) : null;
$duitnowQrData = $shopRow ? shopDuitnowQrDataUri($shopRow) : '';
$duitnowQrAbs = ($shopRow && !empty($shopRow['duitnow_qr_url']))
    ? shopDuitnowQrProxyUrl((int) $shopRow['id'], $shopRow)
    : '';
$duitnowQrDisplay = $duitnowQrData !== '' ? $duitnowQrData : $duitnowQrAbs;
$proofUploadUrl = baseUrl('public/api/upload_payment_proof.php');
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#e85d04">
  <title><?= e(t('order_sent')) ?> — <?= e($brand) ?></title>
  <?php
  require_once dirname(__DIR__) . '/includes/seo.php';
  seoFaviconLinks();
  ?>
  <link rel="stylesheet" href="<?= e(assetUrl('css/app.css')) ?>">
</head>
<body>
<?php if (!$focusOrder || !$table): ?>
  <div class="error-page">
    <h1><?= e($config['app_name']) ?></h1>
    <p><?= e($sessionToken !== '' ? t('cafe_session_expired') : t('invalid_table')) ?></p>
  </div>
<?php else: ?>
  <div class="confirm-page tracking" id="track-app" data-fulfillment="<?= e($selfPickup ? 'self_pickup' : ($deliveryMode ? 'delivery' : 'waiter')) ?>" data-stage="<?= e($stage) ?>" data-focus-order="<?= (int) $orderId ?>" data-meja="<?= e($table['nomor_meja']) ?>" data-token="<?= e($table['token_akses']) ?>" data-guest-token="<?= e($guestTokenParam) ?>" data-session="<?= e($cafeMode ? $sessionToken : '') ?>"<?= $selfPickup ? ' data-collect-url="' . e(baseUrl('public/api/collect_order.php')) . '"' : '' ?> data-poll-url="<?= e($pollUrl) ?>" data-proof-url="<?= e($proofUploadUrl) ?>" data-duitnow-qr="<?= e($duitnowQrDisplay) ?>" data-interval="<?= (int) ($config['poll_interval_ms'] ?? 4000) ?>" data-lang="<?= e($lang) ?>" data-i18n="<?= e(json_encode($trackI18n, JSON_UNESCAPED_UNICODE)) ?>">
    <div class="lang-toggle" style="position:absolute;top:16px;right:16px">
      <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
      <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
    </div>

    <div class="track-hero" id="track-hero" data-stage="<?= e($stage) ?>" aria-hidden="true">
      <div class="track-stage track-stage-queue"><span class="orb-ring"></span><span class="orb-ring delay"></span><span class="orb-ticket">#</span><i class="orb-dot d1"></i><i class="orb-dot d2"></i><i class="orb-dot d3"></i></div>
      <div class="track-stage track-stage-cooking"><span class="cook-pan"></span><span class="cook-lid"></span><span class="cook-steam s1"></span><span class="cook-steam s2"></span><span class="cook-steam s3"></span><span class="cook-flame"></span></div>
      <div class="track-stage track-stage-ready"><span class="ready-ping p1"></span><span class="ready-ping p2"></span><span class="ready-ping p3"></span><span class="ready-bag"><?= $selfPickup ? '!' : '✓' ?></span></div>
      <?php if ($deliveryMode): ?>
      <div class="track-stage track-stage-delivering track-stage-courier">
        <span class="courier-sky"></span>
        <span class="courier-road"></span>
        <span class="courier-dash d1"></span>
        <span class="courier-dash d2"></span>
        <span class="courier-dash d3"></span>
        <span class="courier-dash d4"></span>
        <span class="courier-scooter">
          <span class="courier-body"></span>
          <span class="courier-rider"></span>
          <span class="courier-bag"></span>
          <span class="courier-wheel w1"></span>
          <span class="courier-wheel w2"></span>
        </span>
        <span class="courier-house"></span>
        <span class="courier-pulse p1"></span>
        <span class="courier-pulse p2"></span>
      </div>
      <?php else: ?>
      <div class="track-stage track-stage-delivering"><span class="w-table"></span><span class="w-tray"></span><span class="w-person"></span></div>
      <?php endif; ?>
      <div class="track-stage track-stage-done"><span class="done-burst b1"></span><span class="done-burst b2"></span><span class="done-burst b3"></span><span class="done-check">✓</span></div>
    </div>

    <ol class="track-steps<?= $selfPickup ? '' : ' waiter' ?>" id="track-steps">
      <?php
        $stepLabels = [
            'queue' => t('step_queue'),
            'cooking' => t('step_cook'),
            'ready' => $selfPickup ? t('step_ready') : ($deliveryMode ? t('step_ready_delivery') : t('step_ready_waiter')),
            'delivering' => $deliveryMode ? t('step_deliver_courier') : t('step_deliver'),
            'done' => $deliveryMode ? t('step_done_delivery') : t('step_done'),
        ];
        foreach ($stepOrder as $step):
      ?>
        <li data-step="<?= e($step) ?>" class="<?= e(trackStepClass($step, $stage, $stepOrder)) ?>"><?= e($stepLabels[$step]) ?></li>
      <?php endforeach; ?>
    </ol>

    <h1 id="track-title"><?= e($trackTitle) ?></h1>
    <p style="color:var(--ink-muted);margin:0"><?= e($brand) ?> · <?= e($trackSubtitle) ?></p>
    <p class="track-hint"><?= e($selfPickup ? t('keep_page_open') : ($deliveryMode ? t('keep_page_open_delivery') : t('keep_page_open_waiter'))) ?></p>

    <h2 class="track-orders-heading" id="track-orders-heading"><?= e(t('your_orders')) ?><?= count($allOrders) > 1 ? ' (' . count($allOrders) . ')' : '' ?></h2>

    <div class="track-orders-list" id="track-orders-list">
      <?php foreach ($allOrders as $idx => $ord):
          $oid = (int) ($ord['order_id'] ?? 0);
          $isFocus = $oid === $orderId;
          $ordStage = (string) ($ord['stage'] ?? 'queue');
          $ordItems = $ord['items'] ?? [];
          $ordGuestToken = (string) ($ord['guest_token'] ?? $guestTokenParam);
      ?>
      <article class="track-order-card<?= $isFocus ? ' is-focus' : '' ?>" data-order-id="<?= $oid ?>" data-guest-token="<?= e($ordGuestToken) ?>" data-stage="<?= e($ordStage) ?>">
        <header class="track-order-card-head">
          <div>
            <span class="track-order-label"><?= e($isFocus ? t('order_latest') : t('order_earlier')) ?></span>
            <strong class="track-order-no">#<?= $oid ?></strong>
            <?php if (!empty($ord['nama_pelanggan'])): ?>
              <span class="track-order-name"><?= e((string) $ord['nama_pelanggan']) ?></span>
            <?php endif; ?>
          </div>
          <div class="confirm-status track-banner track-order-banner <?= e($ordStage) ?>">
            <?= e($banners[$ordStage] ?? t('order_waiting')) ?>
          </div>
        </header>
        <p class="track-order-meta">
          <?= e(
              ($ord['jenis_hidang'] ?? '') === 'delivery'
                  ? t('delivery_order_title')
                  : (($ord['jenis_hidang'] ?? '') === 'takeaway' ? t('takeaway') : t('dine_in'))
          ) ?>
          · <?= e(formatMoney((float) ($ord['total_harga'] ?? 0))) ?>
        </p>
        <ul class="track-items track-order-items">
          <?php foreach ($ordItems as $it): ?>
            <li class="<?= e(confirmItemClass($it)) ?>">
              <span class="track-item-pulse" aria-hidden="true"></span>
              <span class="track-item-name"><b><?= (int) ($it['qty'] ?? 0) ?>×</b> <?= e((string) ($it['nama'] ?? '')) ?></span>
              <span class="track-pill"><?= e(confirmItemLabel($it, $selfPickup)) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($selfPickup): ?>
        <button type="button" class="btn btn-success btn-collect-order" style="width:100%;margin-top:8px;<?= $ordStage === 'ready' ? '' : 'display:none' ?>" data-order-id="<?= $oid ?>" data-guest-token="<?= e($ordGuestToken) ?>">
          <?= e(t('i_collected')) ?> (#<?= $oid ?>)
        </button>
        <?php endif; ?>
        <?php
          $needsProof = (($ord['payment_method'] ?? '') === 'duitnow')
              && (($ord['status_bayar'] ?? 'belum_bayar') !== 'lunas')
              && in_array(($ord['payment_proof_status'] ?? 'none'), ['none', 'rejected'], true)
              && $ordGuestToken !== '';
          $proofWaiting = (($ord['payment_method'] ?? '') === 'duitnow')
              && (($ord['payment_proof_status'] ?? '') === 'uploaded');
          $showDuitnowQr = (($ord['payment_method'] ?? '') === 'duitnow')
              && (($ord['status_bayar'] ?? 'belum_bayar') !== 'lunas')
              && $duitnowQrDisplay !== ''
              && in_array(($ord['payment_proof_status'] ?? 'none'), ['none', 'rejected'], true);
        ?>
        <div class="track-payment-slot" data-proof-status="<?= e((string) ($ord['payment_proof_status'] ?? 'none')) ?>">
        <?php if ($showDuitnowQr): ?>
        <div class="duitnow-pay-block">
          <p class="order-meta"><?= e(t('duitnow_scan_hint')) ?></p>
          <div class="duitnow-pay-visual">
            <img class="duitnow-pay-qr" src="<?= e($duitnowQrDisplay) ?>" alt="DuitNow QR" width="220" height="220" decoding="async">
          </div>
          <a class="btn btn-secondary btn-sm duitnow-download" href="<?= e($duitnowQrAbs !== '' ? $duitnowQrAbs : $duitnowQrDisplay) ?>" download="duitnow-qr.png" target="_blank" rel="noopener"><?= e(t('download_qr')) ?></a>
        </div>
        <?php endif; ?>
        <?php if ($needsProof): ?>
        <form class="proof-upload-form" method="post" enctype="multipart/form-data" action="<?= e($proofUploadUrl) ?>" style="margin-top:12px">
          <input type="hidden" name="order_id" value="<?= $oid ?>">
          <input type="hidden" name="gt" value="<?= e($ordGuestToken) ?>">
          <p class="order-meta"><?= e(t('proof_upload_hint')) ?></p>
          <button type="button" class="btn btn-secondary btn-sm proof-pick-btn"><?= e(t('proof_choose_file')) ?></button>
          <p class="proof-file-name order-meta"><?= e(t('proof_no_file')) ?></p>
          <button type="submit" class="btn btn-primary btn-sm proof-upload-submit" style="width:100%;margin-top:8px" disabled><?= e(t('proof_upload_btn')) ?></button>
        </form>
        <?php elseif ($proofWaiting): ?>
          <p class="order-meta proof-waiting" style="margin-top:8px"><?= e(t('proof_waiting_kasir')) ?></p>
        <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <a class="btn btn-primary" href="<?= e($orderAgainUrl) ?>">
      <?= e(t('order_again')) ?>
    </a>
    <input type="file" id="proof-file-input" class="proof-file-input-hidden" accept="image/*,application/pdf,.pdf" tabindex="-1" aria-hidden="true">
  </div>
<?php endif; ?>
<?php if ($focusOrder && $table && $stage !== 'done'): ?>
<div class="sound-modal" id="sound-modal" role="dialog" aria-modal="true" aria-labelledby="sound-modal-title">
  <div class="sound-modal-card">
    <h2 id="sound-modal-title"><?= e(t('sound_popup_title')) ?></h2>
    <p><?= e($selfPickup ? t('sound_popup_body') : t('sound_popup_body_waiter')) ?></p>
    <button type="button" class="btn btn-primary" id="btn-sound-ok" style="width:100%"><?= e(t('sound_popup_ok')) ?></button>
  </div>
</div>
<?php endif; ?>
<script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
<?php if ($focusOrder && $table): ?>
<script src="<?= e(assetUrl('js/sound.js')) ?>"></script>
<script src="<?= e(assetUrl('js/live-poll.js')) ?>"></script>
<script src="<?= e(assetUrl('js/track.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
