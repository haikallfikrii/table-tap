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
$showPrinter = true;
$adminScripts = [
    assetUrl('js/sound.js'),
    assetUrl('js/live-poll.js'),
    assetUrl('js/thermal-print.js'),
    assetUrl('js/kasir.js'),
];

$i18n = [
    'mark_paid'     => t('mark_paid'),
    'unpaid'        => t('unpaid'),
    'paid'          => t('paid'),
    'no_orders'     => t('no_orders'),
    'table_n'       => t('table_n'),
    'table'         => t('table'),
    'total'         => t('total'),
    'subtotal'      => t('subtotal'),
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
    'guest_name'    => t('guest_name'),
    'ops_orders_n'  => t('ops_orders_n'),
    'table_unpaid'  => t('table_unpaid'),
    'split_bill'    => t('split_bill'),
    'split_hint'    => t('split_hint'),
    'split_select_partial' => t('split_select_partial'),
    'split_confirm' => t('split_confirm'),
    'split_from'    => t('split_from'),
    'thank_you'     => t('thank_you'),
    'printer_connect' => t('printer_connect'),
    'printer_disconnect' => t('printer_disconnect'),
    'printer_connected' => t('printer_connected'),
    'printer_hint'  => t('printer_hint'),
    'kasir_printer_hint' => t('kasir_printer_hint'),
    'printer_unsupported' => t('printer_unsupported'),
    'printer_cancelled' => t('printer_cancelled'),
    'autoprint_on'  => t('autoprint_on'),
    'autoprint_off' => t('autoprint_off'),
    'print_test'    => t('print_test'),
    'print_test_ok' => t('print_test_ok'),
    'print_test_item' => t('print_test_item'),
    'print_failed'  => t('print_failed'),
    'delivery'      => t('delivery'),
    'pay_cod'       => t('pay_cod'),
    'pay_duitnow'   => t('pay_duitnow'),
    'pay_counter'   => t('pay_counter'),
    'cod_received'  => t('cod_received'),
    'cod_held_waiting' => t('cod_held_waiting'),
    'confirm_proof' => t('confirm_proof'),
    'reject_proof'  => t('reject_proof'),
    'proof_pending' => t('proof_pending'),
    'address'       => t('address'),
    'phone'         => t('phone'),
];
?>
<?php require dirname(__DIR__) . '/includes/admin_header.php'; ?>
<?php require dirname(__DIR__) . '/includes/staff_order_flash.php'; ?>

<p style="margin:0 0 16px">
  <a class="btn btn-primary" href="<?= e(baseUrl('admin/staff_order.php?from=kasir')) ?>"><?= e(t('staff_order')) ?></a>
</p>

<p class="print-status" id="print-status"><?= e(t('kasir_printer_hint')) ?></p>

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
     data-confirm-url="<?= e(baseUrl('admin/api/confirm_payment.php')) ?>"
     data-split-url="<?= e(baseUrl('admin/api/split_bill.php')) ?>"
     data-pickup-url="<?= e(baseUrl('admin/api/pickup_action.php')) ?>"
     data-receipt-url="<?= e(baseUrl('admin/receipt.php')) ?>"
     data-receipt-json-url="<?= e(baseUrl('admin/api/receipt_json.php')) ?>"
     data-send-receipt-url="<?= e(baseUrl('admin/api/send_receipt.php')) ?>"
     data-shop-name="<?= e((string) ($user['shop_name'] ?? 'TableTap')) ?>"
     data-interval="<?= (int) ($config['poll_interval_ms'] ?? 3000) ?>"
     data-lang="<?= e($lang) ?>"
     data-i18n="<?= e(json_encode($i18n, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="empty-state"><?= e(t('loading')) ?></div>
</div>

<div class="sheet-overlay" id="split-overlay"></div>
<aside class="cart-sheet split-sheet" id="split-sheet" aria-label="<?= e(t('split_bill')) ?>">
  <div class="cart-sheet-header">
    <h2 id="split-title"><?= e(t('split_bill')) ?></h2>
    <button type="button" class="btn btn-ghost btn-sm" id="btn-close-split"><?= e(t('close')) ?></button>
  </div>
  <div class="cart-sheet-body">
    <label class="split-guest-label" for="split-guest"><?= e(t('guest_name')) ?> <span class="order-meta">(<?= e(t('optional')) ?>)</span></label>
    <input type="text" id="split-guest" maxlength="40" autocomplete="name" placeholder="<?= e(t('guest_name_ph')) ?>">
    <div id="split-body" class="split-item-list"></div>
  </div>
  <div class="cart-sheet-footer">
    <div id="split-total" class="split-total"></div>
    <button type="button" class="btn btn-primary" id="btn-split-confirm" style="width:100%" disabled>
      <?= e(t('split_confirm')) ?>
    </button>
  </div>
</aside>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
