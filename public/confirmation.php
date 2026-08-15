<?php
/**
 * Order confirmation page after successful submit.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

$lang = currentLang();
$config = getConfig();

$orderId = (int) ($_GET['order'] ?? 0);
$nomorMeja = trim((string) ($_GET['meja'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

$order = null;
$table = null;
$items = [];
$brand = $config['app_name'] ?? 'TableTap';
$selfPickup = false;

if ($orderId > 0 && $nomorMeja !== '' && $token !== '') {
    $table = findTableByAccess($nomorMeja, $token);
    if ($table) {
        $brand = shopBrand($table);
        $selfPickup = shopFulfillment($table) === 'self_pickup';
        $stmt = db()->prepare(
            'SELECT id, subtotal, sst_rate, sst_jumlah, total_harga, status_order, waktu_order, jenis_hidang, nama_pelanggan
             FROM orders
             WHERE id = ? AND table_id = ? AND shop_id = ?
             LIMIT 1'
        );
        $stmt->execute([$orderId, (int) $table['id'], (int) $table['shop_id']]);
        $order = $stmt->fetch() ?: null;
        if ($order && $selfPickup) {
            $itemStmt = db()->prepare(
                'SELECT qty, status_item, nama_saat_order_my, nama_saat_order_en
                 FROM order_items WHERE order_id = ? ORDER BY id ASC'
            );
            $itemStmt->execute([$orderId]);
            $items = $itemStmt->fetchAll();
        }
    }
}

$trackI18n = [
    'status_queue'    => t('status_item_menunggu'),
    'status_cooking'  => t('status_item_sedang'),
    'status_ready'    => t('status_item_siap'),
    'status_done'     => t('status_item_dihantar'),
    'order_queue'     => t('order_queue'),
    'order_cooking'   => t('order_cooking'),
    'order_ready'     => t('order_ready'),
    'order_collected' => t('order_collected'),
    'title_queue'     => t('track_title_queue'),
    'title_cooking'   => t('track_title_cooking'),
    'title_ready'     => t('track_title_ready'),
    'title_done'      => t('track_title_done'),
    'sound_popup_ok'  => t('sound_popup_ok'),
    'i_collected'     => t('i_collected'),
];
$stage = $selfPickup ? trackStageFromItems($items) : 'queue';
$trackTitle = $selfPickup
    ? ($stage === 'cooking' ? t('track_title_cooking')
        : ($stage === 'ready' ? t('track_title_ready')
            : ($stage === 'done' ? t('track_title_done') : t('track_title_queue'))))
    : t('order_sent');
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
<?php if (!$order || !$table): ?>
  <div class="error-page">
    <h1><?= e($config['app_name']) ?></h1>
    <p><?= e(t('invalid_table')) ?></p>
  </div>
<?php else: ?>
  <div class="confirm-page<?= $selfPickup ? ' tracking' : '' ?>"<?= $selfPickup ? ' id="track-app" data-stage="' . e($stage) . '" data-order="' . (int) $order['id'] . '" data-meja="' . e($table['nomor_meja']) . '" data-token="' . e($table['token_akses']) . '" data-collect-url="' . e(baseUrl('public/api/collect_order.php')) . '" data-poll-url="' . e(baseUrl('public/api/order_status.php?order=' . $orderId . '&meja=' . urlencode($nomorMeja) . '&token=' . urlencode($token))) . '" data-interval="' . (int) ($config['poll_interval_ms'] ?? 4000) . '" data-lang="' . e($lang) . '" data-i18n="' . e(json_encode($trackI18n, JSON_UNESCAPED_UNICODE)) . '"' : '' ?>>
    <div class="lang-toggle" style="position:absolute;top:16px;right:16px">
      <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
      <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
    </div>
    <?php if ($selfPickup): ?>
      <div class="track-hero" id="track-hero" data-stage="<?= e($stage) ?>" aria-hidden="true">
        <div class="track-stage track-stage-queue">
          <span class="orb-ring"></span>
          <span class="orb-ring delay"></span>
          <span class="orb-ticket">#</span>
          <i class="orb-dot d1"></i>
          <i class="orb-dot d2"></i>
          <i class="orb-dot d3"></i>
        </div>
        <div class="track-stage track-stage-cooking">
          <span class="cook-pan"></span>
          <span class="cook-lid"></span>
          <span class="cook-steam s1"></span>
          <span class="cook-steam s2"></span>
          <span class="cook-steam s3"></span>
          <span class="cook-flame"></span>
        </div>
        <div class="track-stage track-stage-ready">
          <span class="ready-ping p1"></span>
          <span class="ready-ping p2"></span>
          <span class="ready-ping p3"></span>
          <span class="ready-bag">!</span>
        </div>
        <div class="track-stage track-stage-done">
          <span class="done-burst b1"></span>
          <span class="done-burst b2"></span>
          <span class="done-burst b3"></span>
          <span class="done-check">✓</span>
        </div>
      </div>
      <ol class="track-steps" id="track-steps">
        <li data-step="queue" class="<?= $stage === 'queue' ? 'is-current' : ($stage !== 'queue' ? 'is-done' : '') ?>"><?= e(t('step_queue')) ?></li>
        <li data-step="cooking" class="<?= $stage === 'cooking' ? 'is-current' : (in_array($stage, ['ready', 'done'], true) ? 'is-done' : '') ?>"><?= e(t('step_cook')) ?></li>
        <li data-step="ready" class="<?= $stage === 'ready' ? 'is-current' : ($stage === 'done' ? 'is-done' : '') ?>"><?= e(t('step_ready')) ?></li>
        <li data-step="done" class="<?= $stage === 'done' ? 'is-current' : '' ?>"><?= e(t('step_done')) ?></li>
      </ol>
    <?php else: ?>
      <div class="confirm-icon" aria-hidden="true">✓</div>
    <?php endif; ?>
    <h1 id="track-title"><?= e($trackTitle) ?></h1>
    <p style="color:var(--ink-muted);margin:0"><?= e($brand) ?> · <?= e(t('table')) ?> <?= e($table['nomor_meja']) ?></p>
    <?php if (!empty($order['nama_pelanggan'])): ?>
      <p style="margin:6px 0 0;font-weight:800"><?= e($order['nama_pelanggan']) ?></p>
    <?php endif; ?>
    <div class="order-no">#<?= (int) $order['id'] ?></div>
    <p style="margin:0;font-weight:800;font-size:1.05rem">
      <?= e(($order['jenis_hidang'] ?? '') === 'takeaway' ? t('takeaway') : t('dine_in')) ?>
    </p>
    <?php if ((float) $order['sst_jumlah'] > 0): ?>
      <p style="margin:0;color:var(--ink-muted);font-size:0.9rem">
        <?= e(t('subtotal')) ?>: <?= e(formatMoney($order['subtotal'])) ?><br>
        <?= e(t('sst')) ?> (<?= e(number_format((float) $order['sst_rate'], 2)) ?>%): <?= e(formatMoney($order['sst_jumlah'])) ?>
      </p>
    <?php endif; ?>
    <p style="margin:8px 0;font-weight:700;font-size:1.2rem"><?= e(formatMoney($order['total_harga'])) ?></p>
    <div class="confirm-status track-banner <?= e($selfPickup ? $stage : 'queue') ?>" id="track-banner">
      <?= e($selfPickup
          ? ($stage === 'cooking' ? t('order_cooking')
              : ($stage === 'ready' ? t('order_ready')
                  : ($stage === 'done' ? t('order_collected') : t('order_queue'))))
          : t('order_waiting')) ?>
    </div>
    <?php if ($selfPickup): ?>
      <p class="track-hint"><?= e(t('keep_page_open')) ?></p>
      <ul class="track-items" id="track-items">
        <?php foreach ($items as $it):
            $st = (string) $it['status_item'];
            $cls = $st === 'sedang_dimasak' ? 'cooking' : ($st === 'siap' || $st === 'diambil' ? 'ready' : ($st === 'dihantar' ? 'done' : 'queue'));
            $label = $st === 'sedang_dimasak' ? t('status_item_sedang') : ($st === 'siap' || $st === 'diambil' ? t('status_item_siap') : ($st === 'dihantar' ? t('status_item_dihantar') : t('status_item_menunggu')));
            $nama = $lang === 'en' ? $it['nama_saat_order_en'] : $it['nama_saat_order_my'];
        ?>
          <li class="<?= e($cls) ?>">
            <span class="track-item-pulse" aria-hidden="true"></span>
            <span><b><?= (int) $it['qty'] ?>×</b> <?= e($nama) ?></span>
            <span class="track-pill"><?= e($label) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <button type="button" class="btn btn-success" id="btn-i-collected" style="width:100%;margin-bottom:16px;<?= $stage === 'ready' ? '' : 'display:none' ?>">
        <?= e(t('i_collected')) ?>
      </button>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?= e(orderUrl($table['nomor_meja'], $table['token_akses'])) ?>">
      <?= e(t('order_again')) ?>
    </a>
  </div>
<?php endif; ?>
<?php if ($order && $table && $selfPickup && $stage !== 'done'): ?>
<div class="sound-modal" id="sound-modal" role="dialog" aria-modal="true" aria-labelledby="sound-modal-title">
  <div class="sound-modal-card">
    <h2 id="sound-modal-title"><?= e(t('sound_popup_title')) ?></h2>
    <p><?= e(t('sound_popup_body')) ?></p>
    <button type="button" class="btn btn-primary" id="btn-sound-ok" style="width:100%"><?= e(t('sound_popup_ok')) ?></button>
  </div>
</div>
<?php endif; ?>
<script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
<?php if ($order && $table && $selfPickup): ?>
<script src="<?= e(assetUrl('js/sound.js')) ?>"></script>
<script src="<?= e(assetUrl('js/track.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
