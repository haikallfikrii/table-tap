<?php
/**
 * Shared admin page header fragment.
 * Expects: $pageTitle, $user, $lang, $config
 * Optional: $showSound = true
 */

declare(strict_types=1);

if (!isset($pageTitle, $user, $lang, $config)) {
    return;
}
$showSound = $showSound ?? false;
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> — <?= e($config['app_name']) ?></title>
  <?php
  require_once __DIR__ . '/seo.php';
  seoFaviconLinks();
  ?>
  <link rel="stylesheet" href="<?= e(assetUrl('css/admin.css')) ?>">
</head>
<body class="admin">
<div class="admin-shell">
  <header class="admin-topbar">
    <div>
      <h1><?= e($pageTitle) ?></h1>
      <div class="meta">
        <?php if (!empty($user['shop_name'])): ?>
          <?= e($user['shop_name']) ?> ·
        <?php endif; ?>
        <?= e($user['nama'] ?: $user['username']) ?> · <?= e($user['role']) ?>
      </div>
    </div>
    <div class="admin-actions">
      <?php if ($showSound): ?>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-enable-sound"><?= e(t('enable_sound')) ?></button>
      <?php endif; ?>
      <div class="lang-toggle">
        <button type="button" data-set-lang="my" class="<?= $lang === 'my' ? 'active' : '' ?>"><?= e(t('lang_my')) ?></button>
        <button type="button" data-set-lang="en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= e(t('lang_en')) ?></button>
      </div>
      <a class="btn btn-ghost btn-sm" href="<?= e(baseUrl('admin/logout.php')) ?>"><?= e(t('logout')) ?></a>
    </div>
  </header>
