<?php
/**
 * Kasir dashboard — active orders grouped by table, polling every 3s
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/i18n.php';

requireLogin(['kasir', 'owner']);

$user = currentUser();
$lang = currentLang();
$config = getConfig();
$pageTitle = t('kasir_title');
$showSound = true;
$adminScripts = [
    assetUrl('js/sound.js'),
    assetUrl('js/live-poll.js'),
    assetUrl('js/kasir.js'),
];

$i18n = [
    'mark_paid'     => t('mark_paid'),
    'unpaid'        => t('unpaid'),
    'paid'          => t('paid'),
    'no_orders'     => t('no_orders'),
    'table_n'       => t('table_n'),
    'total'         => t('total'),
    'grand_total'   => t('grand_total'),
    'enable_sound'  => t('enable_sound'),
    'sound_on'      => t('sound_on'),
    'status_menunggu'   => t('status_menunggu'),
    'status_diproses'   => t('status_diproses'),
    'status_selesai'    => t('status_selesai'),
    'notes'         => t('notes'),
    'status_item_menunggu' => t('status_item_menunggu'),
    'status_item_sedang_dimasak' => t('status_item_sedang'),
    'status_item_siap' => t('status_item_siap'),
    'status_item_diambil' => t('status_item_diambil'),
    'status_item_dihantar' => t('status_item_dihantar'),
    'dine_in'       => t('dine_in'),
    'takeaway'      => t('takeaway'),
    'mark_collected'=> t('mark_collected'),
    'silence_alert' => t('silence_alert'),
    'sourced_staff' => t('sourced_staff'),
    'print_receipt' => t('print_receipt'),
    'send_receipt'  => t('send_receipt'),
    'receipt'       => t('receipt'),
    'receipt_after_paid' => t('receipt_after_paid'),
    'receipt_email_prompt' => t('receipt_email_prompt'),
    'receipt_sent'  => t('receipt_sent'),
    'receipt_send_failed' => t('receipt_send_failed'),
];
?>
<?php require dirname(__DIR__) . '/includes/admin_header.php'; ?>
<?php require dirname(__DIR__) . '/includes/staff_order_flash.php'; ?>

<p style="margin:0 0 16px">
  <a class="btn btn-primary" href="<?= e(baseUrl('admin/staff_order.php?from=kasir')) ?>"><?= e(t('staff_order')) ?></a>
</p>

<div class="stat-row">
  <div class="stat-card">
    <div class="label"><?= e(t('orders_active')) ?></div>
    <div class="value" id="stat-orders">0</div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('unpaid')) ?></div>
    <div class="value" id="stat-unpaid">0</div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('grand_total')) ?></div>
    <div class="value" id="stat-total">RM 0.00</div>
  </div>
</div>

<div id="orders-root" class="table-grid"
     data-poll-url="<?= e(baseUrl('admin/api/orders_poll.php')) ?>"
     data-paid-url="<?= e(baseUrl('admin/api/mark_paid.php')) ?>"
     data-pickup-url="<?= e(baseUrl('admin/api/pickup_action.php')) ?>"
     data-receipt-url="<?= e(baseUrl('admin/receipt.php')) ?>"
     data-send-receipt-url="<?= e(baseUrl('admin/api/send_receipt.php')) ?>"
     data-interval="<?= (int) ($config['poll_interval_ms'] ?? 3000) ?>"
     data-lang="<?= e($lang) ?>"
     data-i18n="<?= e(json_encode($i18n, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="empty-state"><?= e(t('loading')) ?></div>
</div>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
