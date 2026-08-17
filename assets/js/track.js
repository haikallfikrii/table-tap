/**
 * Customer order tracker — multi-order, live stages, sounds
 */
(function () {
  const root = document.getElementById('track-app');
  if (!root) return;

  const pollUrl = root.dataset.pollUrl;
  const collectUrl = root.dataset.collectUrl;
  const sessionToken = root.dataset.session || '';
  const interval = Number(root.dataset.interval) || 4000;
  const lang = root.dataset.lang || 'my';
  const i18n = JSON.parse(root.dataset.i18n || '{}');
  const fulfillment = root.dataset.fulfillment || 'waiter';
  const loopingReady = fulfillment === 'self_pickup';
  const sound = window.TableTapSound;
  const focusOrderId = Number(root.dataset.focusOrder || 0);

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
      if (loopingReady) {
        syncReadyAlarm(lastStage);
      } else if (lastStage && lastStage !== 'queue') {
        playStageSound(lastStage, true);
      }
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
    if (st === 'siap') return i18n.status_ready || 'Ready';
    if (st === 'diambil') return i18n.status_deliver || 'On the way';
    if (st === 'dihantar') return i18n.status_done || 'Done';
    return i18n.status_queue || 'Waiting';
  }

  function itemClass(st) {
    if (st === 'sedang_dimasak') return 'cooking';
    if (st === 'siap') return 'ready';
    if (st === 'diambil') return 'delivering';
    if (st === 'dihantar') return 'done';
    return 'queue';
  }

  function titleFor(key) {
    if (key === 'cooking') return i18n.title_cooking || 'Kitchen is cooking';
    if (key === 'ready') return i18n.title_ready || 'Ready';
    if (key === 'delivering') return i18n.title_delivering || 'On the way';
    if (key === 'done') return i18n.title_done || 'Done';
    return i18n.title_queue || 'Order received';
  }

  function overallText(key) {
    if (key === 'cooking') return i18n.order_cooking || 'Being prepared';
    if (key === 'ready') return i18n.order_ready || 'Ready';
    if (key === 'delivering') return i18n.order_delivering || 'On the way';
    if (key === 'done') return i18n.order_collected || 'Done';
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

  let doneChimePlayed = false;

  function playDoneChime() {
    if (!sound || doneChimePlayed) return;
    doneChimePlayed = true;
    stopLoop();
    sound.chime('done');
    buzz([50, 70, 90, 70, 140]);
  }

  function playStageSound(key, isChange) {
    if (!sound) return;
    if (key === 'done') {
      if (isChange) playDoneChime();
      else stopLoop();
      return;
    }
    if (key === 'ready') {
      if (muted) {
        stopLoop();
        return;
      }
      if (loopingReady) {
        if (!alarmOn || isChange) {
          sound.configure({ mode: 'until_cleared', interval_ms: 1100, volume: 100 });
          stopLoop();
          sound.startAlarm();
          alarmOn = true;
          buzz([180, 80, 180, 80, 320]);
        }
        return;
      }
      stopLoop();
      if (isChange) {
        sound.chime('ready');
        buzz(120);
      }
      return;
    }
    if (key === 'delivering') {
      stopLoop();
      if (isChange) {
        sound.chime('delivering');
        buzz([80, 60, 80]);
      }
      return;
    }
    stopLoop();
    if (!isChange) return;
    if (key === 'cooking') {
      sound.chime('cooking');
      buzz(160);
    } else {
      sound.chime('queue');
    }
  }

  function syncReadyAlarm(stage) {
    if (!loopingReady || muted) {
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
    document.querySelectorAll('#track-steps [data-step]').forEach(function (el) {
      const step = el.getAttribute('data-step');
      const order = [];
      document.querySelectorAll('#track-steps [data-step]').forEach(function (s) {
        order.push(s.getAttribute('data-step'));
      });
      const i = order.indexOf(step);
      const now = order.indexOf(stage);
      el.classList.toggle('is-current', step === stage);
      el.classList.toggle('is-done', now >= 0 && i < now);
    });
    root.dataset.stage = stage;
  }

  function renderOrderCard(order, isFocus) {
    const oid = Number(order.order_id || 0);
    const stage = order.stage || 'queue';
    const gt = order.guest_token || '';
    const items = order.items || [];
    const label = isFocus ? (i18n.order_latest || 'Latest') : (i18n.order_earlier || 'Earlier');
    const jenis = order.jenis_hidang === 'takeaway' ? (i18n.takeaway || 'Takeaway') : (i18n.dine_in || 'Dine in');
    const total = order.total_formatted || order.total_harga || '';
    const name = order.nama_pelanggan ? '<span class="track-order-name">' + esc(order.nama_pelanggan) + '</span>' : '';

    let html =
      '<article class="track-order-card' + (isFocus ? ' is-focus' : '') + '" data-order-id="' + oid + '" data-guest-token="' + esc(gt) + '" data-stage="' + esc(stage) + '">' +
        '<header class="track-order-card-head">' +
          '<div><span class="track-order-label">' + esc(label) + '</span> <strong class="track-order-no">#' + oid + '</strong>' + name + '</div>' +
          '<div class="confirm-status track-banner track-order-banner ' + esc(stage) + '">' + esc(overallText(stage)) + '</div>' +
        '</header>' +
        '<p class="track-order-meta">' + esc(jenis) + ' · ' + esc(String(total)) + '</p>' +
        '<ul class="track-items track-order-items">' +
        items.map(function (it) {
          const st = it.status_item;
          return (
            '<li class="' + itemClass(st) + '">' +
              '<span class="track-item-pulse" aria-hidden="true"></span>' +
              '<span class="track-item-name"><b>' + esc(String(it.qty)) + '×</b> ' + esc(it.nama) + '</span>' +
              '<span class="track-pill">' + esc(itemLabel(st)) + '</span>' +
            '</li>'
          );
        }).join('') +
        '</ul>';

    if (loopingReady) {
      html += '<button type="button" class="btn btn-success btn-collect-order" style="width:100%;margin-top:8px;' + (stage === 'ready' ? '' : 'display:none') + '" data-order-id="' + oid + '" data-guest-token="' + esc(gt) + '">' +
        esc(i18n.i_collected || 'Collected') + ' (#' + oid + ')</button>';
    }
    html += '</article>';
    return html;
  }

  function render(data) {
    const orders = data.orders || [];
    const focusId = Number(data.focus_order_id || focusOrderId);
    const focusOrder = orders.find(function (o) { return Number(o.order_id) === focusId; }) || orders[0];
    const stage = focusOrder ? (focusOrder.stage || 'queue') : (data.focus_stage || 'queue');
    setHero(stage);

    const list = document.getElementById('track-orders-list');
    const heading = document.getElementById('track-orders-heading');
    if (heading && orders.length > 1) {
      heading.textContent = (i18n.your_orders || 'Your orders') + ' (' + orders.length + ')';
    }
    if (list) {
      list.innerHTML = orders.map(function (o) {
        return renderOrderCard(o, Number(o.order_id) === focusId);
      }).join('');
    }
    return { stage: stage, focusOrder: focusOrder, orders: orders };
  }

  function detectChanges(orders, focusId) {
    let cooking = false;
    let ready = false;
    let collected = false;
    orders.forEach(function (order) {
      if (Number(order.order_id) !== focusId) return;
      (order.items || []).forEach(function (it) {
        const key = order.order_id + ':' + it.id;
        const prev = lastItems[key];
        if (prev && prev !== it.status_item) {
          if (it.status_item === 'sedang_dimasak') cooking = true;
          if (it.status_item === 'siap') ready = true;
          if (it.status_item === 'diambil') ready = true;
          if (it.status_item === 'dihantar') collected = true;
        }
      });
    });
    const map = {};
    orders.forEach(function (order) {
      (order.items || []).forEach(function (it) {
        map[order.order_id + ':' + it.id] = it.status_item;
      });
    });
    lastItems = map;
    return { cooking: cooking, ready: ready, collected: collected };
  }

  async function poll() {
    if (busy || !pollUrl) return;
    busy = true;
    try {
      const url = pollUrl + (pollUrl.indexOf('?') >= 0 ? '&' : '?') + 'lang=' + encodeURIComponent(lang);
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();
      if (!data.ok) return;

      const focusId = Number(data.focus_order_id || focusOrderId);
      const focusOrder = (data.orders || []).find(function (o) { return Number(o.order_id) === focusId; });
      const stage = focusOrder ? (focusOrder.stage || 'queue') : (data.focus_stage || 'queue');
      const alertOn = focusOrder ? focusOrder.pickup_alert !== false : true;
      const changes = detectChanges(data.orders || [], focusId);
      const becameDone = stage === 'done' && lastStage !== 'done';
      muted = !alertOn && stage !== 'done';
      if (!alertOn && stage !== 'done') stopLoop();
      render(data);

      if (!primed) {
        primed = true;
        playStageSound(stage, loopingReady ? (stage === 'ready' || stage === 'cooking') : false);
        lastStage = stage;
        muted = !alertOn;
        return;
      }

      if (becameDone) {
        playDoneChime();
      } else if (stage !== lastStage) {
        playStageSound(stage, true);
      } else if (changes.ready && stage === 'ready') {
        playStageSound('ready', true);
      } else if (changes.cooking && stage !== 'ready') {
        playStageSound('cooking', true);
      }
      lastStage = stage;
      muted = !alertOn;
    } catch (e) {
      /* keep polling */
    } finally {
      busy = false;
    }
  }

  document.getElementById('track-orders-list')?.addEventListener('click', async function (e) {
    const btn = e.target.closest('.btn-collect-order');
    if (!btn || collecting || !collectUrl) return;
    collecting = true;
    stopLoop();
    btn.disabled = true;
    try {
      const body = {
        order: Number(btn.dataset.orderId || 0),
        guest_token: btn.dataset.guestToken || '',
      };
      if (sessionToken) {
        body.session = sessionToken;
      } else {
        body.meja = root.dataset.meja || '';
        body.token = root.dataset.token || '';
      }
      const res = await fetch(collectUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');
      busy = false;
      await poll();
    } catch (err) {
      muted = false;
      btn.disabled = false;
    } finally {
      collecting = false;
    }
  });

  poll();
  setInterval(poll, interval);
})();
