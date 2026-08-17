<?php
/**
 * Shared header/footer for public pages (blog, etc.)
 */

declare(strict_types=1);

require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/blog.php';

/** @param array{title:string,description:string,path?:string,type?:string,extra_css?:list<string>,extra_js?:list<string>,json_ld?:callable|null,body_class?:string} $opts */
function publicLayoutStart(array $opts): void
{
    $GLOBALS['_public_layout_opts'] = $opts;
    $lang = currentLang();
    $config = getConfig();
    $logo = assetUrl('img/brand/tabletap-icon-192.png');
    $loginUrl = baseUrl('admin/login.php');
    $homeUrl = baseUrl('');
    $blogUrl = blogUrl();
    $waBiz = 'https://wa.me/601125352270';
    $waStartUrl = $waBiz . '?text=' . rawurlencode(t('lp_wa_start'));
    ?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#e85d04">
  <link rel="icon" href="<?= e($logo) ?>">
  <link rel="apple-touch-icon" href="<?= e(assetUrl('img/brand/tabletap-icon-512.png')) ?>">
  <?php seoHead([
      'title' => $opts['title'],
      'description' => $opts['description'],
      'path' => $opts['path'] ?? '',
      'type' => $opts['type'] ?? 'website',
      'noindex' => !empty($opts['noindex']),
  ]); ?>
  <link rel="stylesheet" href="<?= e(assetUrl('css/landing.css')) ?>">
  <?php foreach ($opts['extra_css'] ?? [] as $css): ?>
  <link rel="stylesheet" href="<?= e(assetUrl($css)) ?>">
  <?php endforeach; ?>
  <?php
    if (!empty($opts['json_ld']) && is_callable($opts['json_ld'])) {
        ($opts['json_ld'])();
    }
    ?>
</head>
<body class="lp blog-page<?= !empty($opts['body_class']) ? ' ' . e($opts['body_class']) : '' ?>">
<div class="blog-read-progress" id="blog-read-progress" aria-hidden="true"></div>
<header class="lp-nav">
  <div class="wrap lp-nav-inner">
    <a class="lp-logo" href="<?= e($homeUrl) ?>">
      <img src="<?= e($logo) ?>" alt="<?= e($config['app_name']) ?>">
      <span>Table<i>Tap</i></span>
    </a>
    <nav class="lp-links">
      <a href="<?= e($homeUrl) ?>#features"><?= e(t('lp_nav_features')) ?></a>
      <a href="<?= e($blogUrl) ?>"><?= e(t('lp_nav_blog')) ?></a>
      <a href="<?= e($homeUrl) ?>#pricing"><?= e(t('lp_nav_pricing')) ?></a>
      <a href="<?= e($homeUrl) ?>#faq"><?= e(t('lp_nav_faq')) ?></a>
    </nav>
    <div class="lp-nav-actions">
      <div class="lang-toggle">
        <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
        <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
      </div>
      <a class="btn btn-outline btn-sm" href="<?= e($loginUrl) ?>"><?= e(t('lp_login')) ?></a>
      <a class="btn btn-primary btn-sm" href="<?= e($waStartUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('lp_cta_start')) ?></a>
    </div>
  </div>
</header>
<main class="blog-main">
    <?php
}

function publicLayoutEnd(): void
{
    $opts = $GLOBALS['_public_layout_opts'] ?? [];
    $config = getConfig();
    $homeUrl = baseUrl('');
    $blogUrl = blogUrl();
    $loginUrl = baseUrl('admin/login.php');
    $waBiz = 'https://wa.me/601125352270';
    $waStartUrl = $waBiz . '?text=' . rawurlencode(t('lp_wa_start'));
    ?>
</main>
<footer class="lp-footer">
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-about">
        <a class="lp-logo" href="<?= e($homeUrl) ?>">
          <img src="<?= e(assetUrl('img/brand/tabletap-icon-192.png')) ?>" alt="<?= e($config['app_name']) ?>">
          <span>Table<i>Tap</i></span>
        </a>
        <p><?= e(t('lp_footer_tagline')) ?></p>
      </div>
      <div>
        <h4><?= e(t('lp_footer_product')) ?></h4>
        <ul>
          <li><a href="<?= e($homeUrl) ?>#features"><?= e(t('lp_nav_features')) ?></a></li>
          <li><a href="<?= e($blogUrl) ?>"><?= e(t('lp_nav_blog')) ?></a></li>
          <li><a href="<?= e($homeUrl) ?>#pricing"><?= e(t('lp_nav_pricing')) ?></a></li>
        </ul>
      </div>
      <div>
        <h4><?= e(t('lp_footer_company')) ?></h4>
        <ul>
          <li><a href="<?= e($homeUrl) ?>#faq"><?= e(t('lp_nav_faq')) ?></a></li>
          <li><a href="<?= e($waStartUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('lp_cta_start')) ?></a></li>
        </ul>
      </div>
      <div>
        <h4><?= e(t('lp_footer_staff')) ?></h4>
        <ul>
          <li><a href="<?= e($loginUrl) ?>"><?= e(t('lp_login')) ?></a></li>
        </ul>
      </div>
    </div>
    <div class="foot-bottom">
      <span>© <?= date('Y') ?> <?= e($config['app_name']) ?>. <?= e(t('lp_rights')) ?></span>
    </div>
  </div>
</footer>
<script src="<?= e(assetUrl('js/i18n.js')) ?>"></script>
<script src="<?= e(assetUrl('js/landing.js')) ?>"></script>
<?php foreach ($opts['extra_js'] ?? [] as $js): ?>
<script src="<?= e(assetUrl($js)) ?>"></script>
<?php endforeach; ?>
<?= chatlmWidgetHtml() ?>
</body>
</html>
    <?php
}
