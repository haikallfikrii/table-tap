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
];
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
  <div class="confirm-page<?= $selfPickup ? ' tracking' : '' ?>"<?= $selfPickup ? ' id="track-app" data-poll-url="' . e(baseUrl('public/api/order_status.php?order=' . $orderId . '&meja=' . urlencode($nomorMeja) . '&token=' . urlencode($token))) . '" data-interval="' . (int) ($config['poll_interval_ms'] ?? 4000) . '" data-lang="' . e($lang) . '" data-i18n="' . e(json_encode($trackI18n, JSON_UNESCAPED_UNICODE)) . '"' : '' ?>>
    <div class="lang-toggle" style="position:absolute;top:16px;right:16px">
      <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
      <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
    </div>
    <div class="confirm-icon" aria-hidden="true">✓</div>
    <h1><?= e(t('order_sent')) ?></h1>
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
    <div class="confirm-status track-banner queue" id="track-banner">
      <?= e($selfPickup ? t('order_queue') : t('order_waiting')) ?>
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
            <span><b><?= (int) $it['qty'] ?>×</b> <?= e($nama) ?></span>
            <span class="track-pill"><?= e($label) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?= e(orderUrl($table['nomor_meja'], $table['token_akses'])) ?>">
      <?= e(t('order_again')) ?>
    </a>
  </div>
<?php endif; ?>
<script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
<?php if ($order && $table && $selfPickup): ?>
<script src="<?= e(assetUrl('js/sound.js')) ?>"></script>
<script src="<?= e(assetUrl('js/track.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
