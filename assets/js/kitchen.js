/**
 * Kitchen / drinks dashboard polling
 */
(function () {
  const root = document.getElementById('kitchen-root');
  if (!root) return;

  const pollUrl = root.dataset.pollUrl;
  const updateUrl = root.dataset.updateUrl;
  const kategori = root.dataset.kategori || 'makanan';
  const interval = Number(root.dataset.interval) || 3000;
  const lang = root.dataset.lang || 'my';
  const i18n = JSON.parse(root.dataset.i18n || '{}');

  let sinceId = 0;
  let busy = false;

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
    return (i18n.table_n || 'Table %s').replace('%s', String(num));
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
      if (it.status_item === 'menunggu') {
        actions =
          '<button type="button" class="btn btn-secondary btn-sm" data-status="sedang_dimasak" data-id="' + it.id + '">' +
            esc(i18n.mark_cooking || 'Start') +
          '</button> ' +
          '<button type="button" class="btn btn-success" data-status="selesai" data-id="' + it.id + '">' +
            esc(i18n.mark_done || 'Done') +
          '</button>';
      } else {
        actions =
          '<button type="button" class="btn btn-success" style="width:100%" data-status="selesai" data-id="' + it.id + '">' +
            esc(i18n.mark_done || 'Done') +
          '</button>';
      }

      return (
        '<article class="kitchen-card ' + esc(it.status_item) + (newSet.has(it.id) ? ' new-flash' : '') + '">' +
          '<div class="kitchen-table">' + esc(tableTitle(it.nomor_meja)) + '</div>' +
          '<div class="kitchen-qty">×' + it.qty + '</div>' +
          '<h2 class="kitchen-item-name">' + esc(it.nama) + '</h2>' +
          note +
          '<div class="order-meta" style="margin:8px 0 14px">#' + it.order_id + ' · ' + esc(it.waktu_order) + '</div>' +
          '<div style="display:flex;flex-wrap:wrap;gap:8px">' + actions + '</div>' +
        '</article>'
      );
    }).join('');
  }

  async function poll() {
    if (busy) return;
    busy = true;
    try {
      const url = pollUrl +
        '?kategori=' + encodeURIComponent(kategori) +
        '&since_id=' + encodeURIComponent(String(sinceId)) +
        '&lang=' + encodeURIComponent(lang);
      const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
      if (res.status === 401) {
        window.location.href = '../login.php';
        return;
      }
      const data = await res.json();
      if (!data.ok) return;

      if ((data.new_item_ids || []).length > 0 && sinceId > 0) {
        TableTapSound.beep();
      }
      if (typeof data.max_id === 'number') {
        sinceId = Math.max(sinceId, data.max_id);
      }
      render(data.items || [], data.new_item_ids || []);
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

  poll();
  setInterval(poll, interval);
})();
