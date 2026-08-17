/**
 * Kasir dashboard — poll & render
 */
(function () {
  const root = document.getElementById('orders-root');
  if (!root) return;

  const pollUrl = root.dataset.pollUrl;
  const paidUrl = root.dataset.paidUrl;
  const pickupUrl = root.dataset.pickupUrl;
  const receiptUrlBase = root.dataset.receiptUrl || '';
  const sendReceiptUrl = root.dataset.sendReceiptUrl || '';
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

  function openReceipt(orderId, autoPrint) {
    if (!receiptUrlBase) return;
    const url = receiptUrlBase + '?order=' + encodeURIComponent(String(orderId)) + (autoPrint ? '&print=1' : '');
    window.open(url, 'receipt_' + orderId, 'width=420,height=720');
  }

  async function sendReceipt(orderId, email) {
    if (!sendReceiptUrl) return { ok: false };
    const res = await fetch(sendReceiptUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ order_id: orderId, email: email || '' }),
    });
    return res.json();
  }

  function receiptSection(order) {
    const label = esc(i18n.receipt || 'Receipt');
    if (order.status_bayar !== 'lunas') {
      return (
        '<div class="order-card-receipt">' +
          '<div class="order-card-receipt-label">' + label + '</div>' +
          '<div class="order-card-receipt-hint">' + esc(i18n.receipt_after_paid || 'Available after payment') + '</div>' +
        '</div>'
      );
    }
    return (
      '<div class="order-card-receipt">' +
        '<div class="order-card-receipt-label">' + label + '</div>' +
        '<div class="order-card-receipt-btns">' +
          '<button type="button" class="btn btn-secondary btn-sm" data-print-receipt="' + order.id + '">' +
            esc(i18n.print_receipt || 'Print receipt') +
          '</button>' +
          '<button type="button" class="btn btn-secondary btn-sm" data-send-receipt="' + order.id + '"' +
            (order.has_customer_email ? ' data-has-email="1"' : '') + '>' +
            esc(i18n.send_receipt || 'E-receipt') +
          '</button>' +
        '</div>' +
      '</div>'
    );
  }

  function statusBadges(order, unpaid) {
    const serve = order.jenis_hidang === 'takeaway'
      ? '<span class="badge badge-serve-bungkus">' + esc(i18n.takeaway || 'Takeaway') + '</span>'
      : '<span class="badge badge-serve-sini">' + esc(i18n.dine_in || 'Dine in') + '</span>';
    const orderStatus =
      '<span class="badge badge-' + esc(order.status_order) + '">' +
        esc(statusLabel(order.status_order)) +
      '</span>';
    const payStatus = unpaid
      ? '<span class="badge badge-belum_bayar">' + esc(i18n.unpaid || 'Unpaid') + '</span>'
      : '<span class="badge badge-lunas">' + esc(i18n.paid || 'Paid') + '</span>';
    return serve + orderStatus + payStatus;
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
    let orders = Array.isArray(data.orders) ? data.orders.slice() : [];
    if (orders.length === 0 && tables.length > 0) {
      tables.forEach(function (t) {
        (t.orders || []).forEach(function (o) {
          orders.push(Object.assign({}, o, { nomor_meja: o.nomor_meja || t.nomor_meja }));
        });
      });
    }
    orders.sort(function (a, b) { return (b.id || 0) - (a.id || 0); });

    if (orders.length === 0) {
      root.innerHTML = '<div class="empty-state">' + esc(i18n.no_orders || 'No orders') + '</div>';
      return;
    }

    const newSet = new Set(data.new_order_ids || []);

    root.innerHTML = orders.map(function (o) {
        const t = { nomor_meja: o.nomor_meja };
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
          : '';

        let pickupBtns = '';
        if (o.has_ready) {
          pickupBtns =
            '<div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end">' +
              (o.pickup_alert
                ? '<button type="button" class="btn btn-secondary btn-sm" data-pickup="mute" data-order="' + o.id + '">' +
                    esc(i18n.silence_alert || 'Mute alert') + '</button>'
                : '') +
              '<button type="button" class="btn btn-primary btn-sm" data-pickup="collect" data-order="' + o.id + '">' +
                esc(i18n.mark_collected || 'Collected') + '</button>' +
            '</div>';
        }

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
                  (o.sumber_order === 'staf' ? ' · ' + esc(i18n.sourced_staff || 'Staff') : '') +
                '</div>' +
                (o.customer_email
                  ? '<div class="order-customer-email"><a href="mailto:' + encodeURIComponent(o.customer_email) + '">' + esc(o.customer_email) + '</a></div>'
                  : '') +
              '</div>' +
              '<div class="order-card-badges">' + statusBadges(o, unpaid) + '</div>' +
            '</div>' +
            '<ul class="order-items">' + itemsHtml + '</ul>' +
            '<div class="order-card-footer">' +
              '<div><div class="order-total">' + money(o.total_harga) + '</div>' + sstLine + '</div>' +
              '<div class="order-card-actions">' +
                (paidBtn ? '<div class="order-card-pay">' + paidBtn + '</div>' : '') +
                pickupBtns +
                receiptSection(o) +
              '</div>' +
            '</div>' +
          '</article>'
        );
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
    const printBtn = e.target.closest('[data-print-receipt]');
    if (printBtn) {
      const orderId = Number(printBtn.getAttribute('data-print-receipt'));
      if (orderId) openReceipt(orderId, true);
      return;
    }

    const ereceiptBtn = e.target.closest('[data-send-receipt]');
    if (ereceiptBtn && sendReceiptUrl) {
      const orderId = Number(ereceiptBtn.getAttribute('data-send-receipt'));
      if (!orderId) return;
      let email = '';
      if (ereceiptBtn.getAttribute('data-has-email') !== '1') {
        email = window.prompt(i18n.receipt_email_prompt || 'Customer email for e-receipt:') || '';
        if (!email.trim()) return;
      }
      ereceiptBtn.disabled = true;
      try {
        const data = await sendReceipt(orderId, email.trim());
        if (!data.ok) throw new Error(data.error || 'Failed');
        alert((i18n.receipt_sent || 'E-receipt sent') + (data.email_masked ? ' → ' + data.email_masked : ''));
      } catch (err) {
        alert(err.message || 'Error');
        ereceiptBtn.disabled = false;
      }
      return;
    }

    const pickupBtn = e.target.closest('[data-pickup][data-order]');
    if (pickupBtn && pickupUrl) {
      const orderId = Number(pickupBtn.getAttribute('data-order'));
      const action = pickupBtn.getAttribute('data-pickup');
      if (!orderId) return;
      pickupBtn.disabled = true;
      try {
        const res = await fetch(pickupUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ order_id: orderId, action: action }),
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Failed');
        await poll();
      } catch (err) {
        alert(err.message || 'Error');
        pickupBtn.disabled = false;
      }
      return;
    }
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
