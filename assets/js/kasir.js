/**
 * Kasir dashboard — table groups, split bill, silent Bluetooth receipts
 */
(function () {
  const root = document.getElementById('orders-root');
  if (!root) return;

  const pollUrl = root.dataset.pollUrl;
  const paidUrl = root.dataset.paidUrl;
  const cancelUrl = root.dataset.cancelUrl || '';
  const confirmUrl = root.dataset.confirmUrl || '';
  const splitUrl = root.dataset.splitUrl || '';
  const pickupUrl = root.dataset.pickupUrl;
  const receiptUrlBase = root.dataset.receiptUrl || '';
  const receiptJsonUrl = root.dataset.receiptJsonUrl || '';
  const sendReceiptUrl = root.dataset.sendReceiptUrl || '';
  const printBridgeUrl = root.dataset.printBridgeUrl || '';
  const printHubUrl = root.dataset.printHubUrl || '';
  const itemStatusUrl = root.dataset.itemStatusUrl || '';
  const shopName = root.dataset.shopName || 'TableTap';
  const interval = Number(root.dataset.interval) || 3000;
  const lang = root.dataset.lang || 'my';
  const i18n = JSON.parse(root.dataset.i18n || '{}');

  let sinceId = 0;
  let hubSinceId = 0;
  let busy = false;
  let hubBusy = false;
  let hubPrintBusy = false;
  let latestOrders = [];
  let fulfillment = 'waiter';
  let autoPrint = true;
  let printHub = root.dataset.printHub === '1';
  let openDrawer = root.dataset.openDrawer === '1';
  let beepKasir = Math.max(0, Math.min(9, Number(root.dataset.beepKasir) || 0));
  let beepKitchen = Math.max(0, Math.min(9, Number(root.dataset.beepKitchen) || 4));
  let ownerPrintOnPaid = root.dataset.printOnPaid !== '0';
  const autoKey = 'tt_kasir_autoprint';
  const hubPrintedIds = new Set();
  let hubPrimed = false;
  try {
    const saved = localStorage.getItem(autoKey);
    if (saved === '0') autoPrint = false;
    else if (saved === '1') autoPrint = true;
    else autoPrint = ownerPrintOnPaid;
  } catch (e) {
    autoPrint = ownerPrintOnPaid;
  }

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
      beep_count: beepKasir,
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

  /** Wi-Fi Print Bridge: queue the receipt for the LAN printer (Bluetooth still runs too). */
  async function queueBridgeReceipt(orderId) {
    if (!printBridgeUrl || !orderId) return false;
    try {
      const res = await fetch(printBridgeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ order_id: orderId }),
      });
      const data = await res.json();
      return !!data.ok;
    } catch (e) {
      return false;
    }
  }

  async function silentPrintReceipt(receiptOrId) {
    if (!window.TableTapPrint) return false;
    let receipt = receiptOrId;
    if (typeof receiptOrId === 'number') {
      receipt = await fetchReceipt(receiptOrId);
    }
    if (!receipt) return false;
    await TableTapPrint.printReceipt(receipt, receiptLabels());
    return true;
  }

  /**
   * Print via Bluetooth thermal (same path as kitchen) — never open OS print dialog first.
   * On click: reconnect saved printer, or open BT picker once, then silent ESC/POS print.
   */
  async function printPaidReceipt(orderId, receiptPayload, opts) {
    opts = opts || {};
    const interactive = opts.interactive !== false;

    if (!window.TableTapPrint || !TableTapPrint.supported()) {
      updatePrintStatus(i18n.printer_unsupported || 'Bluetooth print needs Chrome/Edge.');
      return false;
    }

    try {
      await TableTapPrint.ensureConnected({ interactive: interactive });
      await silentPrintReceipt(receiptPayload || orderId);
      if (openDrawer && typeof TableTapPrint.openCashDrawer === 'function') {
        try {
          await TableTapPrint.openCashDrawer();
        } catch (drawerErr) {
          console.warn('Cash drawer kick failed', drawerErr);
        }
      }
      updatePrintStatus(i18n.print_test_ok || 'Printed');
      return true;
    } catch (err) {
      console.warn('Silent print failed', err);
      const name = err && err.name;
      const msg = String((err && err.message) || '');
      if (name === 'NotFoundError') {
        updatePrintStatus(i18n.printer_cancelled || 'No printer selected');
      } else if (msg === 'unsupported') {
        updatePrintStatus(i18n.printer_unsupported || 'Bluetooth print not supported');
        } else if (msg === 'not_connected' || msg === 'no_saved') {
        updatePrintStatus(i18n.kasir_print_need_bt || i18n.kasir_printer_hint || 'Connect printer first');
      } else {
        updatePrintStatus(i18n.print_failed || 'Print failed');
      }
      return false;
    }
  }

  function serveLabel(jenis) {
    if (jenis === 'takeaway') return i18n.takeaway || 'Takeaway';
    if (jenis === 'delivery') return i18n.delivery || 'Delivery';
    return i18n.dine_in || 'Dine in';
  }

  function kitchenLabels() {
    return {
      dine_in: i18n.dine_in || 'Dine in',
      takeaway: i18n.takeaway || 'Takeaway',
      kitchen_ticket: i18n.kitchen_ticket || 'KITCHEN TICKET',
      beep_count: beepKitchen,
    };
  }

  /** One tear-off slip per station per order — never merge stations into one print. */
  function groupHubTickets(items, newIds) {
    const idSet = new Set(newIds || []);
    const byTicket = {};
    items.forEach(function (it) {
      if (!idSet.has(it.id) || hubPrintedIds.has(it.id)) return;
      if (it.status_item !== 'menunggu') return;
      // Key by station only (dapur / western / minuman) — separate physical prints.
      const grp = String(it.station_kod || it.ticket_group || 'default');
      const key = it.order_id + ':' + grp;
      const label = it.ticket_label || grp;
      if (!byTicket[key]) {
        byTicket[key] = {
          shopName: shopName,
          stationName: label,
          table: it.nomor_meja,
          orderId: it.order_id,
          serveLabel: serveLabel(it.jenis_hidang),
          guest: it.nama_pelanggan || '',
          time: it.waktu_order || '',
          stationKod: grp,
          items: [],
          itemIds: [],
        };
      }
      byTicket[key].items.push({
        qty: it.qty,
        nama: it.nama,
        catatan: it.catatan || '',
      });
      byTicket[key].itemIds.push(it.id);
    });
    const order = { dapur: 0, western: 1, minuman: 2 };
    return Object.keys(byTicket).map(function (k) { return byTicket[k]; }).sort(function (a, b) {
      const ao = order[a.stationKod] != null ? order[a.stationKod] : 50;
      const bo = order[b.stationKod] != null ? order[b.stationKod] : 50;
      if (ao !== bo) return ao - bo;
      return a.orderId - b.orderId;
    });
  }

  async function autoPrintHubTickets(items, newIds) {
    // Hub prints whenever enabled — not gated on receipt auto-print toggle.
    if (!printHub || !window.TableTapPrint || !TableTapPrint.supported()) return;
    if (!hubPrimed || hubPrintBusy) return;
    const tickets = groupHubTickets(items, newIds);
    if (!tickets.length) return;

    hubPrintBusy = true;
    try {
      try {
        await TableTapPrint.ensureConnected({ interactive: false });
      } catch (e) {
        return;
      }
      for (let i = 0; i < tickets.length; i++) {
        const t = tickets[i];
        try {
          // Separate ESC/POS job + cut per station (never one combined slip).
          await TableTapPrint.printKitchenTicket(t, kitchenLabels());
          t.itemIds.forEach(function (id) { hubPrintedIds.add(id); });
          if (i < tickets.length - 1) {
            await new Promise(function (r) { setTimeout(r, 700); });
          }
        } catch (err) {
          console.warn('Hub station print failed', err);
          updatePrintStatus(i18n.print_failed || 'Print failed');
          break;
        }
      }
    } finally {
      hubPrintBusy = false;
    }
  }

  async function pollPrintHub() {
    if (!printHub || !printHubUrl || hubBusy) return;
    hubBusy = true;
    try {
      const url = printHubUrl +
        '?since_id=' + encodeURIComponent(String(hubSinceId)) +
        '&lang=' + encodeURIComponent(lang);
      const res = await TableTapLive.fetch(url);
      if (res.status === 401) return;
      const data = await res.json();
      if (!data.ok) return;
      if (data.enabled === false) {
        printHub = false;
        return;
      }
      applyPrinterSettings(data.printer);
      const newIds = data.new_item_ids || [];
      if (hubSinceId > 0 && newIds.length) {
        await autoPrintHubTickets(data.items || [], newIds);
      } else if (hubSinceId === 0) {
        // First poll: mark existing waiting items as already seen (no reprint flood).
        (data.items || []).forEach(function (it) {
          if (it.status_item === 'menunggu') hubPrintedIds.add(it.id);
        });
        hubPrimed = true;
      }
      if (typeof data.max_id === 'number') {
        hubSinceId = Math.max(hubSinceId, data.max_id);
      }
      if (!hubPrimed) hubPrimed = true;
    } catch (e) {
      // keep polling
    } finally {
      hubBusy = false;
    }
  }

  async function cancelOrder(orderId) {
    if (!cancelUrl) return;
    if (!confirm(i18n.cancel_order_confirm || 'Cancel this order?')) return;
    const res = await fetch(cancelUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ order_id: orderId }),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || i18n.cancel_order_failed || 'Failed');
    await poll();
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

  function deliveryPayLabel(state) {
    const map = {
      paid: i18n.paid || 'Paid',
      duitnow_wait_proof: i18n.delivery_pay_wait_proof || 'Awaiting proof',
      duitnow_review: i18n.delivery_pay_proof_sent || 'Proof sent',
      cod_pending: i18n.delivery_pay_cod || 'COD pending',
      cod_held: i18n.cod_held_waiting || 'COD held',
      counter_pending: i18n.delivery_pay_counter || 'Counter unpaid',
    };
    return map[state] || state;
  }

  function deliveryPayBadgeClass(state) {
    if (state === 'paid') return 'badge-lunas';
    if (state === 'duitnow_review' || state === 'cod_held') return 'badge-delivery-action';
    if (state === 'duitnow_wait_proof') return 'badge-delivery-wait';
    return 'badge-delivery-pending';
  }

  let lastDeliveryStates = {};

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
    const payStatus = order.jenis_hidang === 'delivery'
      ? (function () {
          const st = order.payment_state || (unpaid ? 'counter_pending' : 'paid');
          return '<span class="badge ' + deliveryPayBadgeClass(st) + '">' + esc(deliveryPayLabel(st)) + '</span>';
        })()
      : (unpaid
        ? '<span class="badge badge-belum_bayar">' + esc(i18n.unpaid || 'Unpaid') + '</span>'
        : '<span class="badge badge-lunas">' + esc(i18n.paid || 'Paid') + '</span>');
    let method = '';
    if (order.payment_method === 'cod') {
      method = '<span class="badge badge-info">' + esc(i18n.pay_cod || 'COD') + '</span>';
    } else if (order.payment_method === 'duitnow') {
      method = '<span class="badge badge-info">' + esc(i18n.pay_duitnow || 'DuitNow') + '</span>';
    }
    return serve + orderStatus + payStatus + method;
  }

  function itemPrepActions(it) {
    const st = String(it.status_item || '');
    if (st === 'dihantar') {
      return '';
    }
    let btns = '';
    if (st === 'menunggu') {
      btns +=
        '<button type="button" class="btn btn-secondary btn-xs" data-item-status="sedang_dimasak" data-item-id="' + it.id + '">' +
          esc(i18n.mark_cooking || 'Start') +
        '</button>';
      btns +=
        '<button type="button" class="btn btn-success btn-xs" data-item-status="siap" data-item-id="' + it.id + '">' +
          esc(fulfillment === 'self_pickup'
            ? (i18n.mark_ready_self || i18n.mark_ready || 'Ready')
            : (i18n.mark_ready || i18n.mark_done || 'Ready')) +
        '</button>';
    } else if (st === 'sedang_dimasak') {
      btns +=
        '<button type="button" class="btn btn-success btn-xs" data-item-status="siap" data-item-id="' + it.id + '">' +
          esc(fulfillment === 'self_pickup'
            ? (i18n.mark_ready_self || i18n.mark_ready || 'Ready')
            : (i18n.mark_ready || i18n.mark_done || 'Ready')) +
        '</button>';
    } else if (st === 'siap' || st === 'diambil') {
      btns +=
        '<button type="button" class="btn btn-primary btn-xs" data-item-status="dihantar" data-item-id="' + it.id + '">' +
          esc(i18n.mark_collected || 'Collected') +
        '</button>';
    }
    return btns ? '<div class="kasir-item-actions">' + btns + '</div>' : '';
  }

  function renderOrderCard(o, newSet) {
    const unpaid = o.status_bayar === 'belum_bayar';
    const isNew = newSet.has(o.id);
    const itemsHtml = (o.items || []).map((it) => {
      const note = it.catatan
        ? '<span class="item-note">' + esc(i18n.notes || 'Notes') + ': ' + esc(it.catatan) + '</span>'
        : '';
      const station = it.station_label
        ? '<span class="kasir-item-station">' + esc(it.station_label) + '</span>'
        : '';
      const st = it.status_item
        ? '<span class="kasir-item-status status-' + esc(it.status_item) + '">' +
            esc(i18n['status_item_' + it.status_item] || it.status_item) +
          '</span>'
        : '';
      return (
        '<li class="kasir-item-row">' +
          '<div class="kasir-item-main">' +
            '<div><span class="qty">' + it.qty + '×</span> ' + esc(it.nama) + note + '</div>' +
            '<div class="kasir-item-meta">' + station + st + '</div>' +
            itemPrepActions(it) +
          '</div>' +
          '<div class="kasir-item-price">' + money(it.harga_saat_order * it.qty) + '</div>' +
        '</li>'
      );
    }).join('');

    const canSplit = unpaid && (o.items || []).length > 1 && o.jenis_hidang !== 'delivery';
    let paidBtn = '';
    if (unpaid && o.jenis_hidang !== 'delivery' && (o.payment_method === 'counter' || !o.payment_method)) {
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
        payExtra =
          '<span class="order-meta">' + esc(i18n.proof_pending || 'Waiting for proof') + '</span>' +
          '<button type="button" class="btn btn-success btn-sm" data-pay-action="confirm" data-order="' + o.id + '">' +
            esc(i18n.mark_paid_manual || i18n.mark_paid || 'Mark paid') + '</button>';
      }
    }
    if (unpaid && o.jenis_hidang === 'delivery' && (o.payment_method === 'counter' || !o.payment_method)) {
      payExtra =
        '<button type="button" class="btn btn-success btn-sm" data-mark-paid="' + o.id + '">' +
          esc(i18n.mark_paid || 'Mark paid') + '</button>';
    }

    const deliveryMeta =
      (o.alamat ? '<div class="order-meta">' + esc(i18n.address || 'Address') + ': ' + esc(o.alamat) + '</div>' : '') +
      (o.phone
        ? '<div class="order-meta">' + esc(i18n.phone || 'Phone') + ': <a href="tel:' + esc(String(o.phone).replace(/[^\d+]/g, '')) + '">' + esc(o.phone) + '</a></div>'
        : '');

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
      '<article class="order-card' +
        (unpaid && o.jenis_hidang !== 'delivery' ? ' unpaid' : '') +
        (o.jenis_hidang === 'delivery' && o.needs_payment_attention ? ' delivery-needs-action' : '') +
        (isNew ? ' new-flash' : '') +
        '" data-order-id="' + o.id + '">' +
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
                '<button type="button" class="btn btn-ghost btn-sm" data-cancel-order="' + o.id + '" style="color:var(--danger);margin-top:6px">' +
                  esc(i18n.cancel_order || 'Cancel order') +
                '</button>' +
              '</div>' +
            '</div>' +
          '</article>'
        );
  }

  function render(data) {
    if (data.fulfillment) fulfillment = data.fulfillment;
    const stats = data.stats || {};
    const elOrders = document.getElementById('stat-orders');
    const elUnpaid = document.getElementById('stat-unpaid');
    const elTotal = document.getElementById('stat-total');
    const elDelivery = document.getElementById('stat-delivery');
    const elDeliverySub = document.getElementById('stat-delivery-sub');
    if (elOrders) elOrders.textContent = String(stats.active_orders || 0);
    if (elUnpaid) elUnpaid.textContent = String(stats.unpaid_orders || 0);
    if (elTotal) elTotal.textContent = money(stats.grand_total || 0);
    if (elDelivery) elDelivery.textContent = String(stats.delivery_active || 0);
    if (elDeliverySub) {
      const need = Number(stats.delivery_needs_action || 0);
      elDeliverySub.textContent = need > 0
        ? (need + ' ' + (i18n.delivery_needs_action || 'needs action'))
        : '';
    }
    document.getElementById('stat-delivery-card')?.classList.toggle('has-alert', Number(stats.delivery_needs_action || 0) > 0);

    let tables = Array.isArray(data.tables) ? data.tables.slice() : [];
    const deliveryOrders = Array.isArray(data.delivery_orders) ? data.delivery_orders.slice() : [];
    latestOrders = Array.isArray(data.orders) ? data.orders.slice() : [];

    if (tables.length === 0 && latestOrders.length > 0) {
      const map = {};
      latestOrders.forEach(function (o) {
        if (o.jenis_hidang === 'delivery') return;
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

    const newSet = new Set(data.new_order_ids || []);
    let html = '';

    if (deliveryOrders.length > 0) {
      const deliveryHtml = deliveryOrders.map(function (o) {
        return renderOrderCard(o, newSet);
      }).join('');
      html +=
        '<section class="kasir-delivery-panel" id="delivery">' +
          '<header class="kasir-delivery-head">' +
            '<div>' +
              '<h2 class="kasir-section-title">' + esc(i18n.delivery || 'Delivery') + '</h2>' +
              '<p class="order-meta">' + esc(i18n.delivery_needs_action || 'Payment tracking') + '</p>' +
            '</div>' +
            (Number(stats.delivery_needs_action || 0) > 0
              ? '<span class="kasir-delivery-alert">' + Number(stats.delivery_needs_action) + ' ' +
                  esc(i18n.delivery_needs_action || 'needs action') + '</span>'
              : '') +
          '</header>' +
          '<div class="kasir-delivery-orders">' + deliveryHtml + '</div>' +
        '</section>';
    }

    if (tables.length === 0 && deliveryOrders.length === 0) {
      root.innerHTML = '<div class="empty-state">' + esc(i18n.no_orders || 'No orders') + '</div>';
      return;
    }

    if (tables.length > 0) {
      html += '<div class="table-grid kasir-tables-panel">' + tables.map(function (t) {
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
                    '<div class="order-meta">' + esc(i18n.table_unpaid_only || i18n.table_unpaid || 'Table unpaid') + '</div>' +
                    '<div class="order-total">' + money(t.table_total) + '</div>' +
                  '</div>'
                : '') +
            '</header>' +
            '<div class="kasir-table-orders">' + ordersHtml + '</div>' +
          '</section>'
        );
      }).join('') + '</div>';
    }

    root.innerHTML = html;

    deliveryOrders.forEach(function (o) {
      const prev = lastDeliveryStates[o.id];
      if (sinceId > 0 && o.payment_state === 'duitnow_review' && prev !== 'duitnow_review' && window.TableTapSound) {
        TableTapSound.configure(data.sound || {});
        TableTapSound.beep();
        setTimeout(function () { TableTapSound.beep(); }, 320);
      }
      lastDeliveryStates[o.id] = o.payment_state;
    });
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
      const maxQty = Math.max(1, Number(it.qty) || 1);
      const unit = Number(it.harga_saat_order) || 0;
      return (
        '<div class="split-item" data-split-row="' + it.id + '" data-unit="' + unit + '" data-max="' + maxQty + '">' +
          '<div class="split-item-body">' +
            '<span class="split-item-name">' + esc(it.nama) + '</span>' +
            (it.catatan ? '<span class="item-note">' + esc(it.catatan) + '</span>' : '') +
            '<span class="split-item-meta">' + esc(money(unit) + ' × ' + maxQty) + '</span>' +
          '</div>' +
          '<div class="split-qty" role="group" aria-label="' + esc(it.nama) + '">' +
            '<button type="button" class="split-qty-btn" data-split-dec="' + it.id + '" aria-label="-">−</button>' +
            '<span class="split-qty-val" data-split-qty="' + it.id + '">0</span>' +
            '<button type="button" class="split-qty-btn" data-split-inc="' + it.id + '" aria-label="+">+</button>' +
          '</div>' +
          '<span class="split-item-amt" data-split-amt="' + it.id + '">' + money(0) + '</span>' +
        '</div>'
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

  function getSplitSelections() {
    const rows = [];
    let selectedUnits = 0;
    let totalUnits = 0;
    let sub = 0;
    splitBody?.querySelectorAll('[data-split-row]').forEach(function (row) {
      const id = Number(row.getAttribute('data-split-row'));
      const max = Number(row.getAttribute('data-max')) || 1;
      const unit = Number(row.getAttribute('data-unit')) || 0;
      const qtyEl = row.querySelector('[data-split-qty]');
      const qty = Math.max(0, Math.min(max, Number(qtyEl && qtyEl.textContent) || 0));
      totalUnits += max;
      if (qty > 0) {
        selectedUnits += qty;
        sub += unit * qty;
        rows.push({ id: id, qty: qty });
      }
      const amtEl = row.querySelector('[data-split-amt]');
      if (amtEl) amtEl.textContent = money(unit * qty);
      row.classList.toggle('is-selected', qty > 0);
    });
    return { rows: rows, selectedUnits: selectedUnits, totalUnits: totalUnits, sub: sub };
  }

  function bumpSplitQty(itemId, delta) {
    if (!splitBody) return;
    const row = splitBody.querySelector('[data-split-row="' + itemId + '"]');
    if (!row) return;
    const max = Number(row.getAttribute('data-max')) || 1;
    const qtyEl = row.querySelector('[data-split-qty]');
    if (!qtyEl) return;
    const next = Math.max(0, Math.min(max, (Number(qtyEl.textContent) || 0) + delta));
    qtyEl.textContent = String(next);
    updateSplitPreview();
  }

  function updateSplitPreview() {
    if (!splitBody || !splitTotal) return;
    const sel = getSplitSelections();
    const sub = Math.round(sel.sub * 100) / 100;
    const sst = splitSstEnabled && splitSstRate > 0 ? Math.round(sub * (splitSstRate / 100) * 100) / 100 : 0;
    const total = Math.round((sub + sst) * 100) / 100;
    const invalidAll = sel.selectedUnits > 0 && sel.selectedUnits >= sel.totalUnits;
    splitTotal.innerHTML =
      '<div>' + esc(i18n.subtotal || 'Subtotal') + ': <strong>' + money(sub) + '</strong></div>' +
      (sst > 0 ? '<div>SST: <strong>' + money(sst) + '</strong></div>' : '') +
      '<div>' + esc(i18n.total || 'Total') + ': <strong>' + money(total) + '</strong></div>' +
      (invalidAll
        ? '<p class="split-hint warn">' + esc(i18n.split_select_partial || 'Leave at least one item unpaid, or mark the whole bill paid.') + '</p>'
        : '<p class="split-hint">' + esc(i18n.split_hint || 'Choose how many of each item this guest pays now. Remaining stay unpaid.') + '</p>');

    const confirmBtn = document.getElementById('btn-split-confirm');
    if (confirmBtn) confirmBtn.disabled = sel.selectedUnits === 0 || invalidAll;
  }

  async function confirmSplit() {
    if (!splitUrl || !splitOrderId) return;
    const sel = getSplitSelections();
    if (!sel.rows.length) return;
    const btn = document.getElementById('btn-split-confirm');
    if (btn) btn.disabled = true;
    try {
      const res = await fetch(splitUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          order_id: splitOrderId,
          items: sel.rows,
          nama_pelanggan: (splitGuest && splitGuest.value.trim()) || '',
        }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');
      closeSplitModal();
      if (autoPrint) {
        await printPaidReceipt(data.paid_order_id, data.receipt, { interactive: false });
      }
      await poll();
    } catch (err) {
      alert(err.message || 'Error');
      if (btn) btn.disabled = false;
    }
  }

  function setAutoPrint(on, persist) {
    autoPrint = !!on;
    if (persist !== false) {
      try { localStorage.setItem(autoKey, autoPrint ? '1' : '0'); } catch (e) { /* ignore */ }
    }
    const toggle = document.getElementById('btn-autoprint');
    if (toggle) {
      toggle.classList.toggle('is-on', autoPrint);
      toggle.setAttribute('aria-pressed', autoPrint ? 'true' : 'false');
      toggle.textContent = autoPrint
        ? (i18n.autoprint_on || 'Auto-print on')
        : (i18n.autoprint_off || 'Auto-print off');
    }
  }

  function applyPrinterSettings(printer) {
    if (!printer) return;
    if (printer.beep_kasir != null) {
      beepKasir = Math.max(0, Math.min(9, Number(printer.beep_kasir) || 0));
    }
    if (printer.beep_kitchen != null) {
      beepKitchen = Math.max(0, Math.min(9, Number(printer.beep_kitchen) || 0));
    }
    if (typeof printer.kasir_print_hub === 'boolean') {
      printHub = printer.kasir_print_hub;
    }
    if (typeof printer.kasir_open_drawer === 'boolean') {
      openDrawer = printer.kasir_open_drawer;
    }
    if (typeof printer.kasir_print_on_paid === 'boolean') {
      ownerPrintOnPaid = printer.kasir_print_on_paid;
      // First visit (no local override yet): follow owner default
      try {
        if (localStorage.getItem(autoKey) === null) {
          setAutoPrint(ownerPrintOnPaid, false);
        }
      } catch (e) {
        setAutoPrint(ownerPrintOnPaid, false);
      }
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
          (autoPrint ? ' · ' + (i18n.autoprint_on || 'Auto-print on') : '') +
          (printHub ? ' · ' + (i18n.kasir_print_hub_on || 'Station hub ON') : '');
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

    function tryReconnect(reason) {
      if (!TableTapPrint.supported() || TableTapPrint.isConnected() || TableTapPrint.connecting()) {
        return;
      }
      const returning = /[?&]ordered=/.test(window.location.search) || reason === 'focus';
      const attempts = returning ? 5 : 3;
      updatePrintStatus(i18n.printer_reconnecting || 'Menyambung semula printer…');
      TableTapPrint.reconnectWithRetry(attempts, 800).then(function () {
        updatePrintStatus();
      }).catch(function () {
        updatePrintStatus();
      });
    }

    // Restore previous BT grant without picker (retry — printers often need a moment after tab return)
    tryReconnect('boot');

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') tryReconnect('focus');
    });
    window.addEventListener('pageshow', function () { tryReconnect('focus'); });
    window.addEventListener('focus', function () { tryReconnect('focus'); });

    document.querySelectorAll('a[data-staff-order-popup]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        // Keep this kasir tab alive so the Bluetooth GATT session stays connected.
        e.preventDefault();
        const w = window.open(a.href, 'tt_staff_order');
        if (!w) {
          window.location.assign(a.href);
        }
      });
    });

    document.getElementById('btn-connect-printer')?.addEventListener('click', async function () {
      const connectBtn = this;
      if (TableTapPrint.isConnected()) {
        TableTapPrint.disconnect();
        updatePrintStatus();
        return;
      }
      connectBtn.disabled = true;
      try {
        await TableTapPrint.ensureConnected({ interactive: true });
        updatePrintStatus(i18n.printer_connected || 'Printer connected');
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
        await TableTapPrint.ensureConnected({ interactive: true });
        await TableTapPrint.printTest(receiptLabels());
        updatePrintStatus(i18n.print_test_ok || 'Test printed');
      } catch (err) {
        updatePrintStatus(i18n.print_failed || 'Print failed');
      } finally {
        testBtn.disabled = false;
        updatePrintStatus();
      }
    });

    document.getElementById('btn-autoprint')?.addEventListener('click', function () {
      setAutoPrint(!autoPrint, true);
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
      if ((data.new_delivery_ids || []).length > 0 && sinceId > 0) {
        TableTapSound.configure(data.sound || {});
        TableTapSound.beep();
        setTimeout(function () { TableTapSound.beep(); }, 180);
        setTimeout(function () { TableTapSound.beep(); }, 420);
      }
      if (typeof data.max_id === 'number') {
        sinceId = Math.max(sinceId, data.max_id);
      }
      applyPrinterSettings(data.printer);
      render(data);
      if (printHub) {
        pollPrintHub();
      }
    } catch (e) {
      // keep polling
    } finally {
      busy = false;
    }
  }

  root.addEventListener('click', async (e) => {
    const itemStatusBtn = e.target.closest('[data-item-status][data-item-id]');
    if (itemStatusBtn && itemStatusUrl) {
      const itemId = Number(itemStatusBtn.getAttribute('data-item-id'));
      const status = itemStatusBtn.getAttribute('data-item-status');
      if (!itemId || !status) return;
      itemStatusBtn.disabled = true;
      try {
        const res = await fetch(itemStatusUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ item_id: itemId, status: status }),
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Failed');
        await poll();
      } catch (err) {
        alert(err.message || 'Error');
        itemStatusBtn.disabled = false;
      }
      return;
    }

    const cancelBtn = e.target.closest('[data-cancel-order]');
    if (cancelBtn) {
      const orderId = Number(cancelBtn.getAttribute('data-cancel-order'));
      if (!orderId) return;
      cancelBtn.disabled = true;
      try {
        await cancelOrder(orderId);
      } catch (err) {
        alert(err.message || 'Error');
        cancelBtn.disabled = false;
      }
      return;
    }

    const printBtn = e.target.closest('[data-print-receipt]');
    if (printBtn) {
      const orderId = Number(printBtn.getAttribute('data-print-receipt'));
      if (!orderId) return;
      printBtn.disabled = true;
      try {
        const queued = await queueBridgeReceipt(orderId);
        if (queued && (!window.TableTapPrint || !TableTapPrint.supported())) {
          updatePrintStatus(i18n.print_bridge_queued || 'Sent to network printer');
        } else {
          await printPaidReceipt(orderId, null, { interactive: true });
        }
      } finally {
        printBtn.disabled = false;
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
          if (autoPrint) {
            await printPaidReceipt(orderId, data.receipt, { interactive: false });
          }
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
      if (autoPrint) {
        await printPaidReceipt(orderId, data.receipt, { interactive: false });
      }
      await poll();
    } catch (err) {
      alert(err.message || 'Error');
      btn.disabled = false;
    }
  });

  splitBody?.addEventListener('change', function (e) {
    if (e.target && e.target.matches('[data-split-item]')) updateSplitPreview();
  });
  splitBody?.addEventListener('click', function (e) {
    const dec = e.target.closest('[data-split-dec]');
    if (dec) {
      e.preventDefault();
      bumpSplitQty(Number(dec.getAttribute('data-split-dec')), -1);
      return;
    }
    const inc = e.target.closest('[data-split-inc]');
    if (inc) {
      e.preventDefault();
      bumpSplitQty(Number(inc.getAttribute('data-split-inc')), 1);
    }
  });
  document.getElementById('btn-split-confirm')?.addEventListener('click', confirmSplit);
  document.getElementById('btn-close-split')?.addEventListener('click', closeSplitModal);
  splitOverlay?.addEventListener('click', closeSplitModal);

  wirePrinterUi();
  TableTapLive.loop(poll, interval, { keepAwake: true });
})();
