/**
 * Owner dashboard — live kitchen / drinks / handover / unpaid counts
 */
(function () {
  const root = document.getElementById('ops-board');
  if (!root) return;

  const pollUrl = root.dataset.pollUrl;
  const interval = Number(root.dataset.interval) || 4000;
  let busy = false;

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

  function apply(ops) {
    const k = ops.kitchen || {};
    const d = ops.drinks || {};
    const h = ops.handover || {};
    const u = ops.unpaid || {};

    setText('ops-kitchen-items', k.items || 0);
    setText('ops-kitchen-orders', k.orders || 0);
    setChip('ops-kitchen-queue', k.menunggu || 0);
    setChip('ops-kitchen-cook', k.sedang_dimasak || 0);
    setBusy('ops-card-kitchen', k.items || 0);

    setText('ops-drinks-items', d.items || 0);
    setText('ops-drinks-orders', d.orders || 0);
    setChip('ops-drinks-queue', d.menunggu || 0);
    setChip('ops-drinks-cook', d.sedang_dimasak || 0);
    setBusy('ops-card-drinks', d.items || 0);

    setText('ops-hand-items', h.items || 0);
    setText('ops-hand-orders', h.orders || 0);
    setChip('ops-hand-ready', h.siap || 0);
    setChip('ops-hand-deliver', h.diambil || 0);
    setBusy('ops-card-hand', h.items || 0);

    setText('ops-unpaid-n', u.orders || 0);
    setText('ops-unpaid-amt', u.amount_fmt || 'RM 0.00');
    setBusy('ops-card-unpaid', u.orders || 0);
    document.getElementById('ops-card-unpaid')?.classList.toggle('is-alert', Number(u.orders) > 0);
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

  TableTapLive.loop(poll, interval);
})();
