/**
 * Kitchen / drinks dashboard polling + looping alert until items are processed
 * + Bluetooth thermal auto-print for new tickets (silent, no print dialog)
 */
(function () {
  const root = document.getElementById('kitchen-root');
  if (!root) return;

  const pollUrl = root.dataset.pollUrl;
  const updateUrl = root.dataset.updateUrl;
  const kategori = root.dataset.kategori || 'makanan';
  const stationId = root.dataset.stationId || '';
  const stationName = root.dataset.stationName || '';
  const shopName = root.dataset.shopName || 'TableTap';
  const interval = Number(root.dataset.interval) || 3000;
  const lang = root.dataset.lang || 'my';
  const fulfillment = root.dataset.fulfillment || 'waiter';
  const i18n = JSON.parse(root.dataset.i18n || '{}');

  let sinceId = 0;
  let busy = false;
  let waveDone = false;
  let lastPending = 0;
  let primed = false;
  let printBusy = false;
  const printedIds = new Set();

  const autoKey = 'tt_kitchen_autoprint_' + (stationId || kategori);
  let autoPrint = true;
  try {
    const saved = localStorage.getItem(autoKey);
    if (saved === '0') autoPrint = false;
    if (saved === '1') autoPrint = true;
  } catch (e) { /* ignore */ }

  TableTapSound.bindButton(document.getElementById('btn-enable-sound'), {
    on: i18n.sound_on || 'Sound on',
  });

  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function tableTitle(num) {
    if (String(num) === 'Delivery') return i18n.delivery || 'Delivery';
    return (i18n.table_n || 'Table %s').replace('%s', String(num));
  }

  function serveLabel(jenis) {
    if (jenis === 'takeaway') return i18n.takeaway || 'Takeaway';
    if (jenis === 'delivery') return i18n.delivery || 'Delivery';
    return i18n.dine_in || 'Dine in';
  }

  function applyAlarm(pending, newIds, sound) {
    TableTapSound.configure(sound || {});
    const count = Number(pending) || 0;
    if (count <= 0) {
      TableTapSound.stopAlarm();
      waveDone = false;
      lastPending = 0;
      return;
    }
    const mode = (sound && sound.mode) || 'until_cleared';
    if (mode === 'until_cleared') {
      TableTapSound.startAlarm();
      lastPending = count;
      return;
    }
    const rising = count > lastPending || (newIds && newIds.length > 0);
    lastPending = count;
    if (rising) waveDone = false;
    if (!waveDone) {
      TableTapSound.startAlarm(function () { waveDone = true; });
    }
  }

  function render(items, newIds) {
    const newSet = new Set(newIds || []);
    if (!items.length) {
      root.innerHTML = '<div class="empty-state">' + esc(i18n.no_kitchen_items || 'Empty') + '</div>';
      return;
    }

    root.innerHTML = items.map((it) => {
      const note = it.catatan
        ? '<div class="item-note">' + esc(i18n.notes || 'Notes') + ': ' + esc(it.catatan) + '</div>'
        : '';

      let actions = '';
      if (it.status_item === 'siap' && fulfillment === 'self_pickup') {
        actions =
          '<button type="button" class="btn btn-success" style="width:100%" data-status="dihantar" data-id="' + it.id + '">' +
            esc(i18n.mark_collected || 'Collected') +
          '</button>';
      } else if (it.status_item === 'menunggu') {
        actions =
          '<button type="button" class="btn btn-secondary btn-sm" data-status="sedang_dimasak" data-id="' + it.id + '">' +
            esc(i18n.mark_cooking || 'Start') +
          '</button> ' +
          '<button type="button" class="btn btn-success" data-status="siap" data-id="' + it.id + '">' +
            esc(i18n.mark_ready || i18n.mark_done || 'Ready') +
          '</button>';
      } else {
        actions =
          '<button type="button" class="btn btn-success" style="width:100%" data-status="siap" data-id="' + it.id + '">' +
            esc(i18n.mark_ready || i18n.mark_done || 'Ready') +
          '</button>';
      }

      const hidang = serveLabel(it.jenis_hidang);
      const hidangClass = it.jenis_hidang === 'takeaway'
        ? ' takeaway'
        : (it.jenis_hidang === 'delivery' ? ' delivery' : ' dine-in');
      const badgeCls = it.jenis_hidang === 'takeaway'
        ? 'bungkus'
        : (it.jenis_hidang === 'delivery' ? 'delivery' : 'sini');

      return (
        '<article class="kitchen-card ' + esc(it.status_item) + hidangClass + (newSet.has(it.id) ? ' new-flash' : '') + '">' +
          '<div class="kitchen-table">' + esc(tableTitle(it.nomor_meja)) + '</div>' +
          '<div class="serve-badge ' + badgeCls + '">' + esc(hidang) + '</div>' +
          '<div class="kitchen-qty">×' + it.qty + '</div>' +
          '<h2 class="kitchen-item-name">' + esc(it.nama) + '</h2>' +
          (it.nama_pelanggan ? '<div class="order-meta">' + esc(it.nama_pelanggan) + '</div>' : '') +
          note +
          '<div class="order-meta" style="margin:8px 0 14px">#' + it.order_id + ' · ' + esc(it.waktu_order) + '</div>' +
          '<div style="display:flex;flex-wrap:wrap;gap:8px">' + actions + '</div>' +
        '</article>'
      );
    }).join('');
  }

  function printLabels() {
    return {
      table: i18n.table || 'Meja',
      order: i18n.order || 'Order',
      guest: i18n.guest_name || 'Guest',
      dine_in: i18n.dine_in || 'Dine in',
      takeaway: i18n.takeaway || 'Takeaway',
      kitchen_ticket: i18n.kitchen_ticket || 'KITCHEN TICKET',
      test_station: stationName || 'Test',
      test_item: i18n.print_test_item || 'Test print OK',
    };
  }

  function groupNewTickets(items, newIds) {
    const idSet = new Set(newIds || []);
    const byOrder = {};
    items.forEach(function (it) {
      if (!idSet.has(it.id) || printedIds.has(it.id)) return;
      if (it.status_item !== 'menunggu') return;
      const oid = it.order_id;
      if (!byOrder[oid]) {
        byOrder[oid] = {
          shopName: shopName,
          stationName: stationName,
          table: it.nomor_meja,
          orderId: oid,
          serveLabel: serveLabel(it.jenis_hidang),
          guest: it.nama_pelanggan || '',
          time: it.waktu_order || '',
          items: [],
          itemIds: [],
        };
      }
      byOrder[oid].items.push({
        qty: it.qty,
        nama: it.nama,
        catatan: it.catatan || '',
      });
      byOrder[oid].itemIds.push(it.id);
    });
    return Object.keys(byOrder).map(function (k) { return byOrder[k]; });
  }

  async function autoPrintNew(items, newIds) {
    if (!autoPrint || !window.TableTapPrint || !TableTapPrint.supported()) return;
    if (!primed || printBusy) return;
    const tickets = groupNewTickets(items, newIds);
    if (!tickets.length) return;

    printBusy = true;
    try {
      try {
        await TableTapPrint.ensureConnected({ interactive: false });
      } catch (e) {
        return;
      }
      for (let i = 0; i < tickets.length; i++) {
        const t = tickets[i];
        try {
          await TableTapPrint.printKitchenTicket(t, printLabels());
          t.itemIds.forEach(function (id) { printedIds.add(id); });
        } catch (err) {
          console.warn('TableTap print failed', err);
          updatePrintStatus(i18n.print_failed || 'Print failed');
          break;
        }
      }
    } finally {
      printBusy = false;
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
        el.textContent = i18n.printer_unsupported || 'Bluetooth print needs Chrome/Edge (Android or desktop).';
        el.className = 'print-status warn';
      } else if (extra) {
        el.textContent = extra;
        el.className = 'print-status warn';
      } else if (connected) {
        el.textContent = (i18n.printer_connected || 'Printer connected') +
          (autoPrint ? ' · ' + (i18n.autoprint_on || 'Auto-print on') : '');
        el.className = 'print-status ok';
      } else {
        el.textContent = i18n.printer_hint || 'Connect a Bluetooth thermal printer on this device for silent kitchen tickets.';
        el.className = 'print-status';
      }
    }
  }

  function wirePrinterUi() {
    if (!window.TableTapPrint) return;

    const connectBtn = document.getElementById('btn-connect-printer');
    const testBtn = document.getElementById('btn-test-print');
    const autoBtn = document.getElementById('btn-autoprint');

    setAutoPrint(autoPrint);
    TableTapPrint.onChange(function () { updatePrintStatus(); });
    updatePrintStatus();

    TableTapPrint.reconnect().then(function () {
      updatePrintStatus();
    }).catch(function () { /* no prior grant */ });

    connectBtn?.addEventListener('click', async function () {
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
        } else if (String(err && err.message) === 'unsupported') {
          updatePrintStatus(i18n.printer_unsupported || 'Bluetooth print not supported');
        } else {
          updatePrintStatus(i18n.print_failed || 'Could not connect printer');
        }
      } finally {
        updatePrintStatus();
      }
    });

    testBtn?.addEventListener('click', async function () {
      testBtn.disabled = true;
      try {
        await TableTapPrint.ensureConnected({ interactive: true });
        await TableTapPrint.printTest(printLabels());
        updatePrintStatus(i18n.print_test_ok || 'Test printed');
      } catch (err) {
        updatePrintStatus(i18n.print_failed || 'Print failed');
      } finally {
        testBtn.disabled = false;
        updatePrintStatus();
      }
    });

    autoBtn?.addEventListener('click', function () {
      setAutoPrint(!autoPrint);
      updatePrintStatus();
    });
  }

  async function poll() {
    if (busy) return;
    busy = true;
    try {
      const url = pollUrl +
        '?kategori=' + encodeURIComponent(kategori) +
        (stationId ? ('&station_id=' + encodeURIComponent(stationId)) : '') +
        '&since_id=' + encodeURIComponent(String(sinceId)) +
        '&lang=' + encodeURIComponent(lang);
      const res = await TableTapLive.fetch(url);
      if (res.status === 401) {
        window.location.href = '../login.php';
        return;
      }
      const data = await res.json();
      if (!data.ok) return;

      const items = data.items || [];
      const newIds = data.new_item_ids || [];

      if (typeof data.max_id === 'number') {
        sinceId = Math.max(sinceId, data.max_id);
      }

      // First poll only seeds baseline — avoid reprinting all waiting tickets on open
      if (!primed) {
        newIds.forEach(function (id) { printedIds.add(id); });
        primed = true;
        applyAlarm(data.pending_alerts || 0, [], data.sound || {});
        render(items, []);
        return;
      }

      applyAlarm(data.pending_alerts || 0, newIds, data.sound || {});
      render(items, newIds);
      await autoPrintNew(items, newIds);
    } catch (e) {
      // keep going
    } finally {
      busy = false;
    }
  }

  root.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-status][data-id]');
    if (!btn) return;
    const id = Number(btn.getAttribute('data-id'));
    const status = btn.getAttribute('data-status');
    btn.disabled = true;
    try {
      const res = await fetch(updateUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ item_id: id, status: status }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');
      await poll();
    } catch (err) {
      alert(err.message || 'Error');
      btn.disabled = false;
    }
  });

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
      TableTapSound.stopAlarm();
    }
  });

  wirePrinterUi();
  TableTapLive.loop(poll, interval, { keepAwake: true });
})();
