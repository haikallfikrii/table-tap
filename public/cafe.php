<?php
/**
 * Cafe entry — redirects to menu browse (verify at checkout).
 * GET: shop=slug&token=shop_token
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';

$slug = trim((string) ($_GET['shop'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

if ($slug !== '' && $token !== '' && findShopByAccess($slug, $token)) {
    redirect(cafeBrowseOrderUrl($slug, $token));
}

require_once dirname(__DIR__) . '/includes/i18n.php';
$lang = currentLang();
$config = getConfig();
?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#e85d04">
  <title><?= e($config['app_name']) ?></title>
  <link rel="stylesheet" href="<?= e(assetUrl('css/app.css')) ?>">
</head>
<body>
  <div class="error-page">
    <h1><?= e($config['app_name']) ?></h1>
    <p><?= e(t('cafe_invalid_link')) ?></p>
  </div>
</body>
</html>
