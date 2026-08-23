<?php
/**
 * Staff order for a table — waiter / kasir / owner, guests without a phone.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

requireLogin(['waiter', 'kasir', 'owner']);

$user = currentUser();
$shopId = requireShopId();
$lang = currentLang();
$config = getConfig();

$from = (string) ($_GET['from'] ?? '');
if (!in_array($from, ['waiter', 'kasir', 'owner'], true)) {
    $from = match ((string) ($user['role'] ?? '')) {
        'kasir' => 'kasir',
        'owner' => 'owner',
        default => 'waiter',
    };
}

$tableId = (int) ($_GET['table_id'] ?? 0);
$staffBackUrl = staffStationHome($from);
$staffFrom = $from;
$staffMode = true;
$submitUrl = baseUrl('admin/api/staff_submit_order.php');

if ($tableId <= 0) {
    $pageTitle = t('staff_order');
    $showSound = false;
    $adminScripts = [];
    $tables = listActiveShopTables($shopId);
    ?>
    <?php require dirname(__DIR__) . '/includes/admin_header.php'; ?>
    <p style="margin:0 0 16px">
      <a class="btn btn-ghost btn-sm" href="<?= e($staffBackUrl) ?>"><?= e(t('staff_back')) ?></a>
    </p>
    <p class="order-meta" style="margin:0 0 16px"><?= e(t('staff_pick_table')) ?></p>
    <?php if ($tables === []): ?>
      <div class="empty-state"><?= e(t('no_data')) ?></div>
    <?php else: ?>
      <div class="table-grid">
        <?php foreach ($tables as $row): ?>
          <a class="order-card" style="text-decoration:none" href="<?= e(baseUrl('admin/staff_order.php?from=' . urlencode($from) . '&table_id=' . (int) $row['id'])) ?>">
            <div class="table-num"><?= e(t('table')) ?> <?= e($row['nomor_meja']) ?></div>
            <p class="order-meta"><?= e(t('staff_order')) ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
    <?php
    exit;
}

$table = findTableByIdForShop($tableId, $shopId);
if (!$table) {
    redirect(baseUrl('admin/staff_order.php?from=' . urlencode($from)));
}

$brand = shopBrand($table);
$menu = getMenuGrouped($shopId, $lang);
$sstEnabled = (int) ($table['sst_enabled'] ?? 0) === 1;
$sstRate = (float) ($table['sst_rate'] ?? 0);
$selfPickup = shopFulfillment($table) === 'self_pickup';
$canGallery = shopHasFeature($table, 'menu_gallery');

$i18nJs = [
    'cart_empty'    => t('cart_empty'),
    'item_note_ph'  => t('item_note_ph'),
    'remove'        => t('remove'),
    'submit_order'  => t('submit_order'),
    'submitting'    => t('submitting'),
    'order_failed'  => t('order_failed'),
    'select_items'  => t('select_items'),
    'guest_name_required' => t('guest_name_required'),
    'sst_enabled'   => $sstEnabled,
    'sst_rate'      => $sstRate,
    'subtotal'      => t('subtotal'),
    'sst'           => t('sst'),
    'dine_in'       => t('dine_in'),
    'takeaway'      => t('takeaway'),
    'serving_type'  => t('serving_type'),
    'addon_pick' => t('addon_pick'),
    'addon_extras' => t('addon_extras'),
    'addon_required' => t('addon_required'),
    'addon_choice_required' => t('addon_choice_required'),
];

require dirname(__DIR__) . '/includes/order_ui.php';
