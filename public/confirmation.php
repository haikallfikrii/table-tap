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
$nomorMeja = trim((string) ($_GET['meja'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$guestTokenParam = trim((string) ($_GET['gt'] ?? ''));

$table = null;
$allOrders = [];
$focusOrder = null;
$brand = $config['app_name'] ?? 'TableTap';
$selfPickup = false;

if ($nomorMeja !== '' && $token !== '') {
    $table = findTableByAccess($nomorMeja, $token);
    if ($table) {
        $brand = shopBrand($table);
        $selfPickup = shopFulfillment($table) === 'self_pickup';
        $allOrders = fetchActiveCustomerOrders($table, $lang);
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
    'status_deliver'  => t('status_item_diambil'),
    'status_done'     => $selfPickup ? t('status_item_dihantar') : t('status_item_served'),
    'order_queue'     => t('order_queue'),
    'order_cooking'   => t('order_cooking'),
    'order_ready'     => $selfPickup ? t('order_ready') : t('order_ready_waiter'),
    'order_delivering'=> t('order_delivering'),
    'order_collected' => $selfPickup ? t('order_collected') : t('order_arrived'),
    'title_queue'     => t('track_title_queue'),
    'title_cooking'   => t('track_title_cooking'),
    'title_ready'     => $selfPickup ? t('track_title_ready') : t('track_title_ready_waiter'),
    'title_delivering'=> t('track_title_delivering'),
    'title_done'      => $selfPickup ? t('track_title_done') : t('track_title_served'),
    'sound_popup_ok'  => t('sound_popup_ok'),
    'i_collected'     => t('i_collected'),
    'your_orders'     => t('your_orders'),
    'order_latest'    => t('order_latest'),
    'order_earlier'   => t('order_earlier'),
    'dine_in'         => t('dine_in'),
    'takeaway'        => t('takeaway'),
];

$stage = $focusOrder ? (string) ($focusOrder['stage'] ?? 'queue') : 'queue';
$titles = [
    'queue' => t('track_title_queue'),
    'cooking' => t('track_title_cooking'),
    'ready' => $selfPickup ? t('track_title_ready') : t('track_title_ready_waiter'),
    'delivering' => t('track_title_delivering'),
    'done' => $selfPickup ? t('track_title_done') : t('track_title_served'),
];
$banners = [
    'queue' => t('order_queue'),
    'cooking' => t('order_cooking'),
    'ready' => $selfPickup ? t('order_ready') : t('order_ready_waiter'),
    'delivering' => t('order_delivering'),
    'done' => $selfPickup ? t('order_collected') : t('order_arrived'),
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

$pollUrl = $table
    ? baseUrl('public/api/table_orders.php?meja=' . urlencode($nomorMeja) . '&token=' . urlencode($token) . '&focus=' . $orderId)
    : '';
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#e85d04">
  <title><?= e(t('order_sent')) ?> — <?= e($brand) ?></title>
  <link rel="stylesheet" href="<?= e(assetUrl('css/app.css')) ?>">
</head>
<body>
<?php if (!$focusOrder || !$table): ?>
  <div class="error-page">
    <h1><?= e($config['app_name']) ?></h1>
    <p><?= e(t('invalid_table')) ?></p>
  </div>
<?php else: ?>
  <div class="confirm-page tracking" id="track-app" data-fulfillment="<?= e($selfPickup ? 'self_pickup' : 'waiter') ?>" data-stage="<?= e($stage) ?>" data-focus-order="<?= (int) $orderId ?>" data-meja="<?= e($table['nomor_meja']) ?>" data-token="<?= e($table['token_akses']) ?>"<?= $selfPickup ? ' data-collect-url="' . e(baseUrl('public/api/collect_order.php')) . '"' : '' ?> data-poll-url="<?= e($pollUrl) ?>" data-interval="<?= (int) ($config['poll_interval_ms'] ?? 4000) ?>" data-lang="<?= e($lang) ?>" data-i18n="<?= e(json_encode($trackI18n, JSON_UNESCAPED_UNICODE)) ?>">
    <div class="lang-toggle" style="position:absolute;top:16px;right:16px">
      <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
      <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
    </div>

    <div class="track-hero" id="track-hero" data-stage="<?= e($stage) ?>" aria-hidden="true">
      <div class="track-stage track-stage-queue"><span class="orb-ring"></span><span class="orb-ring delay"></span><span class="orb-ticket">#</span><i class="orb-dot d1"></i><i class="orb-dot d2"></i><i class="orb-dot d3"></i></div>
      <div class="track-stage track-stage-cooking"><span class="cook-pan"></span><span class="cook-lid"></span><span class="cook-steam s1"></span><span class="cook-steam s2"></span><span class="cook-steam s3"></span><span class="cook-flame"></span></div>
      <div class="track-stage track-stage-ready"><span class="ready-ping p1"></span><span class="ready-ping p2"></span><span class="ready-ping p3"></span><span class="ready-bag"><?= $selfPickup ? '!' : '✓' ?></span></div>
      <div class="track-stage track-stage-delivering"><span class="w-table"></span><span class="w-tray"></span><span class="w-person"></span></div>
      <div class="track-stage track-stage-done"><span class="done-burst b1"></span><span class="done-burst b2"></span><span class="done-burst b3"></span><span class="done-check">✓</span></div>
    </div>

    <ol class="track-steps<?= $selfPickup ? '' : ' waiter' ?>" id="track-steps">
      <?php
        $stepLabels = [
            'queue' => t('step_queue'),
            'cooking' => t('step_cook'),
            'ready' => $selfPickup ? t('step_ready') : t('step_ready_waiter'),
            'delivering' => t('step_deliver'),
            'done' => t('step_done'),
        ];
        foreach ($stepOrder as $step):
      ?>
        <li data-step="<?= e($step) ?>" class="<?= e(trackStepClass($step, $stage, $stepOrder)) ?>"><?= e($stepLabels[$step]) ?></li>
      <?php endforeach; ?>
    </ol>

    <h1 id="track-title"><?= e($trackTitle) ?></h1>
    <p style="color:var(--ink-muted);margin:0"><?= e($brand) ?> · <?= e(t('table')) ?> <?= e($table['nomor_meja']) ?></p>
    <p class="track-hint"><?= e($selfPickup ? t('keep_page_open') : t('keep_page_open_waiter')) ?></p>

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
          <?= e(($ord['jenis_hidang'] ?? '') === 'takeaway' ? t('takeaway') : t('dine_in')) ?>
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
      </article>
      <?php endforeach; ?>
    </div>

    <a class="btn btn-primary" href="<?= e(orderUrl($table['nomor_meja'], $table['token_akses'])) ?>">
      <?= e(t('order_again')) ?>
    </a>
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
<script src="<?= e(assetUrl('js/track.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
