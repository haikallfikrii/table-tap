<?php
/**
 * Owner / kasir — shop hours, open float, closing count (cash / TnG / bank / other).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/shift.php';

requireLogin(['owner', 'kasir']);

$user = currentUser();
$shopId = requireShopId();
$isOwner = ($user['role'] ?? '') === 'owner';
$lang = currentLang();
$config = getConfig();
$pageTitle = t('shift_title');
$showSound = false;
$nav = 'shift';
$flash = isset($_GET['ok']) ? t('saved') : '';

$shop = getShopOrFail($shopId);
$openShift = findOpenCashShift($shopId);
$sales = $openShift
    ? shiftSalesSummary($shopId, (string) $openShift['opened_at'], null)
    : null;
$expectedCash = $openShift && $sales ? shiftExpectedCash($openShift, $sales) : 0;
$history = recentCashShifts($shopId, 14);
$hoursOn = shopHoursEnabled($shop);
$openVal = substr((string) ($shop['open_time'] ?? ''), 0, 5);
$closeVal = substr((string) ($shop['close_time'] ?? ''), 0, 5);
$overnight = $hoursOn && shopTimeToMinutes($shop['close_time']) <= shopTimeToMinutes($shop['open_time']);
$accepting = shopIsOpenForOrders($shop);
?>
<?php require dirname(__DIR__, 2) . '/includes/admin_header.php'; ?>
<?php if ($isOwner): require __DIR__ . '/_nav.php'; endif; ?>

<?php if ($flash !== ''): ?>
  <p class="flash ok"><?= e($flash) ?></p>
<?php endif; ?>

<p class="order-meta" style="margin-top:0">
  <?= e(t('shift_business_day')) ?>: <strong><?= e(shopBusinessDate($shop)) ?></strong>
  · <?= $accepting ? e(t('shift_status_open')) : e(t('shift_status_closed')) ?>
  <?php if ($hoursOn && shopHoursLabel($shop) !== ''): ?>
    · <?= e(shopHoursLabel($shop)) ?>
  <?php endif; ?>
</p>

<?php if ($isOwner): ?>
<fieldset class="settings-fieldset" style="margin-bottom:20px">
  <legend><?= e(t('shift_hours')) ?></legend>
  <p class="settings-fieldset-desc"><?= e(t('shift_hours_hint')) ?></p>
  <form id="hours-form" class="settings-row" style="flex-wrap:wrap;gap:12px;align-items:flex-end">
    <label class="settings-check">
      <input type="checkbox" name="hours_enabled" value="1" <?= $hoursOn ? 'checked' : '' ?>>
      <span><?= e(t('shift_hours_enable')) ?></span>
    </label>
    <label><?= e(t('shift_open_time')) ?>
      <input type="time" name="open_time" value="<?= e($openVal) ?>" style="display:block;margin-top:4px">
    </label>
    <label><?= e(t('shift_close_time')) ?>
      <input type="time" name="close_time" value="<?= e($closeVal) ?>" style="display:block;margin-top:4px">
    </label>
    <?php if ($overnight): ?>
      <span class="badge badge-info"><?= e(t('shift_overnight')) ?></span>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary btn-sm"><?= e(t('shift_save_hours')) ?></button>
  </form>
</fieldset>
<?php endif; ?>

<div class="stat-row" style="margin-bottom:20px">
  <?php if ($openShift): ?>
  <div class="stat-card">
    <div class="label"><?= e(t('shift_opening_float')) ?></div>
    <div class="value"><?= e(formatMoney((float) $openShift['opening_float'])) ?></div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('shift_sales_live')) ?></div>
    <div class="value"><?= e(formatMoney((float) ($sales['total'] ?? 0))) ?></div>
    <div class="order-meta"><?= (int) ($sales['order_count'] ?? 0) ?> orders</div>
  </div>
  <div class="stat-card">
    <div class="label"><?= e(t('shift_expected_cash')) ?></div>
    <div class="value"><?= e(formatMoney($expectedCash)) ?></div>
  </div>
  <?php else: ?>
  <div class="stat-card">
    <div class="label"><?= e(t('shift_current')) ?></div>
    <div class="value" style="font-size:1rem"><?= e(t('shift_no_shift')) ?></div>
  </div>
  <?php endif; ?>
</div>

<?php if ($openShift && $sales): ?>
<div class="order-meta" style="margin-bottom:16px">
  Counter <?= e(formatMoney($sales['counter'])) ?>
  · COD <?= e(formatMoney($sales['cod'])) ?>
  · DuitNow <?= e(formatMoney($sales['duitnow'])) ?>
</div>
<?php endif; ?>

<div class="table-grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));margin-bottom:24px">
  <?php if (!$openShift): ?>
  <section class="order-card">
    <h2 style="margin:0 0 12px;font-size:1.1rem"><?= e(t('shift_open_btn')) ?></h2>
    <p class="order-meta"><?= e(t('shift_opening_float_hint')) ?></p>
    <form id="open-form">
      <label><?= e(t('shift_opening_float')) ?></label>
      <input type="number" name="opening_float" min="0" step="0.01" value="0" inputmode="decimal" style="width:100%;margin-bottom:12px">
      <button type="submit" class="btn btn-success" style="width:100%"><?= e(t('shift_open_btn')) ?></button>
    </form>
  </section>
  <?php else: ?>
  <section class="order-card">
    <h2 style="margin:0 0 12px;font-size:1.1rem"><?= e(t('shift_close_btn')) ?></h2>
    <p class="order-meta"><?= e(t('shift_close_count')) ?></p>
    <form id="close-form">
      <label><?= e(t('shift_close_cash')) ?></label>
      <input type="number" name="close_cash" min="0" step="0.01" required inputmode="decimal" style="width:100%;margin-bottom:8px">
      <label><?= e(t('shift_close_tng')) ?></label>
      <input type="number" name="close_tng" min="0" step="0.01" value="0" inputmode="decimal" style="width:100%;margin-bottom:8px">
      <label><?= e(t('shift_close_bank')) ?></label>
      <input type="number" name="close_bank" min="0" step="0.01" value="0" inputmode="decimal" style="width:100%;margin-bottom:8px">
      <label><?= e(t('shift_close_other')) ?></label>
      <input type="number" name="close_other" min="0" step="0.01" value="0" inputmode="decimal" style="width:100%;margin-bottom:8px">
      <label><?= e(t('shift_close_notes')) ?></label>
      <input type="text" name="close_notes" maxlength="500" style="width:100%;margin-bottom:12px">
      <button type="submit" class="btn btn-primary" style="width:100%"><?= e(t('shift_close_btn')) ?></button>
    </form>
  </section>
  <?php endif; ?>
</div>

<h2 class="section-title"><?= e(t('shift_history')) ?></h2>
<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th><?= e(t('shift_business_day')) ?></th>
        <th><?= e(t('shift_open_time')) ?></th>
        <th>Float</th>
        <th><?= e(t('shift_sales_live')) ?></th>
        <th><?= e(t('shift_counted_total')) ?></th>
        <th><?= e(t('shift_variance')) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($history === []): ?>
        <tr><td colspan="7" class="order-meta"><?= e(t('no_data')) ?></td></tr>
      <?php else: ?>
        <?php foreach ($history as $h):
          $hs = shiftSalesSummary($shopId, (string) $h['opened_at'], $h['closed_at'] ?? null);
          $exp = shiftExpectedCash($h, $hs);
          $closed = ($h['status'] ?? '') === 'closed';
          $totals = shiftCloseTotals($h);
          $var = $closed ? round($totals['cash'] - $exp, 2) : null;
        ?>
        <tr>
          <td><?= e((string) $h['business_date']) ?></td>
          <td class="order-meta"><?= e(substr((string) $h['opened_at'], 0, 16)) ?><?= $closed && $h['closed_at'] ? ' → ' . e(substr((string) $h['closed_at'], 11, 5)) : '' ?></td>
          <td><?= e(formatMoney((float) $h['opening_float'])) ?></td>
          <td><?= e(formatMoney($hs['total'])) ?></td>
          <td><?= $closed ? e(formatMoney($totals['all'])) : '—' ?></td>
          <td><?= $var !== null ? e(formatMoney($var)) : '—' ?></td>
          <td><span class="badge badge-<?= $closed ? 'selesai' : 'diproses' ?>"><?= e($closed ? t('shift_status_closed') : t('shift_status_open')) ?></span></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
(function () {
  const api = <?= json_encode(baseUrl('admin/api/shift_action.php'), JSON_UNESCAPED_UNICODE) ?>;
  const msgOpened = <?= json_encode(t('shift_opened'), JSON_UNESCAPED_UNICODE) ?>;
  const msgClosed = <?= json_encode(t('shift_closed'), JSON_UNESCAPED_UNICODE) ?>;

  async function post(body) {
    const res = await fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Failed');
    return data;
  }

  document.getElementById('hours-form')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    try {
      await post({
        action: 'save_hours',
        hours_enabled: fd.get('hours_enabled') ? 1 : 0,
        open_time: fd.get('open_time') || '',
        close_time: fd.get('close_time') || '',
      });
      location.reload();
    } catch (err) { alert(err.message); }
  });

  document.getElementById('open-form')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    try {
      await post({ action: 'open', opening_float: fd.get('opening_float') || 0 });
      alert(msgOpened);
      location.reload();
    } catch (err) { alert(err.message); }
  });

  document.getElementById('close-form')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    try {
      const data = await post({
        action: 'close',
        close_cash: fd.get('close_cash') || 0,
        close_tng: fd.get('close_tng') || 0,
        close_bank: fd.get('close_bank') || 0,
        close_other: fd.get('close_other') || 0,
        close_notes: fd.get('close_notes') || '',
      });
      const s = data.summary || {};
      let txt = msgClosed;
      if (s.cash_variance != null) {
        txt += '\n' + <?= json_encode(t('shift_variance'), JSON_UNESCAPED_UNICODE) ?> + ': RM ' + Number(s.cash_variance).toFixed(2);
      }
      alert(txt);
      location.reload();
    } catch (err) { alert(err.message); }
  });
})();
</script>

<?php require dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
