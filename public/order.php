<?php
/**
 * Customer order page — accessed via QR: /public/order.php?meja=5&token=xxx
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

$lang = currentLang();
$config = getConfig();

$nomorMeja = trim((string) ($_GET['meja'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

$table = null;
if ($nomorMeja !== '' && $token !== '') {
    $table = findTableByAccess($nomorMeja, $token);
}

$brand = $table ? shopBrand($table) : ($config['app_name'] ?? 'TableTap');
$menu = $table ? getMenuGrouped((int) $table['shop_id'], $lang) : [];
$sstEnabled = $table && (int) ($table['sst_enabled'] ?? 0) === 1;
$sstRate = $table ? (float) ($table['sst_rate'] ?? 0) : 0;
$selfPickup = $table && shopFulfillment($table) === 'self_pickup';

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
];
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#e85d04">
  <title><?= e($brand) ?> — <?= e(t('table')) ?> <?= e($nomorMeja) ?></title>
  <link rel="stylesheet" href="<?= e(assetUrl('css/app.css')) ?>">
</head>
<body>
<?php if (!$table): ?>
  <div class="error-page">
    <div class="lang-toggle" style="margin:0 auto 24px">
      <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
      <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
    </div>
    <h1><?= e($config['app_name']) ?></h1>
    <p><?= e(t('invalid_table')) ?></p>
  </div>
  <script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
</body>
</html>
<?php exit; endif; ?>

<div
  id="order-app"
  class="customer-app"
  data-meja="<?= e($table['nomor_meja']) ?>"
  data-token="<?= e($table['token_akses']) ?>"
  data-submit-url="<?= e(baseUrl('public/api/submit_order.php')) ?>"
  data-fulfillment="<?= e($selfPickup ? 'self_pickup' : 'waiter') ?>"
  data-i18n="<?= e(json_encode($i18nJs, JSON_UNESCAPED_UNICODE)) ?>"
>
  <header class="customer-header">
    <div class="brand">
      <span class="brand-name"><?= e($brand) ?></span>
      <span class="brand-table"><?= e(t('table')) ?> <?= e($table['nomor_meja']) ?></span>
    </div>
    <div class="lang-toggle">
      <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
      <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
    </div>
  </header>

  <nav class="category-tabs">
    <a href="#makanan" class="active"><?= e(t('makanan')) ?></a>
    <a href="#minuman"><?= e(t('minuman')) ?></a>
  </nav>

  <?php foreach (['makanan', 'minuman'] as $kat): ?>
    <section class="menu-section" id="<?= e($kat) ?>">
      <h2><?= e(t($kat)) ?></h2>
      <div class="menu-list">
        <?php if (empty($menu[$kat])): ?>
          <p style="color:var(--ink-muted)"><?= e(t('no_data')) ?></p>
        <?php else: ?>
          <?php foreach ($menu[$kat] as $item):
            $out = $item['status_stok'] === 'habis';
            $initial = mb_strtoupper(mb_substr($item['nama'], 0, 1));
          ?>
            <article class="menu-item<?= $out ? ' out' : '' ?>">
              <?php if (!empty($item['foto_url'])): ?>
                <img class="menu-item-photo" src="<?= e(baseUrl($item['foto_url'])) ?>" alt="<?= e($item['nama']) ?>" loading="lazy">
              <?php else: ?>
                <div class="menu-item-photo placeholder" aria-hidden="true"><?= e($initial) ?></div>
              <?php endif; ?>
              <div class="menu-item-body">
                <h3><?= e($item['nama']) ?></h3>
                <?php if ($item['deskripsi']): ?>
                  <p><?= e($item['deskripsi']) ?></p>
                <?php endif; ?>
                <span class="menu-item-price">
                  <?= e(formatMoney($item['harga'])) ?>
                  <?php if ($out): ?> · <?= e(t('out_of_stock')) ?><?php endif; ?>
                </span>
              </div>
              <button
                type="button"
                class="btn-icon"
                data-add-item="<?= (int) $item['id'] ?>"
                data-nama="<?= e($item['nama']) ?>"
                data-harga="<?= e($item['harga']) ?>"
                <?= $out ? 'disabled' : '' ?>
                aria-label="<?= e(t('add_to_cart')) ?>"
              >+</button>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>

<div class="cart-bar" id="cart-bar">
  <button type="button" class="cart-bar-btn" id="cart-bar-btn">
    <span>
      <span class="cart-bar-count" id="cart-bar-count">0</span>
      <?= e(t('view_cart')) ?>
    </span>
    <span id="cart-bar-total">RM 0.00</span>
  </button>
</div>

<div class="sheet-overlay" id="sheet-overlay"></div>
<aside class="cart-sheet" id="cart-sheet" aria-label="<?= e(t('cart')) ?>">
  <div class="cart-sheet-header">
    <h2><?= e(t('cart')) ?></h2>
    <button type="button" class="btn btn-ghost btn-sm" id="btn-close-cart"><?= e(t('close')) ?></button>
  </div>
  <div class="cart-sheet-body" id="cart-sheet-body"></div>
  <div class="cart-sheet-footer">
    <div id="cart-sst-rows"></div>
    <div class="cart-total-row">
      <span><?= e(t('total')) ?></span>
      <span id="cart-sheet-total">RM 0.00</span>
    </div>
    <div class="serve-toggle" role="group" aria-label="<?= e(t('serving_type')) ?>">
      <button type="button" class="serve-opt on" data-serve="dine_in"><?= e(t('dine_in')) ?></button>
      <button type="button" class="serve-opt" data-serve="takeaway"><?= e(t('takeaway')) ?></button>
    </div>
    <?php if ($selfPickup): ?>
      <div class="guest-name-field">
        <label for="guest-name"><?= e(t('guest_name')) ?></label>
        <input type="text" id="guest-name" maxlength="40" autocomplete="name" placeholder="<?= e(t('guest_name_ph')) ?>" required>
        <p class="order-meta"><?= e(t('guest_name_hint')) ?></p>
      </div>
    <?php endif; ?>
    <button type="button" class="btn btn-primary" id="btn-submit-order" style="width:100%" disabled>
      <?= e(t('submit_order')) ?>
    </button>
  </div>
</aside>

<script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
<?php if ($selfPickup): ?>
<script src="<?= e(assetUrl('js/sound.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(assetUrl('js/order.js')) ?>"></script>
</body>
</html>
