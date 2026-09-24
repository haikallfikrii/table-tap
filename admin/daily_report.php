<?php
/**
 * Kasir / owner — daily closing report (print thermal + download CSV).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/i18n.php';
require_once dirname(__DIR__) . '/includes/shift.php';

requireLogin(['kasir', 'owner']);

$user = currentUser();
$shopId = requireShopId();
$shop = getShopOrFail($shopId);
$lang = currentLang();
$config = getConfig();
$isOwner = ($user['role'] ?? '') === 'owner';

$todayBiz = shopBusinessDate($shop);
$date = (string) ($_GET['date'] ?? $todayBiz);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $todayBiz;
}

$report = dailyClosingReport($shopId, $shop, $date);
$shopName = (string) ($shop['nama_kedai'] ?? 'TableTap');

// CSV download — no HTML shell
if (($_GET['format'] ?? '') === 'csv') {
    $fname = 'tabletap-closing-' . $date . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Shop', $shopName]);
    fputcsv($out, ['Date', $date]);
    fputcsv($out, ['From', $report['start']]);
    fputcsv($out, ['To', $report['end']]);
    fputcsv($out, []);
    fputcsv($out, ['Orders paid', $report['order_count']]);
    fputcsv($out, ['Subtotal', number_format($report['subtotal'], 2, '.', '')]);
    fputcsv($out, ['SST', number_format($report['sst'], 2, '.', '')]);
    fputcsv($out, ['Total sales', number_format($report['total'], 2, '.', '')]);
    fputcsv($out, ['Counter', number_format($report['by_pay']['counter'], 2, '.', '')]);
    fputcsv($out, ['COD', number_format($report['by_pay']['cod'], 2, '.', '')]);
    fputcsv($out, ['DuitNow', number_format($report['by_pay']['duitnow'], 2, '.', '')]);
    fputcsv($out, ['Unpaid open', $report['unpaid_count'], number_format($report['unpaid_total'], 2, '.', '')]);
    fputcsv($out, []);
    fputcsv($out, ['#', 'Table', 'Serve', 'Paid at', 'Guest', 'Pay', 'Subtotal', 'SST', 'Total']);
    foreach ($report['orders'] as $r) {
        $serve = (string) ($r['jenis_hidang'] ?? 'dine_in');
        $pay = (string) ($r['payment_method'] ?? 'counter');
        fputcsv($out, [
            (int) $r['id'],
            (string) $r['nomor_meja'],
            $serve,
            (string) ($r['waktu_lunas'] ?: $r['waktu_order']),
            (string) ($r['nama_pelanggan'] ?? ''),
            $pay !== '' ? $pay : 'counter',
            number_format((float) $r['subtotal'], 2, '.', ''),
            number_format((float) $r['sst_jumlah'], 2, '.', ''),
            number_format((float) $r['total_harga'], 2, '.', ''),
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = t('daily_closing_title');
$showSound = false;
$showPrinter = true;
$adminScripts = [
    assetUrl('js/thermal-print.js'),
    assetUrl('js/daily-report.js'),
];
$csvUrl = baseUrl('admin/daily_report.php?date=' . urlencode($date) . '&format=csv');
$jsonUrl = baseUrl('admin/api/daily_report_json.php?date=' . urlencode($date));
$backUrl = $isOwner ? baseUrl('admin/owner/index.php') : baseUrl('admin/kasir.php');

$i18nJs = [
    'printer_connect' => t('printer_connect'),
    'printer_connected' => t('printer_connected'),
    'printer_unsupported' => t('printer_unsupported'),
    'print_failed' => t('print_failed'),
    'daily_print_ok' => t('daily_print_ok'),
    'daily_print_bt' => t('daily_print_bt'),
    'daily_closing_title' => t('daily_closing_title'),
    'daily_closing_date' => t('daily_closing_date'),
    'daily_orders_paid' => t('daily_orders_paid'),
    'daily_sales_total' => t('daily_sales_total'),
    'daily_unpaid_open' => t('daily_unpaid_open'),
    'subtotal' => t('subtotal'),
    'sst' => t('sst'),
    'pay_method' => t('pay_method'),
    'pay_counter' => t('pay_counter'),
    'pay_cod' => t('pay_cod'),
    'pay_duitnow' => t('pay_duitnow'),
    'dine_in' => t('dine_in'),
    'takeaway' => t('takeaway'),
    'delivery' => t('delivery'),
    'order_history' => t('order_history'),
    'other' => t('other'),
    'thank_you' => t('thank_you'),
    'printing' => t('submitting'),
];
?>
<?php require dirname(__DIR__) . '/includes/admin_header.php'; ?>
<?php if ($isOwner): require __DIR__ . '/owner/_nav.php'; endif; ?>

<style>
  .closing-actions { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 16px; align-items:center; }
  .closing-actions form { display:flex; flex-wrap:wrap; gap:8px; align-items:end; margin:0; }
  .closing-meta { margin:0 0 14px; }
  @media print {
    .admin-top, .owner-nav, .no-print, .lang-toggle, .print-bar, #print-status { display:none !important; }
    body { background:#fff; }
    .admin-main { max-width:none; padding:0; }
    .stat-card { break-inside:avoid; }
  }
</style>

<p class="no-print" style="margin:0 0 12px">
  <a class="btn btn-ghost btn-sm" href="<?= e($backUrl) ?>">← <?= e(t('back')) ?></a>
  <?php if (shiftColumnsExist()): ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(baseUrl('admin/owner/shift.php')) ?>"><?= e(t('shift_kasir_link')) ?></a>
  <?php endif; ?>
</p>

<div class="closing-actions no-print">
  <form method="get" action="">
    <label><?= e(t('daily_closing_date')) ?>
      <input type="date" name="date" value="<?= e($date) ?>" style="display:block;margin-top:4px">
    </label>
    <button type="submit" class="btn btn-secondary btn-sm"><?= e(t('daily_closing_show')) ?></button>
  </form>
  <a class="btn btn-primary btn-sm" href="<?= e($csvUrl) ?>"><?= e(t('daily_download_csv')) ?></a>
  <button type="button" class="btn btn-secondary btn-sm" id="btn-print-page"><?= e(t('daily_print_page')) ?></button>
  <button type="button" class="btn btn-secondary btn-sm" id="btn-print-bt"><?= e(t('daily_print_bt')) ?></button>
</div>

<p class="print-status no-print" id="print-status"><?= e(t('kasir_printer_hint')) ?></p>

<p class="closing-meta order-meta">
  <strong><?= e($shopName) ?></strong>
  · <?= e(t('shift_business_day')) ?>: <strong><?= e($date) ?></strong>
  · <?= e($report['start']) ?> → <?= e($report['end']) ?>
</p>

<div class="stat-row" style="margin-bottom:16px">
  <div class="stat-card">
    <div class="label"><?= e(t('daily_orders_paid')) ?></div>
    <div class="value"><?= (int) $report['order_count'] ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('daily_sales_total')) ?></div>
    <div class="value"><?= e(formatMoney($report['total'])) ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('sst')) ?></div>
    <div class="value" style="font-size:1.2rem"><?= e(formatMoney($report['sst'])) ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('daily_unpaid_open')) ?></div>
    <div class="value" style="font-size:1.2rem"><?= (int) $report['unpaid_count'] ?></div>
    <div class="order-meta"><?= e(formatMoney($report['unpaid_total'])) ?></div>
  </div>
</div>

<div class="order-meta" style="margin-bottom:16px">
  <?= e(t('pay_counter')) ?> <?= e(formatMoney($report['by_pay']['counter'])) ?>
  · COD <?= e(formatMoney($report['by_pay']['cod'])) ?>
  · DuitNow <?= e(formatMoney($report['by_pay']['duitnow'])) ?>
  <?php if ($report['by_pay']['other'] > 0): ?>
    · <?= e(t('other')) ?> <?= e(formatMoney($report['by_pay']['other'])) ?>
  <?php endif; ?>
  <br>
  <?= e(t('dine_in')) ?> <?= e(formatMoney($report['by_serve']['dine_in'])) ?>
  · <?= e(t('takeaway')) ?> <?= e(formatMoney($report['by_serve']['takeaway'])) ?>
  · <?= e(t('delivery')) ?> <?= e(formatMoney($report['by_serve']['delivery'])) ?>
</div>

<div class="table-list-wrap">
  <table class="table-list">
    <thead>
      <tr>
        <th>#</th>
        <th><?= e(t('table')) ?></th>
        <th><?= e(t('serving_type')) ?></th>
        <th><?= e(t('pay_method')) ?></th>
        <th><?= e(t('total')) ?></th>
        <th><?= e(t('date')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($report['orders'] === []): ?>
        <tr><td colspan="6"><?= e(t('no_data')) ?></td></tr>
      <?php else: ?>
        <?php foreach ($report['orders'] as $r):
          $serve = (string) ($r['jenis_hidang'] ?? 'dine_in');
          $serveLabel = $serve === 'takeaway' ? t('takeaway') : ($serve === 'delivery' ? t('delivery') : t('dine_in'));
          $pay = (string) ($r['payment_method'] ?? 'counter');
          if ($pay === '' || $pay === 'null') {
              $pay = 'counter';
          }
          $payLabel = match ($pay) {
              'cod' => t('pay_cod'),
              'duitnow' => t('pay_duitnow'),
              'counter' => t('pay_counter'),
              default => $pay,
          };
        ?>
        <tr>
          <td><?= (int) $r['id'] ?></td>
          <td><?= e((string) $r['nomor_meja']) ?></td>
          <td><?= e($serveLabel) ?></td>
          <td><?= e($payLabel) ?></td>
          <td><strong><?= e(formatMoney((float) $r['total_harga'])) ?></strong></td>
          <td><?= e((string) ($r['waktu_lunas'] ?: $r['waktu_order'])) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div
  id="daily-report-app"
  class="no-print"
  hidden
  data-json-url="<?= e($jsonUrl) ?>"
  data-shop-name="<?= e($shopName) ?>"
  data-i18n="<?= e(json_encode($i18nJs, JSON_UNESCAPED_UNICODE)) ?>"
></div>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
