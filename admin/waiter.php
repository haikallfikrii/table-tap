<?php
/**
 * Waiter dashboard — items ready to pick up / deliver
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

requireLogin(['waiter', 'owner']);

$user = currentUser();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('waiter_title');
$showSound = true;

$adminScripts = [
    assetUrl('js/sound.js'),
    assetUrl('js/live-poll.js'),
    assetUrl('js/waiter.js'),
];

$i18n = [
    'mark_pickup'      => t('mark_pickup'),
    'mark_delivered'   => t('mark_delivered'),
    'no_waiter_items'  => t('no_waiter_items'),
    'table_n'          => t('table_n'),
    'notes'            => t('notes'),
    'enable_sound'     => t('enable_sound'),
    'sound_on'         => t('sound_on'),
    'dapur_title'      => t('dapur_title'),
    'minuman_title'    => t('minuman_title'),
    'dine_in'          => t('dine_in'),
    'takeaway'         => t('takeaway'),
];
?>
<?php require dirname(__DIR__) . '/includes/admin_header.php'; ?>
<?php require dirname(__DIR__) . '/includes/staff_order_flash.php'; ?>

<p style="margin:0 0 16px">
  <a class="btn btn-primary" href="<?= e(baseUrl('admin/staff_order.php?from=waiter')) ?>"><?= e(t('staff_order')) ?></a>
</p>

<div id="waiter-root" class="kitchen-grid"
     data-poll-url="<?= e(baseUrl('admin/api/waiter_poll.php')) ?>"
     data-update-url="<?= e(baseUrl('admin/api/item_status.php')) ?>"
     data-interval="<?= (int) ($config['poll_interval_ms'] ?? 3000) ?>"
     data-lang="<?= e($lang) ?>"
     data-i18n="<?= e(json_encode($i18n, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="empty-state"><?= e(t('loading')) ?></div>
</div>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
