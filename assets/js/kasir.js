/**
 * Kasir dashboard — poll & render
 */
(function () {
  const root = document.getElementById('orders-root');
  if (!root) return;

  const pollUrl = root.dataset.pollUrl;
  const paidUrl = root.dataset.paidUrl;
  const interval = Number(root.dataset.interval) || 3000;
  const lang = root.dataset.lang || 'my';
  const i18n = JSON.parse(root.dataset.i18n || '{}');

  let sinceId = 0;
  let busy = false;

  TableTapSound.bindButton(document.getElementById('btn-enable-sound'), {
    on: i18n.sound_on || 'Sound on',
  });

  function money(n) {
    return 'RM ' + (Number(n) || 0).toFixed(2);
  }

  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function statusLabel(key) {
    return i18n['status_' + key] || key;
  }

  function tableTitle(num) {
    const tpl = i18n.table_n || 'Table %s';
    return tpl.replace('%s', String(num));
  }

  function render(data) {
    const stats = data.stats || {};
    const elOrders = document.getElementById('stat-orders');
    const elUnpaid = document.getElementById('stat-unpaid');
    const elTotal = document.getElementById('stat-total');
    if (elOrders) elOrders.textContent = String(stats.active_orders || 0);
    if (elUnpaid) elUnpaid.textContent = String(stats.unpaid_orders || 0);
    if (elTotal) elTotal.textContent = money(stats.grand_total || 0);

    const tables = data.tables || [];
    if (tables.length === 0) {
      root.innerHTML = '<div class="empty-state">' + esc(i18n.no_orders || 'No orders') + '</div>';
      return;
    }

    const newSet = new Set(data.new_order_ids || []);

    root.innerHTML = tables.map((t) => {
      return t.orders.map((o) => {
        const unpaid = o.status_bayar === 'belum_bayar';
        const isNew = newSet.has(o.id);
        const itemsHtml = (o.items || []).map((it) => {
          const note = it.catatan
            ? '<span class="item-note">' + esc(i18n.notes || 'Notes') + ': ' + esc(it.catatan) + '</span>'
            : '';
          const st = it.status_item
            ? '<span class="item-note">' + esc(i18n['status_item_' + it.status_item] || it.status_item) + '</span>'
            : '';
          return (
            '<li>' +
              '<div><span class="qty">' + it.qty + '×</span> ' + esc(it.nama) + note + st + '</div>' +
              '<div>' + money(it.harga_saat_order * it.qty) + '</div>' +
            '</li>'
          );
        }).join('');

        const paidBtn = unpaid
          ? '<button type="button" class="btn btn-success btn-sm" data-mark-paid="' + o.id + '">' +
              esc(i18n.mark_paid || 'Mark paid') + '</button>'
          : '<span class="badge badge-lunas">' + esc(i18n.paid || 'Paid') + '</span>';

        const sstLine = (o.sst_jumlah > 0)
          ? '<div class="order-meta">SST: ' + money(o.sst_jumlah) + '</div>'
          : '';

        return (
          '<article class="order-card' + (unpaid ? ' unpaid' : '') + (isNew ? ' new-flash' : '') + '" data-order-id="' + o.id + '">' +
            '<div class="order-card-header">' +
              '<div>' +
                '<div class="table-num">' + esc(tableTitle(t.nomor_meja)) + '</div>' +
                '<div class="order-meta">#' + o.id + ' · ' + esc(o.waktu_order) +
                  (o.nama_pelanggan ? ' · ' + esc(o.nama_pelanggan) : '') +
                '</div>' +
              '</div>' +
              '<div style="text-align:right;display:flex;flex-direction:column;gap:6px;align-items:flex-end">' +
                (o.jenis_hidang === 'takeaway'
                  ? '<span class="badge serve-badge bungkus">' + esc(i18n.takeaway || 'Takeaway') + '</span>'
                  : '<span class="badge serve-badge sini">' + esc(i18n.dine_in || 'Dine in') + '</span>') +
                '<span class="badge badge-' + esc(o.status_order) + '">' + esc(statusLabel(o.status_order)) + '</span>' +
                (unpaid
                  ? '<span class="badge badge-belum_bayar">' + esc(i18n.unpaid || 'Unpaid') + '</span>'
                  : '') +
              '</div>' +
            '</div>' +
            '<ul class="order-items">' + itemsHtml + '</ul>' +
            '<div class="order-card-footer">' +
              '<div><div class="order-total">' + money(o.total_harga) + '</div>' + sstLine + '</div>' +
              paidBtn +
            '</div>' +
          '</article>'
        );
      }).join('');
    }).join('');
  }

  async function poll() {
    if (busy) return;
    busy = true;
    try {
      const url = pollUrl + '?since_id=' + encodeURIComponent(String(sinceId)) + '&lang=' + encodeURIComponent(lang);
      const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
      if (res.status === 401) {
        window.location.href = '../login.php';
        return;
      }
      const data = await res.json();
      if (!data.ok) return;

      if ((data.new_order_ids || []).length > 0 && sinceId > 0) {
        TableTapSound.configure(data.sound || {});
        const mode = (data.sound && data.sound.mode) || 'until_cleared';
        if (mode === 'until_cleared') {
          TableTapSound.beep();
          setTimeout(function () { TableTapSound.beep(); }, 280);
        } else {
          TableTapSound.startAlarm();
        }
      }
      if (typeof data.max_id === 'number') {
        sinceId = Math.max(sinceId, data.max_id);
      }
      render(data);
    } catch (e) {
      // keep polling
    } finally {
      busy = false;
    }
  }

  root.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-mark-paid]');
    if (!btn) return;
    const orderId = Number(btn.getAttribute('data-mark-paid'));
    if (!orderId) return;
    btn.disabled = true;
    try {
      const res = await fetch(paidUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ order_id: orderId }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');
      await poll();
    } catch (err) {
      alert(err.message || 'Error');
      btn.disabled = false;
    }
  });

  poll();
  setInterval(poll, interval);
})();
