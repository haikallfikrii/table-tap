<?php
/**
 * Kitchen (dapur) dashboard — makanan items only
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

requireLogin(['dapur', 'owner']);

$user = currentUser();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('dapur_title');
$showSound = true;
$kategori = 'makanan';

$adminScripts = [
    assetUrl('js/sound.js'),
    assetUrl('js/kitchen.js'),
];

$i18n = [
    'mark_done'        => t('mark_done'),
    'mark_ready'       => t('mark_ready'),
    'mark_cooking'     => t('mark_cooking'),
    'no_kitchen_items' => t('no_kitchen_items'),
    'table_n'          => t('table_n'),
    'notes'            => t('notes'),
    'enable_sound'     => t('enable_sound'),
    'sound_on'         => t('sound_on'),
    'status_item_menunggu' => t('status_item_menunggu'),
    'status_item_sedang'   => t('status_item_sedang'),
    'status_item_selesai'  => t('status_item_selesai'),
];
?>
<?php require dirname(__DIR__) . '/includes/admin_header.php'; ?>

<div id="kitchen-root" class="kitchen-grid"
     data-poll-url="<?= e(baseUrl('admin/api/kitchen_poll.php')) ?>"
     data-update-url="<?= e(baseUrl('admin/api/item_status.php')) ?>"
     data-kategori="<?= e($kategori) ?>"
     data-interval="<?= (int) ($config['poll_interval_ms'] ?? 3000) ?>"
     data-lang="<?= e($lang) ?>"
     data-i18n="<?= e(json_encode($i18n, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="empty-state"><?= e(t('loading')) ?></div>
</div>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
