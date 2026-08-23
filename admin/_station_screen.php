<?php
/**
 * Shared kitchen / drinks / custom-station ticket screen.
 * Expects: $user, $shop, $station, $selfPickup, $lang, $config
 */

declare(strict_types=1);

$pageTitle = stationLabel($station, $lang);
$showSound = true;
$showPrinter = true;
$kategori = (($station['kod'] ?? '') === 'minuman') ? 'minuman' : 'makanan';
$stationId = (int) ($station['id'] ?? 0);

$adminScripts = [
    assetUrl('js/sound.js'),
    assetUrl('js/live-poll.js'),
    assetUrl('js/thermal-print.js'),
    assetUrl('js/kitchen.js'),
];

$i18n = [
    'mark_done'        => t('mark_done'),
    'mark_ready'       => $selfPickup ? t('mark_ready_self') : t('mark_ready'),
    'mark_cooking'     => t('mark_cooking'),
    'mark_collected'   => t('mark_collected'),
    'no_kitchen_items' => t('no_kitchen_items'),
    'table_n'          => t('table_n'),
    'table'            => t('table'),
    'order'            => t('order'),
    'guest_name'       => t('guest_name'),
    'notes'            => t('notes'),
    'enable_sound'     => t('enable_sound'),
    'sound_on'         => t('sound_on'),
    'status_item_menunggu' => t('status_item_menunggu'),
    'status_item_sedang'   => t('status_item_sedang'),
    'status_item_selesai'  => t('status_item_selesai'),
    'dine_in'          => t('dine_in'),
    'takeaway'         => t('takeaway'),
    'delivery'         => t('delivery_order_title'),
    'printer_connect'  => t('printer_connect'),
    'printer_disconnect' => t('printer_disconnect'),
    'printer_connected' => t('printer_connected'),
    'printer_hint'     => t('printer_hint'),
    'printer_unsupported' => t('printer_unsupported'),
    'printer_cancelled' => t('printer_cancelled'),
    'autoprint_on'     => t('autoprint_on'),
    'autoprint_off'    => t('autoprint_off'),
    'print_test'       => t('print_test'),
    'print_test_ok'    => t('print_test_ok'),
    'print_test_item'  => t('print_test_item'),
    'print_failed'     => t('print_failed'),
    'kitchen_ticket'   => t('kitchen_ticket'),
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

<p class="print-status" id="print-status"><?= e(t('printer_hint')) ?></p>

<div id="kitchen-root" class="kitchen-grid"
     data-poll-url="<?= e(baseUrl('admin/api/kitchen_poll.php')) ?>"
     data-update-url="<?= e(baseUrl('admin/api/item_status.php')) ?>"
     data-kategori="<?= e($kategori) ?>"
     data-station-id="<?= e((string) $stationId) ?>"
     data-station-name="<?= e(stationLabel($station, $lang)) ?>"
     data-shop-name="<?= e((string) ($shop['nama_kedai'] ?? $user['shop_name'] ?? 'TableTap')) ?>"
     data-fulfillment="<?= e($selfPickup ? 'self_pickup' : 'waiter') ?>"
     data-interval="<?= (int) ($config['poll_interval_ms'] ?? 3000) ?>"
     data-lang="<?= e($lang) ?>"
     data-i18n="<?= e(json_encode($i18n, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="empty-state"><?= e(t('loading')) ?></div>
</div>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
