<?php
/** Shared customer/staff menu + cart UI. Expects order page variables. */
$staffMode = $staffMode ?? false;
$staffBackUrl = $staffBackUrl ?? '';
$submitUrl = $submitUrl ?? baseUrl('public/api/submit_order.php');
$cafeBrowseMode = $cafeBrowseMode ?? false;
$deliveryMode = $deliveryMode ?? false;
$cafeVerify = $cafeVerify ?? 'email';
$payMethods = $payMethods ?? ['counter' => true, 'cod' => false, 'duitnow' => false];
$duitnowQrUrl = $duitnowQrUrl ?? '';
$deliveryRequirePhone = $deliveryRequirePhone ?? false;
$shopSlug = $shopSlug ?? '';
$shopTokenParam = $shopToken ?? '';
$checkoutUrl = $checkoutUrl ?? '';
$sendOtpUrl = $sendOtpUrl ?? '';
$checkoutNeedsOtp = $deliveryMode || in_array($cafeVerify, ['email', 'email_phone'], true);
$checkoutRequirePhone = $deliveryMode
    ? (bool) $deliveryRequirePhone
    : in_array($cafeVerify, ['phone', 'email_phone'], true);
$showGuestName = ($selfPickup || $staffMode) && !$cafeBrowseMode && empty($sessionToken);
$cafeMode = $cafeMode ?? false;
$sessionToken = $sessionToken ?? '';
$sessionOrderUrl = $sessionOrderUrl ?? '';
$prefillGuestName = $prefillGuestName ?? '';
$activeOrders = $activeOrders ?? [];
$trackOrdersUrl = $trackOrdersUrl ?? '';
$pageSubtitle = $pageSubtitle ?? ($cafeBrowseMode
    ? ($deliveryMode ? t('delivery_browse_sub') : t('cafe_browse_sub'))
    : ($cafeMode && $sessionToken !== ''
        ? t('cafe_session_label', (string) ($prefillGuestName ?: '—'))
        : ($cafeMode ? t('cafe_order_title') : t('table') . ' ' . $table['nomor_meja'] . ($staffMode ? ' · ' . t('staff_order') : ''))));
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#e85d04">
  <title><?= e($brand) ?> — <?= e($deliveryMode ? t('delivery_order_title') : ($cafeMode ? t('cafe_order_title') : t('table') . ' ' . $table['nomor_meja'])) ?></title>
  <?php
  require_once dirname(__DIR__) . '/includes/seo.php';
  seoFaviconLinks();
  ?>
  <link rel="stylesheet" href="<?= e(assetUrl('css/app.css')) ?>">
</head>
<body>
<?php if ($staffMode): ?>
  <div class="staff-order-bar">
    <a href="<?= e($staffBackUrl) ?>"><?= e(t('staff_back')) ?></a>
    <span><?= e(t('staff_order_for')) ?> <?= e($table['nomor_meja']) ?></span>
  </div>
<?php endif; ?>
<div
  id="order-app"
  class="customer-app<?= $staffMode ? ' staff-mode' : '' ?><?= $cafeMode ? ' cafe-mode' : '' ?><?= $deliveryMode ? ' delivery-mode' : '' ?>"
  data-meja="<?= e($table['nomor_meja']) ?>"
  data-token="<?= e($staffMode || $cafeMode ? '' : $table['token_akses']) ?>"
  data-session="<?= e($sessionToken) ?>"
  data-session-url="<?= e($sessionOrderUrl) ?>"
  data-cafe-browse="<?= $cafeBrowseMode ? '1' : '0' ?>"
  data-delivery="<?= $deliveryMode ? '1' : '0' ?>"
  data-shop="<?= e($shopSlug) ?>"
  data-shop-token="<?= e($shopTokenParam) ?>"
  data-cafe-verify="<?= e($cafeVerify) ?>"
  data-needs-otp="<?= $checkoutNeedsOtp ? '1' : '0' ?>"
  data-require-phone="<?= $checkoutRequirePhone ? '1' : '0' ?>"
  data-pay-methods="<?= e(json_encode($payMethods)) ?>"
  data-duitnow-qr="<?= e($duitnowQrUrl) ?>"
  data-checkout-url="<?= e($checkoutUrl) ?>"
  data-send-otp-url="<?= e($sendOtpUrl) ?>"
  data-prefill-name="<?= e($prefillGuestName) ?>"
  data-table-id="<?= (int) $table['id'] ?>"
  data-submit-url="<?= e($submitUrl) ?>"
  data-fulfillment="<?= e($selfPickup ? 'self_pickup' : 'waiter') ?>"
  data-staff="<?= $staffMode ? '1' : '0' ?>"
  data-from="<?= e($staffFrom ?? '') ?>"
  data-i18n="<?= e(json_encode($i18nJs, JSON_UNESCAPED_UNICODE)) ?>"
>
  <header class="customer-header">
    <div class="brand">
      <span class="brand-name"><?= e($brand) ?></span>
      <span class="brand-table"><?= e($pageSubtitle) ?></span>
    </div>
    <div class="header-actions">
      <button
        type="button"
        class="menu-search-toggle"
        id="menu-search-toggle"
        aria-label="<?= e(t('search')) ?>"
        aria-expanded="false"
        aria-controls="menu-search-panel"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
      <?php if ($cafeMode && !$staffMode && $sessionToken !== ''): ?>
      <button type="button" class="btn btn-ghost btn-sm cafe-link-btn" id="btn-my-link" title="<?= e(t('cafe_my_link')) ?>"><?= e(t('cafe_my_link')) ?></button>
      <?php endif; ?>
      <div class="lang-toggle">
        <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
        <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
      </div>
    </div>
  </header>

  <?php if (!$staffMode && count($activeOrders ?? []) > 0): ?>
  <a class="active-orders-bar" href="<?= e($trackOrdersUrl ?? '') ?>">
    <?= e(t('active_orders_banner', count($activeOrders))) ?>
  </a>
  <?php endif; ?>

  <div class="menu-sticky-bar" id="menu-sticky-bar">
    <div class="menu-search-panel" id="menu-search-panel" aria-hidden="true">
      <label class="menu-search" for="menu-search">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input type="search" id="menu-search" placeholder="<?= e(t('menu_search_ph')) ?>" autocomplete="off" enterkeyhint="search">
        <button type="button" class="menu-search-clear" id="menu-search-clear" aria-label="<?= e(t('close')) ?>" hidden>&times;</button>
      </label>
      <p class="menu-search-empty" id="menu-search-empty" hidden><?= e(t('menu_search_empty')) ?></p>
    </div>

    <nav class="category-tabs" id="category-tabs" aria-label="<?= e(t('category')) ?>">
    <?php
      $menuCategories = $menu['categories'] ?? [];
      if ($menuCategories === [] && isset($menu['makanan'])) {
          $menuCategories = [
              ['kod' => 'makanan', 'label' => t('makanan'), 'items' => $menu['makanan'] ?? []],
              ['kod' => 'minuman', 'label' => t('minuman'), 'items' => $menu['minuman'] ?? []],
          ];
      }
      foreach ($menuCategories as $i => $cat):
    ?>
      <a href="#cat-<?= e((string) $cat['kod']) ?>" class="<?= $i === 0 ? 'active' : '' ?>"><?= e((string) $cat['label']) ?></a>
    <?php endforeach; ?>
    </nav>
  </div>

  <?php foreach ($menuCategories as $cat): ?>
    <section class="menu-section" id="cat-<?= e((string) $cat['kod']) ?>">
      <h2><?= e((string) $cat['label']) ?></h2>
      <div class="menu-list">
        <?php if (empty($cat['items'])): ?>
          <p style="color:var(--ink-muted)"><?= e(t('no_data')) ?></p>
        <?php else: ?>
          <?php foreach ($cat['items'] as $item):
            $out = $item['status_stok'] === 'habis';
            $initial = mb_strtoupper(mb_substr($item['nama'], 0, 1));
            $photos = [];
            foreach ($item['gallery'] ?? [] as $src) {
                $photos[] = baseUrl($src);
            }
            $detail = [
                'id' => (int) $item['id'],
                'nama' => $item['nama'],
                'desc' => (string) ($item['deskripsi'] ?? ''),
                'harga' => (float) $item['harga'],
                'harga_l' => formatMoney((float) $item['harga']),
                'photos' => $photos,
                'out' => $out,
            ];
            $searchText = mb_strtolower(
                ($item['nama'] ?? '') . ' '
                . ($item['nama_my'] ?? '') . ' '
                . ($item['nama_en'] ?? '') . ' '
                . ($item['deskripsi_my'] ?? '') . ' '
                . ($item['deskripsi_en'] ?? '')
            );
          ?>
            <article class="menu-item<?= $out ? ' out' : '' ?>" data-search="<?= e($searchText) ?>">
              <?php if (!empty($item['foto_url'])): ?>
                <img class="menu-item-photo<?= $canGallery ? ' tap' : '' ?>" src="<?= e(baseUrl($item['foto_url'])) ?>" alt="<?= e($item['nama']) ?>" loading="lazy"<?= $canGallery ? ' data-open-detail=\'' . e(json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) . '\'' : '' ?>>
              <?php else: ?>
                <div class="menu-item-photo placeholder<?= $canGallery ? ' tap' : '' ?>" aria-hidden="true"<?= $canGallery ? ' data-open-detail=\'' . e(json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) . '\'' : '' ?>><?= e($initial) ?></div>
              <?php endif; ?>
              <div class="menu-item-body"<?= $canGallery ? ' data-open-detail=\'' . e(json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) . '\'' : '' ?>>
                <h3><?= e($item['nama']) ?></h3>
                <?php if ($item['deskripsi']): ?>
                  <p><?= e($item['deskripsi']) ?></p>
                <?php endif; ?>
                <span class="menu-item-price">
                  <?= e(formatMoney($item['harga'])) ?>
                  <?php if ($out): ?> · <?= e(t('out_of_stock')) ?><?php endif; ?>
                  <?php if ($canGallery): ?> · <?= e(t('menu_see_detail')) ?><?php endif; ?>
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
    <?php if (!$deliveryMode): ?>
    <div class="serve-toggle" role="group" aria-label="<?= e(t('serving_type')) ?>">
      <button type="button" class="serve-opt on" data-serve="dine_in"><?= e(t('dine_in')) ?></button>
      <button type="button" class="serve-opt" data-serve="takeaway"><?= e(t('takeaway')) ?></button>
    </div>
    <?php else: ?>
    <p class="order-meta delivery-serve-note"><?= e(t('delivery_serve_note')) ?></p>
    <?php endif; ?>
    <?php if ($showGuestName): ?>
      <div class="guest-name-field">
        <label for="guest-name"><?= e(t('guest_name')) ?></label>
        <input type="text" id="guest-name" maxlength="40" autocomplete="name" placeholder="<?= e(t('guest_name_ph')) ?>" value="<?= e($prefillGuestName) ?>" <?= $selfPickup ? 'required' : '' ?>>
        <p class="order-meta"><?= e($staffMode && !$selfPickup ? t('guest_name_staff_hint') : t('guest_name_hint')) ?></p>
      </div>
    <?php elseif ($cafeMode && $selfPickup && $prefillGuestName !== ''): ?>
      <p class="order-meta cafe-name-note"><?= e(t('cafe_order_as', $prefillGuestName)) ?></p>
    <?php endif; ?>
    <button type="button" class="btn btn-primary" id="btn-submit-order" style="width:100%" disabled>
      <?= e(t('submit_order')) ?>
    </button>
  </div>
</aside>

<?php if ($cafeBrowseMode): ?>
<div class="sheet-overlay" id="checkout-overlay"></div>
<aside class="cart-sheet cafe-checkout-sheet" id="checkout-sheet" aria-label="<?= e($deliveryMode ? t('delivery_checkout_title') : t('cafe_checkout_title')) ?>">
  <div class="cart-sheet-header">
    <h2><?= e($deliveryMode ? t('delivery_checkout_title') : t('cafe_checkout_title')) ?></h2>
    <button type="button" class="btn btn-ghost btn-sm" id="btn-close-checkout"><?= e(t('close')) ?></button>
  </div>
  <div class="cart-sheet-body">
    <p class="order-meta"><?= e($deliveryMode ? t('delivery_checkout_hint') : t('cafe_checkout_hint')) ?></p>
    <div class="form-group" id="checkout-step-details">
      <label for="checkout-name"><?= e(t('guest_name')) ?></label>
      <input type="text" id="checkout-name" maxlength="40" autocomplete="name" placeholder="<?= e(t('guest_name_ph')) ?>">

      <?php if ($deliveryMode): ?>
      <label for="checkout-address" style="margin-top:12px"><?= e(t('address')) ?></label>
      <textarea id="checkout-address" rows="3" maxlength="500" placeholder="<?= e(t('address_ph')) ?>"></textarea>
      <?php endif; ?>

      <?php
        $showCheckoutEmail = $deliveryMode || in_array($cafeVerify, ['email', 'email_phone'], true);
        $showCheckoutPhone = $deliveryMode
            ? $deliveryRequirePhone
            : in_array($cafeVerify, ['phone', 'email_phone'], true);
        $checkoutNeedsOtp = $deliveryMode || in_array($cafeVerify, ['email', 'email_phone'], true);
      ?>
      <?php if ($showCheckoutEmail): ?>
      <label for="checkout-email" style="margin-top:12px"><?= e(t('cafe_email')) ?></label>
      <input type="email" id="checkout-email" maxlength="255" autocomplete="email" placeholder="<?= e(t('cafe_email_ph')) ?>" required>
      <p class="order-meta cafe-spam-note"><?= e($deliveryMode ? t('delivery_email_otp_note') : t('cafe_spam_note')) ?></p>
      <?php endif; ?>
      <?php if ($showCheckoutPhone): ?>
      <label for="checkout-phone" style="margin-top:12px"><?= e(t('phone')) ?><?= $deliveryMode || $cafeVerify === 'email_phone' ? ' *' : '' ?></label>
      <input type="tel" id="checkout-phone" maxlength="20" autocomplete="tel" placeholder="<?= e(t('phone_ph')) ?>" inputmode="tel" <?= ($deliveryMode && $deliveryRequirePhone) || $cafeVerify === 'phone' || $cafeVerify === 'email_phone' ? 'required' : '' ?>>
      <?php endif; ?>

      <?php if ($deliveryMode): ?>
      <fieldset class="pay-method-fieldset" style="margin-top:14px">
        <legend><?= e(t('pay_method')) ?></legend>
        <?php
          $firstPay = null;
          foreach (['cod' => 'pay_cod', 'duitnow' => 'pay_duitnow', 'counter' => 'pay_counter'] as $code => $labelKey) {
              if (empty($payMethods[$code])) {
                  continue;
              }
              if ($firstPay === null) {
                  $firstPay = $code;
              }
              ?>
          <label class="pay-method-opt">
            <input type="radio" name="pay_method" value="<?= e($code) ?>" <?= $firstPay === $code ? 'checked' : '' ?>>
            <span><?= e(t($labelKey)) ?></span>
          </label>
        <?php } ?>
      </fieldset>
      <?php if (!empty($payMethods['duitnow']) && $duitnowQrUrl !== ''):
        $duitnowQrAbs = baseUrl($duitnowQrUrl);
      ?>
        <div class="duitnow-preview is-collapsed" id="duitnow-preview">
          <p class="order-meta"><?= e(t('duitnow_scan_hint')) ?></p>
          <div class="duitnow-pay-visual">
            <img class="duitnow-pay-qr" src="" data-src="<?= e($duitnowQrAbs) ?>" alt="DuitNow QR" width="220" height="220" loading="lazy" decoding="async">
          </div>
          <a class="btn btn-secondary btn-sm duitnow-download" href="<?= e($duitnowQrAbs) ?>" download="duitnow-qr.png" target="_blank" rel="noopener"><?= e(t('download_qr')) ?></a>
        </div>
      <?php endif; ?>
      <?php endif; ?>

      <button type="button" class="btn btn-primary" id="btn-checkout-send" style="width:100%;margin-top:16px">
        <?= e($checkoutNeedsOtp ? t('cafe_send_code') : t('cafe_confirm_order')) ?>
      </button>
    </div>
    <div id="checkout-step-otp" hidden>
      <p class="order-meta" id="checkout-otp-sent"></p>
      <label for="checkout-otp"><?= e(t('cafe_otp_label')) ?></label>
      <input type="text" id="checkout-otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000">
      <button type="button" class="btn btn-primary" id="btn-checkout-verify" style="width:100%;margin-top:16px"><?= e(t('cafe_confirm_order')) ?></button>
    </div>
  </div>
</aside>
<?php endif; ?>

<?php if ($cafeMode && !$cafeBrowseMode && !$staffMode && $sessionToken !== ''): ?>
<div class="sheet-overlay" id="link-overlay"></div>
<aside class="cart-sheet cafe-link-sheet" id="link-sheet" aria-label="<?= e(t('cafe_my_link')) ?>">
  <div class="cart-sheet-header">
    <h2><?= e(t('cafe_my_link')) ?></h2>
    <button type="button" class="btn btn-ghost btn-sm" id="btn-close-link"><?= e(t('close')) ?></button>
  </div>
  <div class="cart-sheet-body">
    <p class="order-meta"><?= e(t('cafe_my_link_hint')) ?></p>
    <div class="cafe-link-qr">
      <img id="cafe-link-qr" src="" alt="QR" width="200" height="200">
    </div>
    <div class="form-group">
      <label><?= e(t('cafe_my_link_url')) ?></label>
      <input type="text" id="cafe-link-url" readonly value="<?= e($sessionOrderUrl) ?>">
    </div>
    <button type="button" class="btn btn-primary" id="btn-copy-link" style="width:100%"><?= e(t('cafe_copy_link')) ?></button>
  </div>
</aside>
<?php endif; ?>

<?php if ($canGallery): ?>
<div class="sheet-overlay" id="detail-overlay"></div>
<aside class="cart-sheet detail-sheet" id="detail-sheet" aria-label="<?= e(t('menu_detail')) ?>">
  <div class="cart-sheet-header">
    <h2 id="detail-title"><?= e(t('menu_detail')) ?></h2>
    <button type="button" class="btn btn-ghost btn-sm" id="btn-close-detail"><?= e(t('close')) ?></button>
  </div>
  <div class="cart-sheet-body" id="detail-body"></div>
  <div class="cart-sheet-footer">
    <button type="button" class="btn btn-primary" id="detail-add" style="width:100%"><?= e(t('add_to_cart')) ?></button>
  </div>
</aside>
<?php endif; ?>

<script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
<?php if (!$staffMode): ?>
<script src="<?= e(assetUrl('js/sound.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(assetUrl('js/order.js')) ?>"></script>
</body>
</html>
