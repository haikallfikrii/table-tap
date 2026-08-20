<?php
/**
 * Shared kitchen / drinks / custom-station ticket screen.
 * Expects: $user, $shop, $station, $selfPickup, $lang, $config
 */

declare(strict_types=1);

$pageTitle = stationLabel($station, $lang);
$showSound = true;
$kategori = (($station['kod'] ?? '') === 'minuman') ? 'minuman' : 'makanan';
$stationId = (int) ($station['id'] ?? 0);

$adminScripts = [
    assetUrl('js/sound.js'),
    assetUrl('js/live-poll.js'),
    assetUrl('js/kitchen.js'),
];

$i18n = [
    'mark_done'        => t('mark_done'),
    'mark_ready'       => $selfPickup ? t('mark_ready_self') : t('mark_ready'),
    'mark_cooking'     => t('mark_cooking'),
    'mark_collected'   => t('mark_collected'),
    'no_kitchen_items' => t('no_kitchen_items'),
    'table_n'          => t('table_n'),
    'notes'            => t('notes'),
    'enable_sound'     => t('enable_sound'),
    'sound_on'         => t('sound_on'),
    'status_item_menunggu' => t('status_item_menunggu'),
    'status_item_sedang'   => t('status_item_sedang'),
    'status_item_selesai'  => t('status_item_selesai'),
    'dine_in'          => t('dine_in'),
    'takeaway'         => t('takeaway'),
];

$allStations = [];
if (($user['role'] ?? '') === 'owner') {
    $allStations = shopStations((int) $shop['id'], true);
}
?>
<?php require dirname(__DIR__) . '/includes/admin_header.php'; ?>

<?php if (count($allStations) > 2): ?>
  <nav class="owner-nav" style="margin-bottom:12px">
    <?php foreach ($allStations as $st): ?>
      <a href="<?= e(stationScreenUrl($st)) ?>" class="<?= (int) $st['id'] === $stationId ? 'active' : '' ?>">
        <?= e(stationLabel($st, $lang)) ?>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<div id="kitchen-root" class="kitchen-grid"
     data-poll-url="<?= e(baseUrl('admin/api/kitchen_poll.php')) ?>"
     data-update-url="<?= e(baseUrl('admin/api/item_status.php')) ?>"
     data-kategori="<?= e($kategori) ?>"
     data-station-id="<?= e((string) $stationId) ?>"
     data-fulfillment="<?= e($selfPickup ? 'self_pickup' : 'waiter') ?>"
     data-interval="<?= (int) ($config['poll_interval_ms'] ?? 3000) ?>"
     data-lang="<?= e($lang) ?>"
     data-i18n="<?= e(json_encode($i18n, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="empty-state"><?= e(t('loading')) ?></div>
</div>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
