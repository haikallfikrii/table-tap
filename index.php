<?php
/**
 * TableTap — public landing page.
 * Plan prices are read from the packages table when available so marketing
 * copy never drifts from what the master panel actually sells.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/blog.php';

$lang = currentLang();
$config = getConfig();

$prices = ['basic' => 29.0, 'standard' => 49.0, 'pro' => 99.0];
try {
    foreach (db()->query('SELECT kod, harga_bulanan FROM packages WHERE is_active = 1')->fetchAll() as $row) {
        if (isset($prices[$row['kod']])) {
            $prices[$row['kod']] = (float) $row['harga_bulanan'];
        }
    }
} catch (Throwable $e) {
    // Landing page must render even before the database is imported.
}

$yearlyDiscount = 0.15; // 15% off annual vs paying month-to-month

$plans = [
    [
        'name'     => t('lp_plan_basic'),
        'kod'      => 'basic',
        'desc'     => t('lp_plan_basic_d'),
        'monthly'  => $prices['basic'],
        'featured' => false,
        'features' => [
            t('lp_pf_tables_10'),
            t('lp_pf_menu'),
            t('lp_pf_dash'),
            t('lp_pf_hist_30'),
            t('lp_pf_staff_5'),
            t('lp_pf_support_mail'),
        ],
    ],
    [
        'name'     => t('lp_plan_std'),
        'kod'      => 'standard',
        'desc'     => t('lp_plan_std_d'),
        'monthly'  => $prices['standard'],
        'featured' => true,
        'features' => [
            t('lp_pf_all_basic'),
            t('lp_pf_tables_25'),
            t('lp_pf_hist_60'),
            t('lp_pf_reports'),
            t('lp_pf_sst'),
            t('lp_pf_self_pickup'),
            t('lp_pf_staff_10'),
            t('lp_pf_support_wa'),
        ],
    ],
    [
        'name'     => t('lp_plan_pro'),
        'kod'      => 'pro',
        'desc'     => t('lp_plan_pro_d'),
        'monthly'  => $prices['pro'],
        'featured' => false,
        'features' => [
            t('lp_pf_all_std'),
            t('lp_pf_tables_inf'),
            t('lp_pf_hist_inf'),
            t('lp_pf_staff_inf'),
            t('lp_pf_gallery'),
            t('lp_pf_support_pri'),
        ],
    ],
];

$features = [
    ['qr', t('lp_f1_t'), t('lp_f1_d')],
    ['bell', t('lp_f2_t'), t('lp_f2_d')],
    ['cook', t('lp_f3_t'), t('lp_f3_d')],
    ['cam', t('lp_f4_t'), t('lp_f4_d')],
    ['chart', t('lp_f5_t'), t('lp_f5_d')],
    ['bill', t('lp_f6_t'), t('lp_f6_d')],
];

$steps = [
    [t('lp_s1_t'), t('lp_s1_d')],
    [t('lp_s2_t'), t('lp_s2_d')],
    [t('lp_s3_t'), t('lp_s3_d')],
    [t('lp_s4_t'), t('lp_s4_d')],
];

$faqs = [
    [t('lp_q1'), t('lp_a1')],
    [t('lp_q2'), t('lp_a2')],
    [t('lp_q3'), t('lp_a3')],
    [t('lp_q4'), t('lp_a4')],
    [t('lp_q5'), t('lp_a5')],
    [t('lp_q6'), t('lp_a6')],
];

$navLinks = [
    '#features' => t('lp_nav_features'),
    '#cases'    => t('lp_nav_cases'),
    '#how'      => t('lp_nav_how'),
    '#demo'     => t('lp_nav_demo'),
    '#pricing'  => t('lp_nav_pricing'),
    '#faq'      => t('lp_nav_faq'),
    blogUrl()   => t('lp_nav_blog'),
];

$loginUrl = baseUrl('admin/login.php');
$waBiz = 'https://wa.me/601125352270';
$waStartUrl = $waBiz . '?text=' . rawurlencode(t('lp_wa_start'));
$logo = assetUrl('img/brand/tabletap-icon-192.png');
$iconLarge = assetUrl('img/brand/tabletap-icon-512.png');
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#e85d04">
  <link rel="icon" href="<?= e($logo) ?>">
  <link rel="apple-touch-icon" href="<?= e($iconLarge) ?>">
  <?php seoHead([
      'title' => $config['app_name'] . ' — ' . t('lp_badge'),
      'description' => t('lp_hero_sub'),
      'path' => '',
      'image' => $iconLarge,
  ]); ?>
  <?php
    seoJsonLdOrganization($iconLarge);
    seoJsonLdWebSite();
    seoJsonLdSoftwareApp();
    seoJsonLdFaq($faqs);
  ?>
  <link rel="stylesheet" href="<?= e(assetUrl('css/landing.css')) ?>">
</head>
<body class="lp">

<header class="lp-nav">
  <div class="wrap lp-nav-inner">
    <a class="lp-logo" href="#top">
      <img src="<?= e($logo) ?>" alt="<?= e($config['app_name']) ?>">
      <span>Table<i>Tap</i></span>
    </a>

    <nav class="lp-links">
      <?php foreach ($navLinks as $href => $label): ?>
        <a href="<?= e($href) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="lp-nav-actions">
      <div class="lang-toggle">
        <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
        <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
      </div>
      <a class="btn btn-outline btn-sm" href="<?= e($loginUrl) ?>"><?= e(t('lp_login')) ?></a>
      <a class="btn btn-primary btn-sm" href="<?= e($waStartUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('lp_cta_start')) ?></a>
      <button type="button" class="lp-burger" id="lp-burger" aria-expanded="false" aria-label="<?= e(t('lp_menu')) ?>">
        <span></span>
      </button>
    </div>
  </div>
  <nav class="lp-mobile-menu" id="lp-mobile-menu">
    <?php foreach ($navLinks as $href => $label): ?>
      <a href="<?= e($href) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= e($loginUrl) ?>"><?= e(t('lp_login')) ?></a>
    <a class="lp-mobile-cta" href="<?= e($waStartUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('lp_cta_start')) ?></a>
  </nav>
</header>

<main id="top">

  <!-- ================= HERO ================= -->
  <section class="hero">
    <span class="blob blob-1"></span>
    <span class="blob blob-2"></span>
    <span class="blob blob-3"></span>

    <div class="wrap">
      <div class="hero-grid">
        <div>
          <span class="kicker">🇲🇾 <?= e(t('lp_badge')) ?></span>
          <h1>
            <?= e(t('lp_hero_title')) ?><br>
            <em><?= e(t('lp_hero_title_hl')) ?></em>
          </h1>
          <p class="hero-sub"><?= e(t('lp_hero_sub')) ?></p>

          <ul class="hero-points">
            <li><span class="tick">✓</span><?= e(t('lp_hero_p1')) ?></li>
            <li><span class="tick">✓</span><?= e(t('lp_hero_p2')) ?></li>
            <li><span class="tick">✓</span><?= e(t('lp_hero_p3')) ?></li>
          </ul>

          <div class="hero-cta">
            <a class="btn btn-primary btn-lg" href="<?= e($waStartUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('lp_cta_start')) ?> →</a>
            <a class="btn btn-outline btn-lg" href="#demo"><?= e(t('lp_cta_demo')) ?></a>
          </div>
          <p class="hero-note"><?= e(t('lp_hero_note')) ?></p>
        </div>

        <div class="phone-stage">
          <div class="chip chip-1">
            <span class="dot"></span>
            <span><?= e(t('lp_demo_new')) ?><small><?= e(t('table_n', '5')) ?></small></span>
          </div>

          <div class="phone" id="hero-phone" data-scene="scan" aria-hidden="true"
               data-cap-scan="<?= e(t('lp_hero_cap_scan')) ?>"
               data-cap-menu="<?= e(t('lp_hero_cap_menu')) ?>"
               data-cap-cart="<?= e(t('lp_hero_cap_cart')) ?>"
               data-cap-done="<?= e(t('lp_hero_cap_done')) ?>">
            <div class="phone-screen">
              <span class="phone-notch"></span>

              <div class="ps-scene" data-scene="scan">
                <div class="ps-scan">
                  <div class="ps-scan-frame">
                    <div class="ps-scan-qr"></div>
                  </div>
                  <p><?= e(t('table_n', '5')) ?></p>
                </div>
              </div>

              <div class="ps-scene" data-scene="menu">
                <div class="ps-head">
                  <div>
                    <div class="ps-brand">Kedai Demo</div>
                    <div class="ps-table"><?= e(t('table_n', '5')) ?></div>
                  </div>
                  <span class="ps-lang"><?= e($lang === 'en' ? 'EN' : 'BM') ?></span>
                </div>
                <div class="ps-tabs">
                  <span class="ps-tab on"><?= e(t('makanan')) ?></span>
                  <span class="ps-tab"><?= e(t('minuman')) ?></span>
                </div>
                <div class="ps-list">
                  <div class="ps-item" data-tap="0">
                    <span class="ps-thumb">N</span>
                    <span><span class="ps-name">Nasi Lemak</span><span class="ps-price">RM 8.00</span></span>
                    <span class="ps-add">+</span>
                  </div>
                  <div class="ps-item" data-tap="1">
                    <span class="ps-thumb">A</span>
                    <span><span class="ps-name"><?= e($lang === 'en' ? 'Fried Chicken' : 'Ayam Goreng') ?></span><span class="ps-price">RM 7.00</span></span>
                    <span class="ps-add">+</span>
                  </div>
                  <div class="ps-item" data-tap="2">
                    <span class="ps-thumb">T</span>
                    <span><span class="ps-name">Teh Tarik</span><span class="ps-price">RM 2.50</span></span>
                    <span class="ps-add">+</span>
                  </div>
                </div>
                <div class="ps-cart" id="hero-cart-bar">
                  <span><b id="hero-cart-n">0</b><?= e(t('view_cart')) ?></span>
                  <span id="hero-cart-rm">RM 0.00</span>
                </div>
              </div>

              <div class="ps-scene" data-scene="cart">
                <div class="ps-cart-ui">
                  <div class="ps-cart-h"><?= e(t('cart')) ?></div>
                  <div class="ps-cart-line"><span>2× Nasi Lemak</span><span>RM 16.00</span></div>
                  <div class="ps-cart-line"><span>1× Teh Tarik</span><span>RM 2.50</span></div>
                  <div class="ps-serve">
                    <span><?= e(t('dine_in')) ?></span>
                    <span class="on"><?= e(t('takeaway')) ?></span>
                  </div>
                  <div class="ps-send"><?= e(t('submit_order')) ?></div>
                </div>
              </div>

              <div class="ps-scene" data-scene="done">
                <div class="ps-done">
                  <div class="ps-done-ico">✓</div>
                  <strong><?= e(t('order_sent')) ?></strong>
                  <span>#1042 · <?= e(t('takeaway')) ?></span>
                </div>
              </div>
            </div>
          </div>
          <p class="phone-caption" id="hero-phone-caption"><?= e(t('lp_hero_cap_scan')) ?></p>

          <div class="chip chip-2">
            <span class="dot"></span>
            <span><?= e(t('takeaway')) ?><small><?= e(t('dapur_title')) ?></small></span>
          </div>
        </div>
      </div>

      <div class="stats reveal">
        <div class="stat"><b><?= e(t('lp_stat_setup')) ?></b><span><?= e(t('lp_stat_setup_l')) ?></span></div>
        <div class="stat"><b><?= e(t('lp_stat_dash')) ?></b><span><?= e(t('lp_stat_dash_l')) ?></span></div>
        <div class="stat"><b><?= e(t('lp_stat_lang')) ?></b><span><?= e(t('lp_stat_lang_l')) ?></span></div>
        <div class="stat"><b><?= e(t('lp_stat_price')) ?></b><span><?= e(t('lp_stat_price_l')) ?></span></div>
      </div>
    </div>
  </section>

  <!-- ================= MARQUEE ================= -->
  <div class="marquee" aria-hidden="true">
    <div class="marquee-track">
      <?php for ($i = 0; $i < 2; $i++): ?>
        <span>Nasi Lemak</span><span>Teh Tarik</span><span>Mee Goreng</span><span>Kopi O</span>
        <span>Roti Canai</span><span>Air Bandung</span><span>Nasi Goreng Kampung</span><span>Limau Ais</span>
        <span>Ayam Goreng</span><span>Cendol</span>
      <?php endfor; ?>
    </div>
  </div>

  <!-- ================= FEATURES ================= -->
  <section class="sec" id="features">
    <div class="wrap">
      <div class="sec-head center reveal">
        <span class="kicker"><?= e(t('lp_features_kicker')) ?></span>
        <h2><?= e(t('lp_features_title')) ?></h2>
        <p><?= e(t('lp_features_sub')) ?></p>
      </div>

      <div class="bento">
        <?php foreach ($features as [$kind, $title, $desc]): ?>
          <article class="card reveal" data-feat="<?= e($kind) ?>">
            <div class="feat-ico" aria-hidden="true">
              <?php if ($kind === 'qr'): ?>
                <span class="fi-qr"></span><span class="fi-laser"></span>
              <?php elseif ($kind === 'bell'): ?>
                <span class="fi-bell"></span><span class="fi-wave"></span>
              <?php elseif ($kind === 'cook'): ?>
                <span class="fi-lid"></span><span class="fi-pan"></span><span class="fi-flame"></span>
              <?php elseif ($kind === 'cam'): ?>
                <span class="fi-cam"></span><span class="fi-flash"></span>
              <?php elseif ($kind === 'chart'): ?>
                <span class="fi-bar b1"></span><span class="fi-bar b2"></span><span class="fi-bar b3"></span>
              <?php else: ?>
                <span class="fi-bill"></span><span class="fi-tick">✓</span>
              <?php endif; ?>
            </div>
            <h3><?= e($title) ?></h3>
            <p><?= e($desc) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ================= CASE: TABLE vs PICKUP ================= -->
  <section class="sec cases" id="cases">
    <div class="wrap">
      <div class="sec-head center reveal">
        <span class="kicker"><?= e(t('lp_case_kicker')) ?></span>
        <h2><?= e(t('lp_case_title')) ?></h2>
        <p><?= e(t('lp_case_sub')) ?></p>
      </div>

      <div class="case-hook reveal">
        <div class="hook-side chaos">
          <div class="hook-stage" aria-hidden="true">
            <span class="shout s1"><?= e(t('lp_case_shout1')) ?></span>
            <span class="shout s2"><?= e(t('lp_case_shout2')) ?></span>
            <span class="shout s3"><?= e(t('lp_case_shout3')) ?></span>
            <span class="hook-clock">?</span>
          </div>
          <span class="case-tag bad"><?= e(t('lp_case_pain')) ?></span>
          <h3><?= e(t('lp_case_pain_h')) ?></h3>
          <p><?= e(t('lp_case_pain_d')) ?></p>
        </div>
        <div class="hook-vs" aria-hidden="true"><span><?= e(t('lp_case_vs')) ?></span></div>
        <div class="hook-side calm">
          <div class="hook-stage" aria-hidden="true">
            <span class="hook-phone">
              <span class="hook-ping"></span>
              <span class="hook-ok">✓</span>
            </span>
          </div>
          <span class="case-tag good"><?= e(t('lp_case_fix')) ?></span>
          <h3><?= e(t('lp_case_fix_h')) ?></h3>
          <p><?= e(t('lp_case_fix_d')) ?></p>
        </div>
      </div>

      <div class="case-switch reveal" role="tablist" aria-label="<?= e(t('lp_nav_cases')) ?>">
        <button type="button" class="on" data-case="waiter" role="tab" aria-selected="true"><?= e(t('lp_case_waiter')) ?></button>
        <button type="button" data-case="pickup" role="tab" aria-selected="false"><?= e(t('lp_case_pickup')) ?></button>
      </div>

      <div class="case-play" id="case-play" data-mode="waiter">
        <article class="case-panel" data-panel="waiter">
          <p class="case-lead"><?= e(t('lp_case_waiter_d')) ?></p>
          <ol class="case-flow" id="waiter-flow">
            <li data-w-step="scan" class="is-current"><?= e(t('lp_case_w1')) ?></li>
            <li data-w-step="send"><?= e(t('lp_case_w2')) ?></li>
            <li data-w-step="cook"><?= e(t('lp_case_w3')) ?></li>
            <li data-w-step="serve"><?= e(t('lp_case_w4')) ?></li>
          </ol>
          <div class="case-scene waiter-scene" aria-hidden="true">
            <div class="lp-waiter-hero" id="lp-waiter-hero" data-stage="scan"
                 data-t-scan="<?= e(t('lp_wstage_scan')) ?>"
                 data-t-send="<?= e(t('lp_wstage_send')) ?>"
                 data-t-cook="<?= e(t('lp_wstage_cook')) ?>"
                 data-t-serve="<?= e(t('lp_wstage_serve')) ?>"
                 data-b-scan="<?= e(t('status_menunggu')) ?>"
                 data-b-send="<?= e(t('status_diproses')) ?>"
                 data-b-cook="<?= e(t('status_item_sedang')) ?>"
                 data-b-serve="<?= e(t('status_item_dihantar')) ?>">
              <div class="lp-wstage lp-w-scan">
                <span class="w-phone">
                  <span class="w-qr"></span>
                  <span class="w-laser"></span>
                </span>
              </div>
              <div class="lp-wstage lp-w-send">
                <span class="w-burst"></span>
                <span class="w-send">↑</span>
              </div>
              <div class="lp-wstage lp-w-cook">
                <span class="cook-lid"></span>
                <span class="cook-pan"></span>
                <span class="cook-flame"></span>
              </div>
              <div class="lp-wstage lp-w-serve">
                <span class="w-table"></span>
                <span class="w-tray"></span>
                <span class="w-person"></span>
              </div>
            </div>
            <div class="lp-track-copy">
              <p class="lp-track-title" id="lp-waiter-title"><?= e(t('lp_wstage_scan')) ?></p>
              <div class="w-dash-card" id="lp-waiter-card">
                <span class="badge amber" id="lp-waiter-badge"><?= e(t('status_menunggu')) ?></span>
                <strong><?= e(t('table_n', '5')) ?></strong>
                <small>2× Nasi Lemak</small>
              </div>
            </div>
          </div>
        </article>

        <article class="case-panel" data-panel="pickup">
          <p class="case-lead"><?= e(t('lp_case_pickup_d')) ?></p>
          <ol class="case-flow">
            <li><?= e(t('lp_case_p1')) ?></li>
            <li><?= e(t('lp_case_p2')) ?></li>
            <li><?= e(t('lp_case_p3')) ?></li>
            <li><?= e(t('lp_case_p4')) ?></li>
          </ol>
          <div class="case-scene pickup-scene" aria-hidden="true">
            <div class="lp-track-hero" id="lp-track-hero" data-stage="queue"
                 data-t-queue="<?= e(t('track_title_queue')) ?>"
                 data-t-cooking="<?= e(t('track_title_cooking')) ?>"
                 data-t-ready="<?= e(t('track_title_ready')) ?>"
                 data-t-done="<?= e(t('track_title_done')) ?>">
              <div class="lp-stage lp-queue">
                <span class="orb-ring"></span>
                <span class="orb-ticket">#</span>
              </div>
              <div class="lp-stage lp-cook">
                <span class="cook-lid"></span>
                <span class="cook-pan"></span>
                <span class="cook-flame"></span>
              </div>
              <div class="lp-stage lp-ready">
                <span class="ready-ping"></span>
                <span class="ready-bag">!</span>
              </div>
              <div class="lp-stage lp-done">
                <span class="done-check">✓</span>
              </div>
            </div>
            <div class="lp-track-copy">
              <p class="lp-track-title" id="lp-track-title"><?= e(t('track_title_queue')) ?></p>
              <ul class="lp-track-steps">
                <li data-lp-step="queue" class="is-current"><?= e(t('step_queue')) ?></li>
                <li data-lp-step="cooking"><?= e(t('step_cook')) ?></li>
                <li data-lp-step="ready"><?= e(t('step_ready')) ?></li>
                <li data-lp-step="done"><?= e(t('step_done')) ?></li>
              </ul>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- ================= HOW IT WORKS ================= -->
  <section class="sec" id="how" style="background:var(--surface);border-top:1px solid var(--line);border-bottom:1px solid var(--line)">
    <div class="wrap">
      <div class="sec-head reveal">
        <span class="kicker"><?= e(t('lp_how_kicker')) ?></span>
        <h2><?= e(t('lp_how_title')) ?></h2>
      </div>

      <div class="steps">
        <?php foreach ($steps as [$title, $desc]): ?>
          <article class="step">
            <h3><?= e($title) ?></h3>
            <p><?= e($desc) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ================= STAFF FLOW ================= -->
  <section class="staff-cinema" id="cinema">
    <div class="wrap">
      <div class="sec-head center">
        <span class="kicker"><?= e(t('lp_cinema_kicker')) ?></span>
        <h2><?= e(t('lp_cinema_title')) ?></h2>
      </div>
      <div class="tablet-frame" id="desk-cinema">
        <div class="tb-chrome">
          <span class="tb-dots" aria-hidden="true"></span>
          <span class="tb-title" id="cinema-title"><?= e(t('owner_title')) ?></span>
        </div>
        <div class="tb-body">
          <div class="tb-scene on" data-role="owner" data-title="<?= e(t('owner_title')) ?>">
            <div class="stat-row">
              <div class="stat-card">
                <div class="label"><?= e(t('income')) ?> (<?= e(t('filter_day')) ?>)</div>
                <div class="value">RM 186.00</div>
              </div>
              <div class="stat-card">
                <div class="label"><?= e(t('expenses')) ?> (<?= e(t('filter_day')) ?>)</div>
                <div class="value">RM 42.00</div>
              </div>
              <div class="stat-card">
                <div class="label"><?= e(t('profit')) ?></div>
                <div class="value">RM 144.00</div>
              </div>
              <div class="stat-card">
                <div class="label"><?= e(t('manage_menu')) ?> / <?= e(t('manage_tables')) ?></div>
                <div class="value" style="font-size:1.2rem">18 · 8</div>
              </div>
            </div>
            <div class="table-grid cinema-hub">
              <div class="order-card">
                <div class="table-num" style="font-size:1.25rem"><?= e(t('manage_menu')) ?></div>
                <p class="order-meta">CRUD · stok</p>
              </div>
              <div class="order-card">
                <div class="table-num" style="font-size:1.25rem"><?= e(t('manage_users')) ?></div>
                <p class="order-meta">cashier · dapur · waiter</p>
              </div>
            </div>
          </div>

          <div class="tb-scene" data-role="kasir" data-title="<?= e(t('lp_demo_cashier')) ?>">
            <article class="order-card unpaid">
              <div class="order-card-header">
                <div>
                  <div class="table-num"><?= e(t('table_n', '5')) ?></div>
                  <div class="order-meta">#1042 · 20:14</div>
                </div>
                <div class="order-flags">
                  <span class="serve-badge bungkus"><?= e(t('takeaway')) ?></span>
                  <span class="badge amber"><?= e(t('status_menunggu')) ?></span>
                  <span class="badge red"><?= e(t('lp_demo_unpaid')) ?></span>
                </div>
              </div>
              <ul class="order-items">
                <li><div><span class="qty">2×</span> Nasi Lemak</div><div>RM 16.00</div></li>
                <li><div><span class="qty">1×</span> Teh Tarik</div><div>RM 2.50</div></li>
              </ul>
              <div class="order-card-footer">
                <div class="order-total">RM 18.50</div>
                <span class="btn btn-success btn-sm"><?= e(t('lp_demo_paid')) ?></span>
              </div>
            </article>
          </div>

          <div class="tb-scene" data-role="dapur" data-title="<?= e(t('dapur_title')) ?>">
            <article class="kitchen-card menunggu takeaway">
              <div class="kitchen-table"><?= e(t('table_n', '5')) ?></div>
              <div class="serve-badge bungkus"><?= e(t('takeaway')) ?></div>
              <div class="kitchen-qty">×2</div>
              <h2 class="kitchen-item-name">Nasi Lemak</h2>
              <div class="order-meta">#1042 · 20:14</div>
              <div class="kitchen-actions">
                <span class="btn btn-secondary btn-sm"><?= e(t('mark_cooking')) ?></span>
                <span class="btn btn-success"><?= e(t('mark_ready')) ?></span>
              </div>
            </article>
          </div>

          <div class="tb-scene" data-role="waiter" data-title="<?= e(t('waiter_title')) ?>">
            <article class="kitchen-card siap takeaway">
              <div class="kitchen-table"><?= e(t('table_n', '5')) ?></div>
              <div class="serve-badge bungkus"><?= e(t('takeaway')) ?></div>
              <div class="kitchen-qty">×2</div>
              <h2 class="kitchen-item-name">Nasi Lemak</h2>
              <div class="order-meta"><?= e(t('dapur_title')) ?></div>
              <div class="order-meta">#1042 · 20:14</div>
              <div class="kitchen-actions">
                <span class="btn btn-primary"><?= e(t('mark_pickup')) ?></span>
              </div>
            </article>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= DEMO ================= -->
  <section class="sec" id="demo">
    <div class="wrap">
      <div class="sec-head center reveal">
        <span class="kicker"><?= e(t('lp_demo_kicker')) ?></span>
        <h2><?= e(t('lp_demo_title')) ?></h2>
        <p><?= e(t('lp_demo_sub')) ?></p>
      </div>

      <div class="demo-shell reveal" id="demo-proto">
        <div class="demo-tabs" role="tablist">
          <button type="button" class="demo-tab on" data-pane="pane-kasir" role="tab" aria-selected="true"><?= e(t('lp_demo_cashier')) ?></button>
          <button type="button" class="demo-tab" data-pane="pane-dapur" role="tab" aria-selected="false"><?= e(t('dapur_title')) ?></button>
          <button type="button" class="demo-tab" data-pane="pane-minuman" role="tab" aria-selected="false"><?= e(t('minuman_title')) ?></button>
          <button type="button" class="demo-tab" data-pane="pane-waiter" role="tab" aria-selected="false"><?= e(t('waiter_title')) ?></button>
        </div>

        <div class="demo-pane on table-grid" id="pane-kasir" role="tabpanel">
          <article class="order-card unpaid" data-demo-card="kasir-new">
            <div class="order-card-header">
              <div>
                <div class="table-num"><?= e(t('table_n', '5')) ?></div>
                <div class="order-meta">#1042 · 20:14</div>
              </div>
              <div class="order-flags">
                <span class="serve-badge bungkus"><?= e(t('takeaway')) ?></span>
                <span class="badge amber"><?= e(t('status_menunggu')) ?></span>
                <span class="badge red" data-unpaid><?= e(t('lp_demo_unpaid')) ?></span>
              </div>
            </div>
            <ul class="order-items">
              <li><div><span class="qty">2×</span> Nasi Lemak</div><div>RM 16.00</div></li>
              <li><div><span class="qty">1×</span> Teh Tarik</div><div>RM 2.50</div></li>
            </ul>
            <div class="order-card-footer">
              <div class="order-total">RM 18.50</div>
              <button type="button" class="btn btn-success btn-sm" data-demo-act="pay"><?= e(t('lp_demo_paid')) ?></button>
            </div>
          </article>
          <article class="order-card">
            <div class="order-card-header">
              <div>
                <div class="table-num"><?= e(t('table_n', '2')) ?></div>
                <div class="order-meta">#1041 · 20:06</div>
              </div>
              <div class="order-flags">
                <span class="serve-badge sini"><?= e(t('dine_in')) ?></span>
                <span class="badge green"><?= e(t('paid')) ?></span>
              </div>
            </div>
            <ul class="order-items">
              <li><div><span class="qty">1×</span> <?= e($lang === 'en' ? 'Kampung Fried Rice' : 'Nasi Goreng Kampung') ?></div><div>RM 9.50</div></li>
            </ul>
            <div class="order-card-footer">
              <div class="order-total">RM 9.50</div>
              <span class="badge green">✓ <?= e(t('paid')) ?></span>
            </div>
          </article>
        </div>

        <div class="demo-pane kitchen-grid" id="pane-dapur" role="tabpanel">
          <article class="kitchen-card menunggu takeaway" data-demo-card="dapur-new">
            <div class="kitchen-table"><?= e(t('table_n', '5')) ?></div>
            <div class="serve-badge bungkus"><?= e(t('takeaway')) ?></div>
            <div class="kitchen-qty">×2</div>
            <h2 class="kitchen-item-name">Nasi Lemak</h2>
            <div class="order-meta">#1042 · 20:14</div>
            <div class="kitchen-actions">
              <button type="button" class="btn btn-secondary btn-sm" data-demo-act="cook"><?= e(t('mark_cooking')) ?></button>
              <button type="button" class="btn btn-success" data-demo-act="ready"><?= e(t('mark_ready')) ?></button>
            </div>
          </article>
          <article class="kitchen-card sedang_dimasak dine-in" data-demo-card="dapur-old">
            <div class="kitchen-table"><?= e(t('table_n', '2')) ?></div>
            <div class="serve-badge sini"><?= e(t('dine_in')) ?></div>
            <div class="kitchen-qty">×1</div>
            <h2 class="kitchen-item-name"><?= e($lang === 'en' ? 'Kampung Fried Rice' : 'Nasi Goreng Kampung') ?></h2>
            <div class="order-meta">#1041 · 20:06</div>
            <div class="kitchen-actions">
              <button type="button" class="btn btn-success" data-demo-act="ready"><?= e(t('mark_ready')) ?></button>
            </div>
          </article>
        </div>

        <div class="demo-pane kitchen-grid" id="pane-minuman" role="tabpanel">
          <article class="kitchen-card menunggu takeaway" data-demo-card="minum-new">
            <div class="kitchen-table"><?= e(t('table_n', '5')) ?></div>
            <div class="serve-badge bungkus"><?= e(t('takeaway')) ?></div>
            <div class="kitchen-qty">×1</div>
            <h2 class="kitchen-item-name">Teh Tarik</h2>
            <div class="order-meta">#1042 · 20:14</div>
            <div class="kitchen-actions">
              <button type="button" class="btn btn-secondary btn-sm" data-demo-act="cook"><?= e(t('mark_cooking')) ?></button>
              <button type="button" class="btn btn-success" data-demo-act="ready"><?= e(t('mark_ready')) ?></button>
            </div>
          </article>
        </div>

        <div class="demo-pane kitchen-grid" id="pane-waiter" role="tabpanel">
          <article class="kitchen-card siap takeaway" data-demo-card="wait-new">
            <div class="kitchen-table"><?= e(t('table_n', '5')) ?></div>
            <div class="serve-badge bungkus"><?= e(t('takeaway')) ?></div>
            <div class="kitchen-qty">×2</div>
            <h2 class="kitchen-item-name">Nasi Lemak</h2>
            <div class="order-meta"><?= e(t('dapur_title')) ?></div>
            <div class="order-meta">#1042 · 20:14</div>
            <div class="kitchen-actions">
              <button type="button" class="btn btn-primary" data-demo-act="pickup"><?= e(t('mark_pickup')) ?></button>
            </div>
          </article>
        </div>

        <p class="demo-hint"><span class="pulse"></span><?= e(t('lp_demo_hint')) ?></p>
      </div>
    </div>
  </section>

  <!-- ================= PRICING ================= -->
  <section class="sec" id="pricing" style="background:var(--surface);border-top:1px solid var(--line);border-bottom:1px solid var(--line)">
    <div class="wrap">
      <div class="sec-head center reveal">
        <span class="kicker"><?= e(t('lp_pricing_kicker')) ?></span>
        <h2><?= e(t('lp_pricing_title')) ?></h2>
        <p><?= e(t('lp_pricing_sub')) ?></p>
      </div>

      <div style="text-align:center">
        <div class="billing">
          <button type="button" class="on" data-billing="monthly" aria-pressed="true"><?= e(t('lp_billing_monthly')) ?></button>
          <button type="button" data-billing="yearly" aria-pressed="false"><?= e(t('lp_billing_yearly')) ?></button>
        </div>
        <span class="save-pill">🎉 <?= e(t('lp_billing_save')) ?></span>
      </div>

      <div class="plans">
        <?php foreach ($plans as $plan):
          $monthly = (float) $plan['monthly'];
          $yearly = $monthly * 12 * (1 - $yearlyDiscount);
          $yearlyMonthly = $yearly / 12;
        ?>
          <article class="plan reveal<?= $plan['featured'] ? ' featured' : '' ?>">
            <?php if ($plan['featured']): ?>
              <span class="plan-tag"><?= e(t('lp_popular')) ?></span>
            <?php endif; ?>

            <div>
              <h3><?= e($plan['name']) ?></h3>
              <p class="desc"><?= e($plan['desc']) ?></p>
            </div>

            <div class="price">
              <div class="price-row">
                <span class="cur"><?= e($config['currency'] ?? 'RM') ?></span>
                <span class="amt"
                      data-monthly="<?= e((string) round($monthly)) ?>"
                      data-yearly="<?= e((string) round($yearlyMonthly)) ?>"><?= e(number_format(round($monthly), 0)) ?></span>
                <span class="per"><?= e(t('lp_per_month')) ?></span>
              </div>
              <p class="price-year" hidden>
                <?= e(t('lp_billed_year', ($config['currency'] ?? 'RM') . ' ' . number_format(round($yearly), 0))) ?>
              </p>
            </div>

            <ul>
              <?php foreach ($plan['features'] as $feat): ?>
                <li><span class="tick">✓</span><span><?= e($feat) ?></span></li>
              <?php endforeach; ?>
            </ul>

            <a class="btn <?= $plan['featured'] ? 'btn-primary' : 'btn-outline' ?>" href="<?= e($waBiz . '?text=' . rawurlencode(t('lp_wa_plan', $plan['name'], (string) round($monthly)))) ?>" target="_blank" rel="noopener noreferrer">
              <?= e(t('lp_choose')) ?>
            </a>
          </article>
        <?php endforeach; ?>
      </div>

      <p class="pricing-note"><?= e(t('lp_pricing_note')) ?></p>
    </div>
  </section>

  <?php $blogPreview = array_slice(blogAllPosts(), 0, 3); ?>
  <?php if ($blogPreview !== []): ?>
  <!-- ================= BLOG ================= -->
  <section class="sec" id="blog">
    <div class="wrap">
      <div class="sec-head center reveal">
        <span class="kicker"><?= e(t('lp_nav_blog')) ?></span>
        <h2><?= e(t('blog_section_title')) ?></h2>
        <p><?= e(t('blog_section_sub')) ?></p>
      </div>
      <div class="lp-blog-grid">
        <?php foreach ($blogPreview as $post): ?>
          <?php
            $slug = (string) ($post['slug'] ?? '');
            $postTitle = blogField($post, 'title', $lang);
            $excerpt = blogField($post, 'excerpt', $lang);
          ?>
          <article class="lp-blog-card reveal">
            <h3><a href="<?= e(blogUrl($slug)) ?>"><?= e($postTitle) ?></a></h3>
            <p><?= e($excerpt) ?></p>
            <a class="lp-blog-link" href="<?= e(blogUrl($slug)) ?>"><?= e(t('blog_read_more')) ?> →</a>
          </article>
        <?php endforeach; ?>
      </div>
      <p class="center" style="margin-top:28px">
        <a class="btn btn-outline" href="<?= e(blogUrl()) ?>"><?= e(t('blog_view_all')) ?></a>
      </p>
    </div>
  </section>
  <?php endif; ?>

  <!-- ================= FAQ ================= -->
  <section class="sec" id="faq">
    <div class="wrap">
      <div class="sec-head center reveal">
        <span class="kicker"><?= e(t('lp_nav_faq')) ?></span>
        <h2><?= e(t('lp_faq_title')) ?></h2>
      </div>

      <div class="faq">
        <?php foreach ($faqs as $i => [$q, $a]): ?>
          <div class="qa reveal">
            <button type="button" class="qa-q" aria-expanded="false" aria-controls="qa-<?= $i ?>">
              <span><?= e($q) ?></span><i>+</i>
            </button>
            <div class="qa-a" id="qa-<?= $i ?>">
              <p><?= e($a) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ================= CTA ================= -->
  <section class="sec" id="contact" style="padding-top:0">
    <div class="wrap">
      <div class="cta-band reveal">
        <h2><?= e(t('lp_cta_title')) ?></h2>
        <p><?= e(t('lp_cta_sub')) ?></p>
        <div class="cta-actions">
          <a class="btn btn-primary btn-lg" href="<?= e($waStartUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('lp_cta_start')) ?> →</a>
          <a class="btn btn-white btn-lg" href="#demo"><?= e(t('lp_cta_demo')) ?></a>
        </div>
      </div>
    </div>
  </section>
</main>

<footer class="lp-footer">
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-about">
        <a class="lp-logo" href="#top">
          <img src="<?= e($logo) ?>" alt="<?= e($config['app_name']) ?>">
          <span>Table<i>Tap</i></span>
        </a>
        <p><?= e(t('lp_footer_tagline')) ?></p>
      </div>

      <div>
        <h4><?= e(t('lp_footer_product')) ?></h4>
        <ul>
          <li><a href="#features"><?= e(t('lp_nav_features')) ?></a></li>
          <li><a href="#cases"><?= e(t('lp_nav_cases')) ?></a></li>
          <li><a href="#how"><?= e(t('lp_nav_how')) ?></a></li>
          <li><a href="#demo"><?= e(t('lp_nav_demo')) ?></a></li>
          <li><a href="#pricing"><?= e(t('lp_nav_pricing')) ?></a></li>
          <li><a href="<?= e(blogUrl()) ?>"><?= e(t('lp_nav_blog')) ?></a></li>
        </ul>
      </div>

      <div>
        <h4><?= e(t('lp_footer_company')) ?></h4>
        <ul>
          <li><a href="#faq"><?= e(t('lp_nav_faq')) ?></a></li>
          <li><a href="<?= e(blogUrl()) ?>"><?= e(t('lp_nav_blog')) ?></a></li>
          <li><a href="#pricing"><?= e(t('lp_pricing_kicker')) ?></a></li>
          <li><a href="<?= e($waStartUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('lp_cta_start')) ?></a></li>
        </ul>
      </div>

      <div>
        <h4><?= e(t('lp_footer_staff')) ?></h4>
        <ul>
          <li><a href="<?= e($loginUrl) ?>"><?= e(t('lp_demo_cashier')) ?></a></li>
          <li><a href="<?= e($loginUrl) ?>"><?= e(t('dapur_title')) ?></a></li>
          <li><a href="<?= e($loginUrl) ?>"><?= e(t('minuman_title')) ?></a></li>
          <li><a href="<?= e($loginUrl) ?>"><?= e(t('owner_title')) ?></a></li>
        </ul>
      </div>
    </div>

    <div class="foot-bottom">
      <span>© <span id="lp-year">2026</span> <?= e($config['app_name']) ?>. <?= e(t('lp_rights')) ?></span>
      <span class="foot-credit">
        <?= e(t('lp_developed_by')) ?>
        <a href="https://dev-khalfikri.pantheonsite.io/" target="_blank" rel="noopener noreferrer">KalFikri</a>
        ·
        <a href="https://www.linkedin.com/in/muhamad-fikri-haikal-fullstack-web-developer/" target="_blank" rel="noopener noreferrer">LinkedIn</a>
        ·
        <a href="mailto:muhamadfikrih29@gmail.com">muhamadfikrih29@gmail.com</a>
      </span>
    </div>
  </div>
</footer>

<script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
<script src="<?= e(assetUrl('js/sound.js')) ?>"></script>
<script src="<?= e(assetUrl('js/landing.js')) ?>"></script>
<?= chatlmWidgetHtml() ?>
</body>
</html>
