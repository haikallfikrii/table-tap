/**
 * Kasir dashboard — table groups, split bill, silent Bluetooth receipts
 */
(function () {
  const root = document.getElementById('orders-root');
  if (!root) return;

  const pollUrl = root.dataset.pollUrl;
  const paidUrl = root.dataset.paidUrl;
  const confirmUrl = root.dataset.confirmUrl || '';
  const splitUrl = root.dataset.splitUrl || '';
  const pickupUrl = root.dataset.pickupUrl;
  const receiptUrlBase = root.dataset.receiptUrl || '';
  const receiptJsonUrl = root.dataset.receiptJsonUrl || '';
  const sendReceiptUrl = root.dataset.sendReceiptUrl || '';
  const shopName = root.dataset.shopName || 'TableTap';
  const interval = Number(root.dataset.interval) || 3000;
  const lang = root.dataset.lang || 'my';
  const i18n = JSON.parse(root.dataset.i18n || '{}');

  let sinceId = 0;
  let busy = false;
  let latestOrders = [];
  let autoPrint = true;
  const autoKey = 'tt_kasir_autoprint';
  try {
    const saved = localStorage.getItem(autoKey);
    if (saved === '0') autoPrint = false;
    if (saved === '1') autoPrint = true;
  } catch (e) { /* ignore */ }

  const splitOverlay = document.getElementById('split-overlay');
  const splitSheet = document.getElementById('split-sheet');
  const splitBody = document.getElementById('split-body');
  const splitTitle = document.getElementById('split-title');
  const splitGuest = document.getElementById('split-guest');
  const splitTotal = document.getElementById('split-total');
  let splitOrderId = 0;
  let splitSstRate = 0;
  let splitSstEnabled = false;

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
    return (i18n.table_n || 'Table %s').replace('%s', String(num));
  }

  function receiptLabels() {
    return {
      receipt: i18n.receipt || 'Receipt',
      table: i18n.table || 'Meja',
      paid: i18n.paid || 'Paid',
      guest: i18n.guest_name || 'Guest',
      dine_in: i18n.dine_in || 'Dine in',
      takeaway: i18n.takeaway || 'Takeaway',
      subtotal: i18n.subtotal || 'Subtotal',
      total: i18n.total || 'Total',
      thank_you: i18n.thank_you || 'Terima kasih!',
      split_from: i18n.split_from || 'Split from',
      test_item: i18n.print_test_item || 'Test print OK',
      mode: 'receipt',
    };
  }

  function openBrowserReceipt(orderId, auto) {
    if (!receiptUrlBase) return;
    const url = receiptUrlBase + '?order=' + encodeURIComponent(String(orderId)) + (auto ? '&print=1' : '');
    window.open(url, 'receipt_' + orderId, 'width=420,height=720');
  }

  async function fetchReceipt(orderId) {
    if (!receiptJsonUrl) return null;
    const url = receiptJsonUrl + '?order=' + encodeURIComponent(String(orderId)) + '&lang=' + encodeURIComponent(lang);
    const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Receipt failed');
    return data.receipt;
  }

  async function silentPrintReceipt(receiptOrId) {
    if (!window.TableTapPrint || !TableTapPrint.isConnected()) return false;
    let receipt = receiptOrId;
    if (typeof receiptOrId === 'number') {
      receipt = await fetchReceipt(receiptOrId);
    }
    if (!receipt) return false;
    await TableTapPrint.printReceipt(receipt, receiptLabels());
    return true;
  }

  async function printPaidReceipt(orderId, receiptPayload) {
    try {
      if (autoPrint && window.TableTapPrint && TableTapPrint.isConnected()) {
        await silentPrintReceipt(receiptPayload || orderId);
        updatePrintStatus(i18n.print_test_ok || 'Printed');
        return;
      }
    } catch (err) {
      console.warn('Silent print failed', err);
      updatePrintStatus(i18n.print_failed || 'Print failed');
    }
    // Fallback only when not connected — avoid browser dialog when BT works
    if (!window.TableTapPrint || !TableTapPrint.isConnected()) {
      openBrowserReceipt(orderId, true);
    }
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
      : (order.jenis_hidang === 'delivery'
        ? '<span class="badge badge-serve-bungkus">' + esc(i18n.delivery || 'Delivery') + '</span>'
        : '<span class="badge badge-serve-sini">' + esc(i18n.dine_in || 'Dine in') + '</span>');
    const orderStatus =
      '<span class="badge badge-' + esc(order.status_order) + '">' +
        esc(statusLabel(order.status_order)) +
      '</span>';
    const payStatus = unpaid
      ? '<span class="badge badge-belum_bayar">' + esc(i18n.unpaid || 'Unpaid') + '</span>'
      : '<span class="badge badge-lunas">' + esc(i18n.paid || 'Paid') + '</span>';
    let method = '';
    if (order.payment_method === 'cod') {
      method = '<span class="badge badge-info">' + esc(i18n.pay_cod || 'COD') + '</span>';
    } else if (order.payment_method === 'duitnow') {
      method = '<span class="badge badge-info">' + esc(i18n.pay_duitnow || 'DuitNow') + '</span>';
    }
    return serve + orderStatus + payStatus + method;
  }

  function renderOrderCard(o, newSet) {
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

    const canSplit = unpaid && (o.items || []).length > 1 && o.jenis_hidang !== 'delivery';
    let paidBtn = '';
    if (unpaid && (o.payment_method === 'counter' || !o.payment_method)) {
      paidBtn =
        '<button type="button" class="btn btn-success btn-sm" data-mark-paid="' + o.id + '">' +
          esc(i18n.mark_paid || 'Mark paid') + '</button>';
    }
    const splitBtn = canSplit
      ? '<button type="button" class="btn btn-secondary btn-sm" data-split-bill="' + o.id + '">' +
          esc(i18n.split_bill || 'Split bill') + '</button>'
      : '';

    let payExtra = '';
    if (unpaid && o.payment_method === 'cod') {
      const heldNote = o.payment_proof_status === 'uploaded'
        ? '<span class="order-meta" style="color:var(--warning)">' + esc(i18n.cod_held_waiting || 'Cash held by waiter') + '</span>'
        : '';
      payExtra =
        heldNote +
        '<button type="button" class="btn btn-success btn-sm" data-pay-action="cod_received" data-order="' + o.id + '">' +
          esc(i18n.cod_received || 'Cash received') + '</button>';
    }
    if (unpaid && o.payment_method === 'duitnow') {
      if (o.payment_proof_status === 'uploaded' && o.payment_proof_url) {
        const proofHref = o.payment_proof_url.indexOf('http') === 0
          ? o.payment_proof_url
          : ('../' + o.payment_proof_url);
        payExtra =
          '<a class="btn btn-ghost btn-sm" href="' + esc(proofHref) + '" target="_blank" rel="noopener">' +
            esc(i18n.proof_pending || 'View proof') + '</a>' +
          '<button type="button" class="btn btn-success btn-sm" data-pay-action="confirm" data-order="' + o.id + '">' +
            esc(i18n.confirm_proof || 'Confirm') + '</button>' +
          '<button type="button" class="btn btn-secondary btn-sm" data-pay-action="reject" data-order="' + o.id + '">' +
            esc(i18n.reject_proof || 'Reject') + '</button>';
      } else {
        payExtra = '<span class="order-meta">' + esc(i18n.proof_pending || 'Waiting for proof') + '</span>';
      }
    }

    const deliveryMeta =
      (o.alamat ? '<div class="order-meta">' + esc(i18n.address || 'Address') + ': ' + esc(o.alamat) + '</div>' : '') +
      (o.phone ? '<div class="order-meta">' + esc(i18n.phone || 'Phone') + ': ' + esc(o.phone) + '</div>' : '');

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
            deliveryMeta +
            '<div class="order-card-footer">' +
              '<div><div class="order-total">' + money(o.total_harga) + '</div>' + sstLine + '</div>' +
              '<div class="order-card-actions">' +
                ((paidBtn || splitBtn || payExtra)
                  ? '<div class="order-card-pay">' + paidBtn + splitBtn + payExtra + '</div>'
                  : '') +
                pickupBtns +
                receiptSection(o) +
              '</div>' +
            '</div>' +
          '</article>'
        );
  }

  function render(data) {
    const stats = data.stats || {};
    const elOrders = document.getElementById('stat-orders');
    const elUnpaid = document.getElementById('stat-unpaid');
    const elTotal = document.getElementById('stat-total');
    if (elOrders) elOrders.textContent = String(stats.active_orders || 0);
    if (elUnpaid) elUnpaid.textContent = String(stats.unpaid_orders || 0);
    if (elTotal) elTotal.textContent = money(stats.grand_total || 0);

    let tables = Array.isArray(data.tables) ? data.tables.slice() : [];
    latestOrders = Array.isArray(data.orders) ? data.orders.slice() : [];

    if (tables.length === 0 && latestOrders.length > 0) {
      const map = {};
      latestOrders.forEach(function (o) {
        const key = String(o.nomor_meja);
        if (!map[key]) {
          map[key] = { nomor_meja: o.nomor_meja, orders: [], table_total: 0, has_unpaid: false };
        }
        map[key].orders.push(o);
        if (o.status_bayar === 'belum_bayar') {
          map[key].table_total += Number(o.total_harga) || 0;
          map[key].has_unpaid = true;
        }
      });
      tables = Object.keys(map).map(function (k) { return map[k]; });
    }

    if (tables.length === 0) {
      root.innerHTML = '<div class="empty-state">' + esc(i18n.no_orders || 'No orders') + '</div>';
      return;
    }

    const newSet = new Set(data.new_order_ids || []);

    root.innerHTML = tables.map(function (t) {
      const ordersHtml = (t.orders || []).map(function (o) {
        return renderOrderCard(o, newSet);
      }).join('');
      return (
        '<section class="kasir-table-group' + (t.has_unpaid ? ' has-unpaid' : '') + '">' +
          '<header class="kasir-table-head">' +
            '<div>' +
              '<div class="table-num">' + esc(tableTitle(t.nomor_meja)) + '</div>' +
              '<div class="order-meta">' +
                esc((t.orders || []).length + ' ' + (i18n.ops_orders_n || 'orders')) +
              '</div>' +
            '</div>' +
            (t.has_unpaid
              ? '<div class="kasir-table-due">' +
                  '<div class="order-meta">' + esc(i18n.table_unpaid || 'Table unpaid') + '</div>' +
                  '<div class="order-total">' + money(t.table_total) + '</div>' +
                '</div>'
              : '') +
          '</header>' +
          '<div class="kasir-table-orders">' + ordersHtml + '</div>' +
        '</section>'
      );
    }).join('');
  }

  function openSplitModal(orderId) {
    const order = latestOrders.find(function (o) { return o.id === orderId; });
    if (!order || !splitSheet || !splitBody) return;
    splitOrderId = orderId;
    splitSstRate = Number(order.sst_rate) || 0;
    splitSstEnabled = Number(order.sst_jumlah) > 0 || splitSstRate > 0;
    if (splitTitle) {
      splitTitle.textContent = (i18n.split_bill || 'Split bill') + ' · #' + orderId + ' · ' + tableTitle(order.nomor_meja);
    }
    if (splitGuest) splitGuest.value = order.nama_pelanggan || '';

    splitBody.innerHTML = (order.items || []).map(function (it) {
      const line = (Number(it.harga_saat_order) || 0) * (Number(it.qty) || 0);
      return (
        '<label class="split-item">' +
          '<input type="checkbox" data-split-item="' + it.id + '" data-line="' + line + '">' +
          '<span class="split-item-body">' +
            '<span class="split-item-name">' + esc(it.qty + '× ' + it.nama) + '</span>' +
            (it.catatan ? '<span class="item-note">' + esc(it.catatan) + '</span>' : '') +
          '</span>' +
          '<span class="split-item-amt">' + money(line) + '</span>' +
        '</label>'
      );
    }).join('');

    updateSplitPreview();
    splitOverlay?.classList.add('open');
    splitSheet.classList.add('open');
  }

  function closeSplitModal() {
    splitOverlay?.classList.remove('open');
    splitSheet?.classList.remove('open');
    splitOrderId = 0;
  }

  function updateSplitPreview() {
    if (!splitBody || !splitTotal) return;
    let sub = 0;
    let checked = 0;
    let totalBoxes = 0;
    splitBody.querySelectorAll('[data-split-item]').forEach(function (box) {
      totalBoxes++;
      if (box.checked) {
        checked++;
        sub += Number(box.getAttribute('data-line')) || 0;
      }
    });
    const sst = splitSstEnabled && splitSstRate > 0 ? Math.round(sub * (splitSstRate / 100) * 100) / 100 : 0;
    const total = Math.round((sub + sst) * 100) / 100;
    const invalidAll = checked > 0 && checked === totalBoxes;
    splitTotal.innerHTML =
      '<div>' + esc(i18n.subtotal || 'Subtotal') + ': <strong>' + money(sub) + '</strong></div>' +
      (sst > 0 ? '<div>SST: <strong>' + money(sst) + '</strong></div>' : '') +
      '<div>' + esc(i18n.total || 'Total') + ': <strong>' + money(total) + '</strong></div>' +
      (invalidAll
        ? '<p class="split-hint warn">' + esc(i18n.split_select_partial || 'Leave at least one item unpaid, or mark the whole bill paid.') + '</p>'
        : '<p class="split-hint">' + esc(i18n.split_hint || 'Tick items this guest pays now. Remaining stay unpaid.') + '</p>');

    const confirmBtn = document.getElementById('btn-split-confirm');
    if (confirmBtn) confirmBtn.disabled = checked === 0 || invalidAll;
  }

  async function confirmSplit() {
    if (!splitUrl || !splitOrderId) return;
    const ids = [];
    splitBody?.querySelectorAll('[data-split-item]:checked').forEach(function (box) {
      ids.push(Number(box.getAttribute('data-split-item')));
    });
    const btn = document.getElementById('btn-split-confirm');
    if (btn) btn.disabled = true;
    try {
      const res = await fetch(splitUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          order_id: splitOrderId,
          item_ids: ids,
          nama_pelanggan: (splitGuest && splitGuest.value.trim()) || '',
        }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');
      closeSplitModal();
      await printPaidReceipt(data.paid_order_id, data.receipt);
      await poll();
    } catch (err) {
      alert(err.message || 'Error');
      if (btn) btn.disabled = false;
    }
  }

  function setAutoPrint(on) {
    autoPrint = !!on;
    try { localStorage.setItem(autoKey, autoPrint ? '1' : '0'); } catch (e) { /* ignore */ }
    const toggle = document.getElementById('btn-autoprint');
    if (toggle) {
      toggle.classList.toggle('is-on', autoPrint);
      toggle.setAttribute('aria-pressed', autoPrint ? 'true' : 'false');
      toggle.textContent = autoPrint
        ? (i18n.autoprint_on || 'Auto-print on')
        : (i18n.autoprint_off || 'Auto-print off');
    }
  }

  function updatePrintStatus(extra) {
    const el = document.getElementById('print-status');
    const btn = document.getElementById('btn-connect-printer');
    const testBtn = document.getElementById('btn-test-print');
    const autoBtn = document.getElementById('btn-autoprint');
    if (!window.TableTapPrint) return;

    const supported = TableTapPrint.supported();
    const connected = TableTapPrint.isConnected();

    if (btn) {
      btn.disabled = !supported || TableTapPrint.connecting();
      btn.textContent = connected
        ? (i18n.printer_disconnect || 'Disconnect printer')
        : (i18n.printer_connect || 'Connect printer');
      btn.classList.toggle('is-connected', connected);
    }
    if (testBtn) testBtn.hidden = !connected;
    if (autoBtn) autoBtn.hidden = !supported;

    if (el) {
      if (!supported) {
        el.textContent = i18n.printer_unsupported || 'Bluetooth print needs Chrome/Edge.';
        el.className = 'print-status warn';
      } else if (extra) {
        el.textContent = extra;
        el.className = 'print-status warn';
      } else if (connected) {
        el.textContent = (i18n.printer_connected || 'Printer connected') +
          (autoPrint ? ' · ' + (i18n.autoprint_on || 'Auto-print on') : '');
        el.className = 'print-status ok';
      } else {
        el.textContent = i18n.kasir_printer_hint || i18n.printer_hint ||
          'Connect a Bluetooth thermal printer on this cashier device for silent receipts.';
        el.className = 'print-status';
      }
    }
  }

  function wirePrinterUi() {
    if (!window.TableTapPrint) return;
    setAutoPrint(autoPrint);
    TableTapPrint.onChange(function () { updatePrintStatus(); });
    updatePrintStatus();

    document.getElementById('btn-connect-printer')?.addEventListener('click', async function () {
      const connectBtn = this;
      if (TableTapPrint.isConnected()) {
        TableTapPrint.disconnect();
        updatePrintStatus();
        return;
      }
      connectBtn.disabled = true;
      try {
        await TableTapPrint.connect();
        updatePrintStatus();
      } catch (err) {
        if (err && err.name === 'NotFoundError') {
          updatePrintStatus(i18n.printer_cancelled || 'No printer selected');
        } else {
          updatePrintStatus(i18n.print_failed || 'Could not connect printer');
        }
      } finally {
        updatePrintStatus();
      }
    });

    document.getElementById('btn-test-print')?.addEventListener('click', async function () {
      const testBtn = this;
      testBtn.disabled = true;
      try {
        await TableTapPrint.printTest(receiptLabels());
        updatePrintStatus(i18n.print_test_ok || 'Test printed');
      } catch (err) {
        updatePrintStatus(i18n.print_failed || 'Print failed');
      } finally {
        testBtn.disabled = false;
      }
    });

    document.getElementById('btn-autoprint')?.addEventListener('click', function () {
      setAutoPrint(!autoPrint);
      updatePrintStatus();
    });
  }

  async function poll() {
    if (busy) return;
    busy = true;
    try {
      const url = pollUrl + '?since_id=' + encodeURIComponent(String(sinceId)) + '&lang=' + encodeURIComponent(lang);
      const res = await TableTapLive.fetch(url);
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
      if (!orderId) return;
      try {
        if (window.TableTapPrint && TableTapPrint.isConnected()) {
          await silentPrintReceipt(orderId);
          updatePrintStatus(i18n.print_test_ok || 'Printed');
        } else {
          openBrowserReceipt(orderId, true);
        }
      } catch (err) {
        openBrowserReceipt(orderId, true);
      }
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

    const splitBtn = e.target.closest('[data-split-bill]');
    if (splitBtn) {
      const orderId = Number(splitBtn.getAttribute('data-split-bill'));
      if (orderId) openSplitModal(orderId);
      return;
    }

    const payAct = e.target.closest('[data-pay-action][data-order]');
    if (payAct && confirmUrl) {
      const orderId = Number(payAct.getAttribute('data-order'));
      const action = payAct.getAttribute('data-pay-action');
      if (!orderId || !action) return;
      payAct.disabled = true;
      try {
        const res = await fetch(confirmUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ order_id: orderId, action: action }),
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Failed');
        if (action !== 'reject') {
          await printPaidReceipt(orderId, data.receipt);
        }
        await poll();
      } catch (err) {
        alert(err.message || 'Error');
        payAct.disabled = false;
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
      await printPaidReceipt(orderId, data.receipt);
      await poll();
    } catch (err) {
      alert(err.message || 'Error');
      btn.disabled = false;
    }
  });

  splitBody?.addEventListener('change', function (e) {
    if (e.target && e.target.matches('[data-split-item]')) updateSplitPreview();
  });
  document.getElementById('btn-split-confirm')?.addEventListener('click', confirmSplit);
  document.getElementById('btn-close-split')?.addEventListener('click', closeSplitModal);
  splitOverlay?.addEventListener('click', closeSplitModal);

  wirePrinterUi();
  TableTapLive.loop(poll, interval, { keepAwake: true });
})();
