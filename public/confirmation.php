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

if ($orderId > 0 && $nomorMeja !== '' && $token !== '') {
    $table = findTableByAccess($nomorMeja, $token);
    if ($table) {
        $stmt = db()->prepare(
            'SELECT id, total_harga, status_order, waktu_order
             FROM orders
             WHERE id = ? AND table_id = ?
             LIMIT 1'
        );
        $stmt->execute([$orderId, (int) $table['id']]);
        $order = $stmt->fetch() ?: null;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#e85d04">
  <title><?= e(t('order_sent')) ?> — <?= e($config['app_name']) ?></title>
  <link rel="stylesheet" href="<?= e(assetUrl('css/app.css')) ?>">
</head>
<body>
<?php if (!$order || !$table): ?>
  <div class="error-page">
    <h1><?= e($config['app_name']) ?></h1>
    <p><?= e(t('invalid_table')) ?></p>
  </div>
<?php else: ?>
  <div class="confirm-page">
    <div class="lang-toggle" style="position:absolute;top:16px;right:16px">
      <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
      <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
    </div>
    <div class="confirm-icon" aria-hidden="true">✓</div>
    <h1><?= e(t('order_sent')) ?></h1>
    <p style="color:var(--ink-muted);margin:0"><?= e(t('table')) ?> <?= e($table['nomor_meja']) ?></p>
    <div class="order-no">#<?= (int) $order['id'] ?></div>
    <p style="margin:0 0 8px;font-weight:700"><?= e(formatMoney($order['total_harga'])) ?></p>
    <div class="confirm-status"><?= e(t('order_waiting')) ?></div>
    <a class="btn btn-primary" href="<?= e(orderUrl($table['nomor_meja'], $table['token_akses'])) ?>">
      <?= e(t('order_again')) ?>
    </a>
  </div>
<?php endif; ?>
<script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
</body>
</html>
