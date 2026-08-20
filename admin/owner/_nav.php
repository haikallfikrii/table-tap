<?php
/** Owner sub-nav. Expects $nav active key. */
$links = [
    'home'     => ['url' => baseUrl('admin/owner/index.php'), 'label' => t('owner_title')],
    'menu'     => ['url' => baseUrl('admin/owner/menu.php'), 'label' => t('manage_menu')],
    'stations' => ['url' => baseUrl('admin/owner/stations.php'), 'label' => t('manage_stations')],
    'tables'   => ['url' => baseUrl('admin/owner/tables.php'), 'label' => t('manage_tables')],
    'reports'  => ['url' => baseUrl('admin/owner/reports.php'), 'label' => t('reports')],
    'history'  => ['url' => baseUrl('admin/owner/history.php'), 'label' => t('order_history')],
    'settings' => ['url' => baseUrl('admin/owner/settings.php'), 'label' => t('shop_settings')],
    'users'    => ['url' => baseUrl('admin/owner/users.php'), 'label' => t('manage_users')],
];
$nav = $nav ?? 'home';
?>
<nav class="owner-nav">
  <?php foreach ($links as $key => $link): ?>
    <a href="<?= e($link['url']) ?>" class="<?= $nav === $key ? 'active' : '' ?>"><?= e($link['label']) ?></a>
  <?php endforeach; ?>
</nav>
