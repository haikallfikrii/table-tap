/**
 * Customer self-pickup tracker — live item status + auto alert when ready
 */
(function () {
  const root = document.getElementById('track-app');
  if (!root || typeof TableTapSound === 'undefined') return;

  const pollUrl = root.dataset.pollUrl;
  const interval = Number(root.dataset.interval) || 4000;
  const lang = root.dataset.lang || 'my';
  const i18n = JSON.parse(root.dataset.i18n || '{}');

  let knownReady = new Set();
  let primed = false;
  let busy = false;

  TableTapSound.armAutoUnlock();

  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function itemLabel(st) {
    if (st === 'sedang_dimasak') return i18n.status_cooking || 'Cooking';
    if (st === 'siap' || st === 'diambil') return i18n.status_ready || 'Ready';
    if (st === 'dihantar') return i18n.status_done || 'Collected';
    return i18n.status_queue || 'Waiting';
  }

  function itemClass(st) {
    if (st === 'sedang_dimasak') return 'cooking';
    if (st === 'siap' || st === 'diambil') return 'ready';
    if (st === 'dihantar') return 'done';
    return 'queue';
  }

  function overall(items) {
    if (!items.length) return 'queue';
    const allDone = items.every(function (it) {
      return it.status_item === 'dihantar';
    });
    if (allDone) return 'done';
    const anyReady = items.some(function (it) {
      return it.status_item === 'siap' || it.status_item === 'diambil';
    });
    if (anyReady) return 'ready';
    const anyCook = items.some(function (it) {
      return it.status_item === 'sedang_dimasak';
    });
    if (anyCook) return 'cooking';
    return 'queue';
  }

  function overallText(key) {
    if (key === 'cooking') return i18n.order_cooking || 'Being prepared';
    if (key === 'ready') return i18n.order_ready || 'Ready for pickup';
    if (key === 'done') return i18n.order_collected || 'Collected';
    return i18n.order_queue || 'In the queue';
  }

  function render(data) {
    const items = data.items || [];
    const key = overall(items);
    const banner = document.getElementById('track-banner');
    if (banner) {
      banner.className = 'confirm-status track-banner ' + key;
      banner.textContent = overallText(key);
    }
    const list = document.getElementById('track-items');
    if (!list) return;
    list.innerHTML = items.map(function (it) {
      const st = it.status_item;
      return (
        '<li class="' + itemClass(st) + '">' +
          '<span><b>' + esc(String(it.qty)) + '×</b> ' + esc(it.nama) + '</span>' +
          '<span class="track-pill">' + esc(itemLabel(st)) + '</span>' +
        '</li>'
      );
    }).join('');
  }

  function alertReady(sound) {
    TableTapSound.unlock();
    TableTapSound.configure({
      mode: 'duration',
      duration_sec: 18,
      interval_ms: (sound && sound.interval_ms) || 900,
      volume: (sound && sound.volume) || 100,
    });
    TableTapSound.stopAlarm();
    TableTapSound.startAlarm();
  }

  async function poll() {
    if (busy) return;
    busy = true;
    try {
      const url = pollUrl +
        (pollUrl.indexOf('?') >= 0 ? '&' : '?') +
        'lang=' + encodeURIComponent(lang);
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      if (!data.ok) return;
      render(data);
      const ready = data.ready_item_ids || [];
      const fresh = ready.filter(function (id) { return !knownReady.has(id); });
      if (!primed) {
        ready.forEach(function (id) { knownReady.add(id); });
        primed = true;
      } else if (fresh.length) {
        fresh.forEach(function (id) { knownReady.add(id); });
        alertReady(data.sound || {});
      }
    } catch (e) {
      /* keep polling */
    } finally {
      busy = false;
    }
  }

  poll();
  setInterval(poll, interval);
})();
