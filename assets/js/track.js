/**
 * Customer self-pickup tracker — live stages, looping hero, status sounds
 */
(function () {
  const root = document.getElementById('track-app');
  if (!root) return;

  const pollUrl = root.dataset.pollUrl;
  const collectUrl = root.dataset.collectUrl;
  const interval = Number(root.dataset.interval) || 4000;
  const lang = root.dataset.lang || 'my';
  const i18n = JSON.parse(root.dataset.i18n || '{}');
  const sound = window.TableTapSound;

  let lastStage = root.dataset.stage || 'queue';
  let lastItems = {};
  let primed = false;
  let busy = false;
  let alarmOn = false;
  let muted = false;
  let collecting = false;

  const modal = document.getElementById('sound-modal');
  const okBtn = document.getElementById('btn-sound-ok');
  if (okBtn && sound) {
    okBtn.addEventListener('click', function () {
      sound.unlock();
      modal?.classList.add('hidden');
      syncReadyAlarm(lastStage);
    });
  } else if (sound) {
    sound.unlock();
  }

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

  function titleFor(key) {
    if (key === 'cooking') return i18n.title_cooking || 'Kitchen is cooking';
    if (key === 'ready') return i18n.title_ready || 'Ready — please collect';
    if (key === 'done') return i18n.title_done || 'Collected. Thank you!';
    return i18n.title_queue || 'Order received';
  }

  function overallText(key) {
    if (key === 'cooking') return i18n.order_cooking || 'Being prepared';
    if (key === 'ready') return i18n.order_ready || 'Ready for pickup';
    if (key === 'done') return i18n.order_collected || 'Collected';
    return i18n.order_queue || 'In the queue';
  }

  function stopLoop() {
    if (!sound) return;
    sound.stopAlarm();
    alarmOn = false;
  }

  function buzz(pattern) {
    try {
      if (navigator.vibrate) navigator.vibrate(pattern);
    } catch (e) { /* ignore */ }
  }

  function playStageSound(key, isChange) {
    if (!sound) return;
    if (muted) {
      stopLoop();
      return;
    }
    if (key === 'ready') {
      if (!alarmOn || isChange) {
        sound.configure({
          mode: 'until_cleared',
          interval_ms: 1100,
          volume: 100,
        });
        stopLoop();
        sound.startAlarm();
        alarmOn = true;
        buzz([180, 80, 180, 80, 320]);
      }
      return;
    }
    stopLoop();
    if (!isChange) return;
    if (key === 'cooking') {
      sound.chime('cooking');
      buzz(160);
    } else if (key === 'done') {
      sound.chime('done');
      buzz(40);
    } else {
      sound.chime('queue');
    }
  }

  function syncReadyAlarm(stage) {
    if (muted) {
      stopLoop();
      return;
    }
    if (stage === 'ready') playStageSound('ready', true);
  }

  function setHero(stage) {
    const hero = document.getElementById('track-hero');
    if (hero) hero.setAttribute('data-stage', stage);
    const title = document.getElementById('track-title');
    if (title) title.textContent = titleFor(stage);
    const banner = document.getElementById('track-banner');
    if (banner) {
      banner.className = 'confirm-status track-banner ' + stage;
      banner.textContent = overallText(stage);
    }
    document.querySelectorAll('#track-steps [data-step]').forEach(function (el) {
      const step = el.getAttribute('data-step');
      const order = ['queue', 'cooking', 'ready', 'done'];
      const i = order.indexOf(step);
      const now = order.indexOf(stage);
      el.classList.toggle('is-current', step === stage);
      el.classList.toggle('is-done', i < now);
    });
    const collectBtn = document.getElementById('btn-i-collected');
    if (collectBtn) {
      collectBtn.style.display = stage === 'ready' ? '' : 'none';
    }
    root.dataset.stage = stage;
  }

  function render(data) {
    const items = data.items || [];
    const key = data.stage || 'queue';
    setHero(key);
    const list = document.getElementById('track-items');
    if (!list) return;
    list.innerHTML = items.map(function (it) {
      const st = it.status_item;
      return (
        '<li class="' + itemClass(st) + '">' +
          '<span class="track-item-pulse" aria-hidden="true"></span>' +
          '<span><b>' + esc(String(it.qty)) + '×</b> ' + esc(it.nama) + '</span>' +
          '<span class="track-pill">' + esc(itemLabel(st)) + '</span>' +
        '</li>'
      );
    }).join('');
  }

  function detectChanges(items) {
    let cooking = false;
    let ready = false;
    let collected = false;
    items.forEach(function (it) {
      const prev = lastItems[it.id];
      if (prev && prev !== it.status_item) {
        if (it.status_item === 'sedang_dimasak') cooking = true;
        if (it.status_item === 'siap' || it.status_item === 'diambil') ready = true;
        if (it.status_item === 'dihantar') collected = true;
      }
    });
    const map = {};
    items.forEach(function (it) { map[it.id] = it.status_item; });
    lastItems = map;
    return { cooking: cooking, ready: ready, collected: collected };
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
      const items = data.items || [];
      const stage = data.stage || 'queue';
      muted = data.pickup_alert === false;
      if (muted) stopLoop();
      const changes = detectChanges(items);
      render(data);

      if (!primed) {
        primed = true;
        playStageSound(stage, stage === 'ready' || stage === 'cooking');
        lastStage = stage;
        return;
      }

      if (stage !== lastStage) {
        playStageSound(stage, true);
      } else if (changes.ready && stage === 'ready') {
        playStageSound('ready', true);
      } else if (changes.cooking && stage !== 'ready') {
        playStageSound('cooking', true);
      } else if (changes.collected && stage === 'done') {
        playStageSound('done', true);
      }
      lastStage = stage;
    } catch (e) {
      /* keep polling */
    } finally {
      busy = false;
    }
  }

  document.getElementById('btn-i-collected')?.addEventListener('click', async function () {
    if (collecting || !collectUrl) return;
    collecting = true;
    stopLoop();
    muted = true;
    this.disabled = true;
    try {
      const res = await fetch(collectUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          order: Number(root.dataset.order || 0),
          meja: root.dataset.meja || '',
          token: root.dataset.token || '',
        }),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');
      await poll();
    } catch (err) {
      muted = false;
      collecting = false;
      this.disabled = false;
    } finally {
      collecting = false;
    }
  });

  poll();
  setInterval(poll, interval);
})();
