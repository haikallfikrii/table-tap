<?php
/**
 * TableTap — public landing page.
 * Plan prices are read from the packages table when available so marketing
 * copy never drifts from what the master panel actually sells.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/i18n.php';

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

$yearlyMultiplier = 10; // pay for 10 months, get 12

$plans = [
    [
        'name'     => t('lp_plan_basic'),
        'desc'     => t('lp_plan_basic_d'),
        'monthly'  => $prices['basic'],
        'featured' => false,
        'features' => [
            t('lp_pf_tables_10'),
            t('lp_pf_menu'),
            t('lp_pf_dash'),
            t('lp_pf_hist_30'),
            t('lp_pf_staff_3'),
            t('lp_pf_support_mail'),
        ],
    ],
    [
        'name'     => t('lp_plan_std'),
        'desc'     => t('lp_plan_std_d'),
        'monthly'  => $prices['standard'],
        'featured' => true,
        'features' => [
            t('lp_pf_all_basic'),
            t('lp_pf_tables_25'),
            t('lp_pf_hist_60'),
            t('lp_pf_reports'),
            t('lp_pf_sst'),
            t('lp_pf_staff_10'),
            t('lp_pf_support_wa'),
        ],
    ],
    [
        'name'     => t('lp_plan_pro'),
        'desc'     => t('lp_plan_pro_d'),
        'monthly'  => $prices['pro'],
        'featured' => false,
        'features' => [
            t('lp_pf_all_std'),
            t('lp_pf_tables_inf'),
            t('lp_pf_hist_inf'),
            t('lp_pf_staff_inf'),
            t('lp_pf_support_pri'),
        ],
    ],
];

$features = [
    ['🍽️', t('lp_f1_t'), t('lp_f1_d')],
    ['🔔', t('lp_f2_t'), t('lp_f2_d')],
    ['🍳', t('lp_f3_t'), t('lp_f3_d')],
    ['📸', t('lp_f4_t'), t('lp_f4_d')],
    ['📊', t('lp_f5_t'), t('lp_f5_d')],
    ['🧾', t('lp_f6_t'), t('lp_f6_d')],
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
    '#how'      => t('lp_nav_how'),
    '#demo'     => t('lp_nav_demo'),
    '#pricing'  => t('lp_nav_pricing'),
    '#faq'      => t('lp_nav_faq'),
];

$loginUrl = baseUrl('admin/login.php');
$logo = assetUrl('img/brand/tabletap-icon-192.png');
$iconLarge = assetUrl('img/brand/tabletap-icon-512.png');
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#e85d04">
  <title><?= e($config['app_name']) ?> — <?= e(t('lp_badge')) ?></title>
  <meta name="description" content="<?= e(t('lp_hero_sub')) ?>">
  <link rel="icon" href="<?= e($logo) ?>">
  <link rel="apple-touch-icon" href="<?= e($iconLarge) ?>">
  <meta property="og:title" content="<?= e($config['app_name']) ?> — <?= e(t('lp_badge')) ?>">
  <meta property="og:description" content="<?= e(t('lp_hero_sub')) ?>">
  <meta property="og:image" content="<?= e($iconLarge) ?>">
  <meta property="og:type" content="website">
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
      <a class="btn btn-primary btn-sm" href="#pricing"><?= e(t('lp_cta_start')) ?></a>
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
    <a class="lp-mobile-cta" href="#pricing"><?= e(t('lp_cta_start')) ?></a>
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
            <a class="btn btn-primary btn-lg" href="#pricing"><?= e(t('lp_cta_start')) ?> →</a>
            <a class="btn btn-outline btn-lg" href="#demo"><?= e(t('lp_cta_demo')) ?></a>
          </div>
          <p class="hero-note"><?= e(t('lp_hero_note')) ?></p>
        </div>

        <div class="phone-stage">
          <div class="chip chip-1">
            <span class="dot"></span>
            <span><?= e(t('lp_demo_new')) ?><small><?= e(t('table_n', '5')) ?></small></span>
          </div>

          <div class="phone" aria-hidden="true">
            <div class="phone-screen">
              <span class="phone-notch"></span>
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
                <div class="ps-item">
                  <span class="ps-thumb">N</span>
                  <span>
                    <span class="ps-name">Nasi Lemak</span>
                    <span class="ps-price">RM 8.00</span>
                  </span>
                  <span class="ps-add">+</span>
                </div>
                <div class="ps-item">
                  <span class="ps-thumb">A</span>
                  <span>
                    <span class="ps-name"><?= e($lang === 'en' ? 'Fried Chicken' : 'Ayam Goreng') ?></span>
                    <span class="ps-price">RM 7.00</span>
                  </span>
                  <span class="ps-add">+</span>
                </div>
                <div class="ps-item">
                  <span class="ps-thumb">M</span>
                  <span>
                    <span class="ps-name"><?= e($lang === 'en' ? 'Fried Noodles' : 'Mee Goreng') ?></span>
                    <span class="ps-price">RM 8.50</span>
                  </span>
                  <span class="ps-add">+</span>
                </div>
                <div class="ps-item">
                  <span class="ps-thumb">T</span>
                  <span>
                    <span class="ps-name">Teh Tarik</span>
                    <span class="ps-price">RM 2.50</span>
                  </span>
                  <span class="ps-add">+</span>
                </div>
              </div>
              <div class="ps-cart">
                <span><b>3</b><?= e(t('view_cart')) ?></span>
                <span>RM 18.50</span>
              </div>
            </div>
          </div>

          <div class="chip chip-2">
            <span class="dot"></span>
            <span><?= e(t('status_item_selesai')) ?><small><?= e(t('dapur_title')) ?></small></span>
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
        <?php foreach ($features as [$icon, $title, $desc]): ?>
          <article class="card reveal">
            <div class="ico"><?= $icon ?></div>
            <h3><?= e($title) ?></h3>
            <p><?= e($desc) ?></p>
          </article>
        <?php endforeach; ?>
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

  <!-- ================= DEMO ================= -->
  <section class="sec" id="demo">
    <div class="wrap">
      <div class="sec-head center reveal">
        <span class="kicker"><?= e(t('lp_demo_kicker')) ?></span>
        <h2><?= e(t('lp_demo_title')) ?></h2>
        <p><?= e(t('lp_demo_sub')) ?></p>
      </div>

      <div class="demo-shell reveal">
        <div class="demo-tabs" role="tablist">
          <button type="button" class="demo-tab on" data-pane="pane-kasir" role="tab" aria-selected="true"><?= e(t('kasir_title')) ?></button>
          <button type="button" class="demo-tab" data-pane="pane-dapur" role="tab" aria-selected="false"><?= e(t('dapur_title')) ?></button>
          <button type="button" class="demo-tab" data-pane="pane-minuman" role="tab" aria-selected="false"><?= e(t('minuman_title')) ?></button>
        </div>

        <!-- Kasir -->
        <div class="demo-pane on" id="pane-kasir" role="tabpanel">
          <article class="o-card unpaid">
            <div class="o-top">
              <div>
                <div class="o-table"><?= e(t('table_n', '5')) ?></div>
                <div class="o-meta">#1042 · 20:14</div>
              </div>
              <div style="display:grid;gap:6px;justify-items:end">
                <span class="badge amber"><?= e(t('status_menunggu')) ?></span>
                <span class="badge red"><?= e(t('lp_demo_unpaid')) ?></span>
              </div>
            </div>
            <ul class="o-lines">
              <li><span><span class="q">2×</span> Nasi Lemak<span class="o-note"><?= e(t('lp_demo_note')) ?></span></span><span>RM 16.00</span></li>
              <li><span><span class="q">1×</span> Teh Tarik</span><span>RM 2.50</span></li>
            </ul>
            <div class="o-foot">
              <span class="o-total">RM 18.50</span>
              <span class="btn btn-primary btn-sm"><?= e(t('mark_paid')) ?></span>
            </div>
          </article>

          <article class="o-card">
            <div class="o-top">
              <div>
                <div class="o-table"><?= e(t('table_n', '2')) ?></div>
                <div class="o-meta">#1041 · 20:06</div>
              </div>
              <div style="display:grid;gap:6px;justify-items:end">
                <span class="badge blue"><?= e(t('status_diproses')) ?></span>
                <span class="badge green"><?= e(t('status_lunas')) ?></span>
              </div>
            </div>
            <ul class="o-lines">
              <li><span><span class="q">1×</span> <?= e($lang === 'en' ? 'Kampung Fried Rice' : 'Nasi Goreng Kampung') ?></span><span>RM 9.50</span></li>
              <li><span><span class="q">2×</span> Kopi O</span><span>RM 4.00</span></li>
            </ul>
            <div class="o-foot">
              <span class="o-total">RM 13.50</span>
              <span class="badge green">✓ <?= e(t('paid')) ?></span>
            </div>
          </article>
        </div>

        <!-- Dapur -->
        <div class="demo-pane" id="pane-dapur" role="tabpanel">
          <article class="o-card warn">
            <div class="o-table"><?= e(t('table_n', '5')) ?></div>
            <div class="o-meta" style="margin-bottom:8px">#1042 · <?= e(t('lp_demo_new')) ?></div>
            <h3 style="font-size:1.3rem;margin-bottom:4px">Nasi Lemak <span class="q" style="color:var(--brand)">×2</span></h3>
            <p class="o-note" style="margin:0 0 12px"><?= e(t('lp_demo_note')) ?></p>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <span class="btn btn-outline btn-sm"><?= e(t('mark_cooking')) ?></span>
              <span class="btn btn-primary btn-sm"><?= e(t('mark_done')) ?></span>
            </div>
          </article>

          <article class="o-card info">
            <div class="o-table"><?= e(t('table_n', '2')) ?></div>
            <div class="o-meta" style="margin-bottom:8px">#1041 · <?= e(t('lp_demo_cook')) ?></div>
            <h3 style="font-size:1.3rem;margin-bottom:12px">
              <?= e($lang === 'en' ? 'Kampung Fried Rice' : 'Nasi Goreng Kampung') ?>
              <span class="q" style="color:var(--brand)">×1</span>
            </h3>
            <span class="btn btn-primary btn-sm" style="width:100%"><?= e(t('mark_done')) ?></span>
          </article>
        </div>

        <!-- Minuman -->
        <div class="demo-pane" id="pane-minuman" role="tabpanel">
          <article class="o-card warn">
            <div class="o-table"><?= e(t('table_n', '5')) ?></div>
            <div class="o-meta" style="margin-bottom:8px">#1042 · <?= e(t('lp_demo_new')) ?></div>
            <h3 style="font-size:1.3rem;margin-bottom:12px">Teh Tarik <span class="q" style="color:var(--brand)">×1</span></h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <span class="btn btn-outline btn-sm"><?= e(t('mark_cooking')) ?></span>
              <span class="btn btn-primary btn-sm"><?= e(t('mark_done')) ?></span>
            </div>
          </article>

          <article class="o-card info">
            <div class="o-table"><?= e(t('table_n', '2')) ?></div>
            <div class="o-meta" style="margin-bottom:8px">#1041 · <?= e(t('lp_demo_cook')) ?></div>
            <h3 style="font-size:1.3rem;margin-bottom:12px">Kopi O <span class="q" style="color:var(--brand)">×2</span></h3>
            <span class="btn btn-primary btn-sm" style="width:100%"><?= e(t('mark_done')) ?></span>
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
          $yearly = $monthly * $yearlyMultiplier;
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
              <span class="cur"><?= e($config['currency'] ?? 'RM') ?></span>
              <span class="amt"
                    data-monthly="<?= e((string) round($monthly)) ?>"
                    data-yearly="<?= e((string) round($yearly)) ?>"><?= e(number_format(round($monthly), 0)) ?></span>
              <span class="per"
                    data-per
                    data-per-monthly="<?= e(t('lp_per_month')) ?>"
                    data-per-yearly="<?= e(t('lp_per_year')) ?>"><?= e(t('lp_per_month')) ?></span>
            </div>

            <ul>
              <?php foreach ($plan['features'] as $feat): ?>
                <li><span class="tick">✓</span><span><?= e($feat) ?></span></li>
              <?php endforeach; ?>
            </ul>

            <a class="btn <?= $plan['featured'] ? 'btn-primary' : 'btn-outline' ?>" href="#contact">
              <?= e(t('lp_choose')) ?>
            </a>
          </article>
        <?php endforeach; ?>
      </div>

      <p class="pricing-note"><?= e(t('lp_pricing_note')) ?></p>
    </div>
  </section>

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
          <a class="btn btn-primary btn-lg" href="<?= e($loginUrl) ?>"><?= e(t('lp_cta_start')) ?> →</a>
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
          <li><a href="#how"><?= e(t('lp_nav_how')) ?></a></li>
          <li><a href="#demo"><?= e(t('lp_nav_demo')) ?></a></li>
          <li><a href="#pricing"><?= e(t('lp_nav_pricing')) ?></a></li>
        </ul>
      </div>

      <div>
        <h4><?= e(t('lp_footer_company')) ?></h4>
        <ul>
          <li><a href="#faq"><?= e(t('lp_nav_faq')) ?></a></li>
          <li><a href="#pricing"><?= e(t('lp_pricing_kicker')) ?></a></li>
          <li><a href="#contact"><?= e(t('lp_cta_start')) ?></a></li>
        </ul>
      </div>

      <div>
        <h4><?= e(t('lp_footer_staff')) ?></h4>
        <ul>
          <li><a href="<?= e($loginUrl) ?>"><?= e(t('kasir_title')) ?></a></li>
          <li><a href="<?= e($loginUrl) ?>"><?= e(t('dapur_title')) ?></a></li>
          <li><a href="<?= e($loginUrl) ?>"><?= e(t('minuman_title')) ?></a></li>
          <li><a href="<?= e($loginUrl) ?>"><?= e(t('owner_title')) ?></a></li>
        </ul>
      </div>
    </div>

    <div class="foot-bottom">
      <span>© <span id="lp-year">2026</span> <?= e($config['app_name']) ?>. <?= e(t('lp_rights')) ?></span>
      <span><?= e(t('lp_stat_lang_l')) ?> · Malaysia 🇲🇾</span>
    </div>
  </div>
</footer>

<script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
<script src="<?= e(assetUrl('js/landing.js')) ?>"></script>
</body>
</html>
