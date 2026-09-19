/**
 * Owner dashboard — live station / handover / unpaid counts + alerts
 */
(function () {
  const root = document.getElementById('ops-board');
  if (!root) return;

  const pollUrl = root.dataset.pollUrl;
  const interval = Number(root.dataset.interval) || 4000;
  const alertsRoot = document.getElementById('owner-alerts');
  const alertsList = document.getElementById('owner-alerts-list');
  const alertsBadge = document.getElementById('owner-alerts-badge');
  const alertsReadBtn = document.getElementById('btn-alerts-read');
  const alertsReadUrl = alertsRoot ? (alertsRoot.dataset.readUrl || '') : '';
  let busy = false;
  let lastUnread = -1;

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = String(value);
  }

  function setChip(id, n) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = n;
    el.parentElement?.classList.toggle('hidden', Number(n) <= 0);
  }

  function setBusy(cardId, n) {
    document.getElementById(cardId)?.classList.toggle('is-busy', Number(n) > 0);
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderAlerts(alerts) {
    if (!alertsList) return;
    const items = (alerts && alerts.items) || [];
    const unread = Number((alerts && alerts.unread) || 0);
    if (alertsBadge) {
      alertsBadge.textContent = String(unread);
      alertsBadge.classList.toggle('hidden', unread <= 0);
    }
    if (alertsReadBtn) {
      alertsReadBtn.hidden = unread <= 0;
    }
    if (lastUnread >= 0 && unread > lastUnread) {
      try {
        if (window.TableTapSound && typeof TableTapSound.beep === 'function') {
          TableTapSound.beep();
        }
      } catch (e) { /* ignore */ }
    }
    lastUnread = unread;

    if (!items.length) {
      const empty = (alertsRoot && alertsRoot.dataset.empty) || '—';
      alertsList.innerHTML = '<p class="order-meta" id="owner-alerts-empty">' + esc(empty) + '</p>';
      return;
    }
    alertsList.innerHTML = items.map(function (al) {
      return (
        '<article class="owner-alert-item' + (al.is_read ? ' is-read' : ' is-unread') + '" data-alert-id="' + al.id + '">' +
          '<div class="owner-alert-title">' + esc(al.title) + '</div>' +
          '<div class="owner-alert-body">' + esc(al.body) + '</div>' +
          '<div class="order-meta">' + esc(al.created_at || '') + '</div>' +
        '</article>'
      );
    }).join('');
  }

  function apply(ops) {
    const stations = ops.stations || [];
    stations.forEach(function (st) {
      const id = st.id;
      setText('ops-st-items-' + id, st.items || 0);
      setText('ops-st-orders-' + id, st.orders || 0);
      setChip('ops-st-queue-' + id, st.menunggu || 0);
      setChip('ops-st-cook-' + id, st.sedang_dimasak || 0);
      setBusy('ops-card-st-' + id, st.items || 0);
    });

    const h = ops.handover || {};
    const u = ops.unpaid || {};

    setText('ops-hand-items', h.items || 0);
    setText('ops-hand-orders', h.orders || 0);
    setChip('ops-hand-ready', h.siap || 0);
    setChip('ops-hand-deliver', h.diambil || 0);
    setBusy('ops-card-hand', h.items || 0);

    setText('ops-unpaid-n', u.orders || 0);
    setText('ops-unpaid-amt', u.amount_fmt || 'RM 0.00');
    setBusy('ops-card-unpaid', u.orders || 0);
    document.getElementById('ops-card-unpaid')?.classList.toggle('is-alert', Number(u.orders) > 0);

    const d = ops.delivery || {};
    if (d.enabled) {
      setText('ops-delivery-n', d.orders || 0);
      const sub = document.getElementById('ops-delivery-sub');
      if (sub) {
        if (Number(d.needs_action) > 0) {
          sub.textContent = (d.needs_action || 0) + ' needs action · ' + (d.amount_fmt || '');
        } else {
          sub.textContent = 'Payment & proof — separate from tables';
        }
      }
      setBusy('ops-card-delivery', d.needs_action || d.orders || 0);
      document.getElementById('ops-card-delivery')?.classList.toggle('is-alert', Number(d.needs_action) > 0);
    }

    if (ops.alerts) renderAlerts(ops.alerts);
  }

  async function poll() {
    if (busy || !pollUrl) return;
    busy = true;
    try {
      const res = await TableTapLive.fetch(pollUrl);
      const data = await res.json();
      if (data.ok && data.ops) apply(data.ops);
    } catch (e) {
      /* keep last snapshot */
    } finally {
      busy = false;
    }
  }

  if (alertsReadBtn && alertsReadUrl) {
    alertsReadBtn.addEventListener('click', async function () {
      alertsReadBtn.disabled = true;
      try {
        const res = await fetch(alertsReadUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({}),
        });
        const data = await res.json();
        if (data.ok) await poll();
      } catch (e) {
        /* ignore */
      } finally {
        alertsReadBtn.disabled = false;
      }
    });
  }

  TableTapLive.loop(poll, interval);
})();
