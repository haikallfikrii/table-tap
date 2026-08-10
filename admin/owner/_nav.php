<?php
/** Owner sub-nav. Expects $nav active key. */
$links = [
    'home'    => ['url' => baseUrl('admin/owner/index.php'), 'label' => t('owner_title')],
    'menu'    => ['url' => baseUrl('admin/owner/menu.php'), 'label' => t('manage_menu')],
    'tables'  => ['url' => baseUrl('admin/owner/tables.php'), 'label' => t('manage_tables')],
    'reports' => ['url' => baseUrl('admin/owner/reports.php'), 'label' => t('reports')],
    'users'   => ['url' => baseUrl('admin/owner/users.php'), 'label' => t('manage_users')],
];
$nav = $nav ?? 'home';
?>
<nav class="owner-nav">
  <?php foreach ($links as $key => $link): ?>
    <a href="<?= e($link['url']) ?>" class="<?= $nav === $key ? 'active' : '' ?>"><?= e($link['label']) ?></a>
  <?php endforeach; ?>
</nav>
