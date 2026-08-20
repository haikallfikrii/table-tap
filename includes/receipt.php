<?php
/**
 * Order receipts — fetch, render, print, and e-mail.
 */

declare(strict_types=1);

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/verification.php';

function orderReceiptUrl(int $orderId, bool $autoPrint = false): string
{
    $url = baseUrl('admin/receipt.php?order=' . $orderId);
    if ($autoPrint) {
        $url .= '&print=1';
    }
    return $url;
}

/**
 * @return array<string, mixed>|null
 */
function fetchOrderReceipt(int $orderId, int $shopId, string $lang = 'my'): ?array
{
    $emailCol = orderCustomerEmailColumnExists() ? ', o.customer_email' : '';
    $stmt = db()->prepare(
        "SELECT o.id, o.waktu_order, o.waktu_lunas, o.status_bayar, o.status_order,
                o.jenis_hidang, o.nama_pelanggan, o.subtotal, o.sst_rate, o.sst_jumlah, o.total_harga,
                o.sumber_order{$emailCol},
                t.nomor_meja, s.nama_kedai, s.sst_enabled
         FROM orders o
         INNER JOIN tables t ON t.id = o.table_id
         INNER JOIN shops s ON s.id = o.shop_id
         WHERE o.id = ? AND o.shop_id = ?
         LIMIT 1"
    );
    $stmt->execute([$orderId, $shopId]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    $itemStmt = db()->prepare(
        "SELECT qty, catatan, harga_saat_order, nama_saat_order_my, nama_saat_order_en
         FROM order_items
         WHERE order_id = ?
         ORDER BY id ASC"
    );
    $itemStmt->execute([$orderId]);
    $items = [];
    foreach ($itemStmt->fetchAll() as $row) {
        $nama = $lang === 'en' ? $row['nama_saat_order_en'] : $row['nama_saat_order_my'];
        $qty = (int) $row['qty'];
        $unit = (float) $row['harga_saat_order'];
        $items[] = [
            'nama' => (string) $nama,
            'qty' => $qty,
            'unit' => $unit,
            'line_total' => round($unit * $qty, 2),
            'catatan' => (string) ($row['catatan'] ?? ''),
        ];
    }

    $email = orderCustomerEmailColumnExists() ? trim((string) ($order['customer_email'] ?? '')) : '';

    $splitFrom = 0;
    static $hasSplitCol = null;
    if ($hasSplitCol === null) {
        try {
            $hasSplitCol = (bool) db()->query("SHOW COLUMNS FROM orders LIKE 'split_from_order_id'")->fetch();
        } catch (Throwable $e) {
            $hasSplitCol = false;
        }
    }
    if ($hasSplitCol) {
        $sf = db()->prepare('SELECT split_from_order_id FROM orders WHERE id = ? LIMIT 1');
        $sf->execute([(int) $order['id']]);
        $splitFrom = (int) ($sf->fetchColumn() ?: 0);
    }

    return [
        'order_id' => (int) $order['id'],
        'shop_name' => (string) $order['nama_kedai'],
        'nomor_meja' => (string) $order['nomor_meja'],
        'waktu_order' => (string) $order['waktu_order'],
        'waktu_lunas' => (string) ($order['waktu_lunas'] ?? ''),
        'status_bayar' => (string) $order['status_bayar'],
        'jenis_hidang' => ($order['jenis_hidang'] ?? 'dine_in') === 'takeaway' ? 'takeaway' : 'dine_in',
        'nama_pelanggan' => (string) ($order['nama_pelanggan'] ?? ''),
        'customer_email' => $email,
        'customer_email_masked' => $email !== '' ? maskEmail($email) : '',
        'sumber_order' => ($order['sumber_order'] ?? 'qr') === 'staf' ? 'staf' : 'qr',
        'split_from_order_id' => $splitFrom > 0 ? $splitFrom : null,
        'subtotal' => (float) $order['subtotal'],
        'sst_rate' => (float) $order['sst_rate'],
        'sst_jumlah' => (float) $order['sst_jumlah'],
        'total_harga' => (float) $order['total_harga'],
        'sst_enabled' => (int) ($order['sst_enabled'] ?? 0) === 1,
        'items' => $items,
        'lang' => $lang === 'en' ? 'en' : 'my',
    ];
}

function receiptServeLabel(string $jenisHidang, string $lang): string
{
    if ($jenisHidang === 'takeaway') {
        return $lang === 'en' ? 'Takeaway' : 'Bungkus';
    }
    return $lang === 'en' ? 'Dine in' : 'Makan sini';
}

function receiptPlainText(array $receipt): string
{
    $lang = ($receipt['lang'] ?? 'my') === 'en' ? 'en' : 'my';
    $lines = [];
    $lines[] = (string) ($receipt['shop_name'] ?? '');
    $lines[] = str_repeat('=', 32);
    $lines[] = ($lang === 'en' ? 'Receipt #' : 'Resit #') . (int) ($receipt['order_id'] ?? 0);
    $paidAt = trim((string) ($receipt['waktu_lunas'] ?? ''));
    $lines[] = ($lang === 'en' ? 'Paid' : 'Dibayar') . ': ' . ($paidAt !== '' ? $paidAt : (string) ($receipt['waktu_order'] ?? ''));
    $lines[] = ($lang === 'en' ? 'Table' : 'Meja') . ': ' . (string) ($receipt['nomor_meja'] ?? '');
    if (!empty($receipt['nama_pelanggan'])) {
        $lines[] = ($lang === 'en' ? 'Guest' : 'Pelanggan') . ': ' . (string) $receipt['nama_pelanggan'];
    }
    $lines[] = receiptServeLabel((string) ($receipt['jenis_hidang'] ?? 'dine_in'), $lang);
    if (!empty($receipt['split_from_order_id'])) {
        $lines[] = ($lang === 'en' ? 'Split from #' : 'Bahagi dari #') . (int) $receipt['split_from_order_id'];
    }
    $lines[] = str_repeat('-', 32);

    foreach ($receipt['items'] ?? [] as $item) {
        $qty = (int) ($item['qty'] ?? 0);
        $nama = (string) ($item['nama'] ?? '');
        $lineTotal = formatMoney((float) ($item['line_total'] ?? 0));
        $lines[] = $qty . 'x ' . $nama . '  ' . $lineTotal;
        $note = trim((string) ($item['catatan'] ?? ''));
        if ($note !== '') {
            $lines[] = '   * ' . $note;
        }
    }

    $lines[] = str_repeat('-', 32);
    $lines[] = ($lang === 'en' ? 'Subtotal' : 'Subjumlah') . ': ' . formatMoney((float) ($receipt['subtotal'] ?? 0));
    if ((float) ($receipt['sst_jumlah'] ?? 0) > 0) {
        $rate = number_format((float) ($receipt['sst_rate'] ?? 0), 2);
        $lines[] = 'SST (' . $rate . '%): ' . formatMoney((float) $receipt['sst_jumlah']);
    }
    $lines[] = ($lang === 'en' ? 'Total' : 'Jumlah') . ': ' . formatMoney((float) ($receipt['total_harga'] ?? 0));
    $lines[] = str_repeat('=', 32);
    $lines[] = $lang === 'en' ? 'Thank you!' : 'Terima kasih!';
    $lines[] = 'TableTap — tabletap.my';

    return implode("\n", $lines);
}

function receiptHtml(array $receipt, bool $standalone = true): string
{
    $lang = ($receipt['lang'] ?? 'my') === 'en' ? 'en' : 'my';
    $title = $lang === 'en' ? 'Receipt' : 'Resit';
    $paidAt = trim((string) ($receipt['waktu_lunas'] ?? ''));
    if ($paidAt === '') {
        $paidAt = (string) ($receipt['waktu_order'] ?? '');
    }

    ob_start();
    if ($standalone): ?>
<!DOCTYPE html>
<html lang="<?= e($lang === 'en' ? 'en' : 'ms') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> #<?= (int) ($receipt['order_id'] ?? 0) ?></title>
  <link rel="stylesheet" href="<?= e(assetUrl('css/receipt.css')) ?>">
</head>
<body class="receipt-body">
<?php endif; ?>
  <div class="receipt-paper">
    <div class="receipt-shop"><?= e((string) ($receipt['shop_name'] ?? '')) ?></div>
    <div class="receipt-title"><?= e($title) ?> #<?= (int) ($receipt['order_id'] ?? 0) ?></div>
    <div class="receipt-meta">
      <div><?= e($lang === 'en' ? 'Paid' : 'Dibayar') ?>: <?= e($paidAt) ?></div>
      <div><?= e($lang === 'en' ? 'Table' : 'Meja') ?>: <?= e((string) ($receipt['nomor_meja'] ?? '')) ?></div>
      <?php if (!empty($receipt['nama_pelanggan'])): ?>
        <div><?= e($lang === 'en' ? 'Guest' : 'Pelanggan') ?>: <?= e((string) $receipt['nama_pelanggan']) ?></div>
      <?php endif; ?>
      <div><?= e(receiptServeLabel((string) ($receipt['jenis_hidang'] ?? 'dine_in'), $lang)) ?></div>
      <?php if (!empty($receipt['split_from_order_id'])): ?>
        <div><?= e($lang === 'en' ? 'Split from' : 'Bahagi dari') ?> #<?= (int) $receipt['split_from_order_id'] ?></div>
      <?php endif; ?>
    </div>

    <table class="receipt-items">
      <tbody>
        <?php foreach ($receipt['items'] ?? [] as $item): ?>
          <tr>
            <td class="receipt-item-name">
              <span class="receipt-qty"><?= (int) ($item['qty'] ?? 0) ?>×</span>
              <?= e((string) ($item['nama'] ?? '')) ?>
              <?php if (trim((string) ($item['catatan'] ?? '')) !== ''): ?>
                <div class="receipt-note"><?= e((string) $item['catatan']) ?></div>
              <?php endif; ?>
            </td>
            <td class="receipt-item-amt"><?= e(formatMoney((float) ($item['line_total'] ?? 0))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="receipt-totals">
      <div class="receipt-row">
        <span><?= e($lang === 'en' ? 'Subtotal' : 'Subjumlah') ?></span>
        <span><?= e(formatMoney((float) ($receipt['subtotal'] ?? 0))) ?></span>
      </div>
      <?php if ((float) ($receipt['sst_jumlah'] ?? 0) > 0): ?>
        <div class="receipt-row">
          <span>SST (<?= e(number_format((float) ($receipt['sst_rate'] ?? 0), 2)) ?>%)</span>
          <span><?= e(formatMoney((float) $receipt['sst_jumlah'])) ?></span>
        </div>
      <?php endif; ?>
      <div class="receipt-row receipt-grand">
        <span><?= e($lang === 'en' ? 'Total' : 'Jumlah') ?></span>
        <span><?= e(formatMoney((float) ($receipt['total_harga'] ?? 0))) ?></span>
      </div>
    </div>

    <div class="receipt-thanks"><?= e($lang === 'en' ? 'Thank you!' : 'Terima kasih!') ?></div>
    <div class="receipt-brand">TableTap</div>
  </div>

  <?php if ($standalone): ?>
  <div class="receipt-actions no-print">
    <button type="button" class="btn btn-primary" onclick="window.print()"><?= e($lang === 'en' ? 'Print' : 'Cetak') ?></button>
    <button type="button" class="btn btn-secondary" onclick="window.close()"><?= e($lang === 'en' ? 'Close' : 'Tutup') ?></button>
  </div>
</body>
</html>
  <?php endif;
    return (string) ob_get_clean();
}

/**
 * @return array{ok:bool,email?:string,error?:string}
 */
function sendOrderReceiptEmail(int $orderId, int $shopId, ?string $email = null, string $lang = 'my'): array
{
    $receipt = fetchOrderReceipt($orderId, $shopId, $lang);
    if (!$receipt) {
        return ['ok' => false, 'error' => 'not_found'];
    }
    if (($receipt['status_bayar'] ?? '') !== 'lunas') {
        return ['ok' => false, 'error' => 'not_paid'];
    }

    $to = $email !== null && $email !== '' ? normalizeEmail($email) : null;
    if ($to === null && !empty($receipt['customer_email'])) {
        $to = normalizeEmail((string) $receipt['customer_email']);
    }
    if ($to === null) {
        return ['ok' => false, 'error' => 'no_email'];
    }

    saveOrderCustomerEmail($orderId, $to);

    $isEn = $lang === 'en';
    $subject = ($isEn ? 'Receipt from ' : 'Resit dari ')
        . (string) ($receipt['shop_name'] ?? 'TableTap')
        . ' #' . (int) $receipt['order_id'];
    $body = receiptPlainText($receipt);

    if (!sendAppMail($to, $subject, $body)) {
        return ['ok' => false, 'error' => 'send_failed', 'email' => maskEmail($to)];
    }

    return ['ok' => true, 'email' => maskEmail($to)];
}
