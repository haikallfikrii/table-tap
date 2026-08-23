/**
 * Waiter dashboard — pickup ready items, mark delivered, hold COD cash
 */
(function () {
  const root = document.getElementById('waiter-root');
  if (!root) return;

  const pollUrl = root.dataset.pollUrl;
  const updateUrl = root.dataset.updateUrl;
  const payUrl = root.dataset.payUrl || '';
  const interval = Number(root.dataset.interval) || 3000;
  const lang = root.dataset.lang || 'my';
  const i18n = JSON.parse(root.dataset.i18n || '{}');

  let sinceId = 0;
  let busy = false;
  let waveDone = false;
  let lastPending = 0;

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
    if (String(num) === 'Delivery') {
      return i18n.delivery || 'Delivery';
    }
    return (i18n.table_n || 'Table %s').replace('%s', String(num));
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

  function serveBadge(it) {
    if (it.jenis_hidang === 'delivery') {
      return { label: i18n.delivery || 'Delivery', cls: 'delivery' };
    }
    if (it.jenis_hidang === 'takeaway') {
      return { label: i18n.takeaway || 'Takeaway', cls: 'bungkus' };
    }
    return { label: i18n.dine_in || 'Dine in', cls: 'sini' };
  }

  function render(items, newIds) {
    const newSet = new Set(newIds || []);
    if (!items.length) {
      root.innerHTML = '<div class="empty-state">' + esc(i18n.no_waiter_items || 'Empty') + '</div>';
      return;
    }

    root.innerHTML = items.map((it) => {
      const note = it.catatan
        ? '<div class="item-note">' + esc(i18n.notes || 'Notes') + ': ' + esc(it.catatan) + '</div>'
        : '';
      const kat = it.station || (it.kategori === 'minuman'
        ? (i18n.minuman_title || 'Drinks')
        : (i18n.dapur_title || 'Kitchen'));

      let actions = '';
      if (it.status_item === 'siap') {
        actions =
          '<button type="button" class="btn btn-primary" style="width:100%" data-status="diambil" data-id="' + it.id + '">' +
            esc(i18n.mark_pickup || 'Pick up') +
          '</button>';
      } else {
        actions =
          '<button type="button" class="btn btn-success" style="width:100%" data-status="dihantar" data-id="' + it.id + '">' +
            esc(i18n.mark_delivered || 'Delivered') +
          '</button>';
      }

      const badge = serveBadge(it);
      const hidangClass = it.jenis_hidang === 'takeaway'
        ? ' takeaway'
        : (it.jenis_hidang === 'delivery' ? ' delivery' : ' dine-in');

      let deliveryMeta = '';
      if (it.jenis_hidang === 'delivery') {
        if (it.alamat) {
          deliveryMeta += '<div class="order-meta">' + esc(i18n.address || 'Address') + ': ' + esc(it.alamat) + '</div>';
        }
        if (it.phone) {
          deliveryMeta += '<div class="order-meta">' + esc(i18n.phone || 'Phone') + ': ' + esc(it.phone) + '</div>';
        }
        if (it.payment_method === 'cod' && it.status_bayar !== 'lunas') {
          if (it.payment_proof_status === 'uploaded') {
            deliveryMeta +=
              '<div class="order-meta" style="color:var(--warning)">' +
                esc(i18n.cod_held_waiting || 'Cash held — waiting kasir') +
              '</div>';
          } else {
            actions +=
              '<button type="button" class="btn btn-secondary" style="width:100%;margin-top:8px" data-cod-held="' + it.order_id + '">' +
                esc(i18n.cod_held_btn || 'COD cash received') +
              '</button>';
          }
        } else if (it.payment_method === 'cod' && it.status_bayar === 'lunas') {
          deliveryMeta +=
            '<div class="order-meta" style="color:var(--success)">' +
              esc(i18n.cod_paid || 'Paid') +
            '</div>';
        }
      }

      return (
        '<article class="kitchen-card ' + esc(it.status_item) + hidangClass + (newSet.has(it.id) ? ' new-flash' : '') + '">' +
          '<div class="kitchen-table">' + esc(tableTitle(it.nomor_meja)) + '</div>' +
          '<div class="serve-badge ' + badge.cls + '">' + esc(badge.label) + '</div>' +
          '<div class="kitchen-qty">×' + it.qty + '</div>' +
          '<h2 class="kitchen-item-name">' + esc(it.nama) + '</h2>' +
          (it.nama_pelanggan ? '<div class="order-meta">' + esc(it.nama_pelanggan) + '</div>' : '') +
          '<div class="order-meta">' + esc(kat) + '</div>' +
          note +
          deliveryMeta +
          '<div class="order-meta" style="margin:8px 0 14px">#' + it.order_id + ' · ' + esc(it.waktu_order) + '</div>' +
          actions +
        '</article>'
      );
    }).join('');
  }

  async function poll() {
    if (busy) return;
    busy = true;
    try {
      const url = pollUrl +
        '?since_id=' + encodeURIComponent(String(sinceId)) +
        '&lang=' + encodeURIComponent(lang);
      const res = await TableTapLive.fetch(url);
      if (res.status === 401) {
        window.location.href = '../login.php';
        return;
      }
      const data = await res.json();
      if (!data.ok) return;
      if (typeof data.max_id === 'number') {
        sinceId = Math.max(sinceId, data.max_id);
      }
      applyAlarm(data.pending_alerts || 0, data.new_item_ids || [], data.sound || {});
      render(data.items || [], data.new_item_ids || []);
    } catch (e) {
      // keep going
    } finally {
      busy = false;
    }
  }

  root.addEventListener('click', async (e) => {
    const codBtn = e.target.closest('[data-cod-held]');
    if (codBtn && payUrl) {
      const orderId = Number(codBtn.getAttribute('data-cod-held'));
      codBtn.disabled = true;
      try {
        const res = await fetch(payUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ order_id: orderId, action: 'cod_held' }),
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Failed');
        await poll();
      } catch (err) {
        alert(err.message || 'Error');
        codBtn.disabled = false;
      }
      return;
    }

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

  TableTapLive.loop(poll, interval, { keepAwake: true });
})();
