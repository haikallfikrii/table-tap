/**
 * TableTap — customer cart & order submit
 */
(function () {
  const root = document.getElementById('order-app');
  if (!root) return;

  const meja = root.dataset.meja;
  const token = root.dataset.token;
  const submitUrl = root.dataset.submitUrl;
  const fulfillment = root.dataset.fulfillment || 'waiter';
  const i18n = JSON.parse(root.dataset.i18n || '{}');

  const money = (n) => {
    const v = Number(n) || 0;
    return 'RM ' + v.toFixed(2);
  };

  /** @type {Array<{id:number,nama:string,harga:number,qty:number,catatan:string}>} */
  let cart = [];
  let serveType = 'dine_in';

  try {
    const saved = sessionStorage.getItem('tt_cart_' + meja);
    if (saved) cart = JSON.parse(saved) || [];
    const savedServe = sessionStorage.getItem('tt_serve_' + meja);
    if (savedServe === 'takeaway' || savedServe === 'dine_in') serveType = savedServe;
    const savedName = sessionStorage.getItem('tt_name_' + meja);
    const nameInput = document.getElementById('guest-name');
    if (nameInput && savedName) nameInput.value = savedName;
  } catch (e) {
    cart = [];
  }

  function persist() {
    try {
      sessionStorage.setItem('tt_cart_' + meja, JSON.stringify(cart));
      sessionStorage.setItem('tt_serve_' + meja, serveType);
      const nameInput = document.getElementById('guest-name');
      if (nameInput) sessionStorage.setItem('tt_name_' + meja, nameInput.value.trim());
    } catch (e) { /* ignore */ }
  }

  function syncServeButtons() {
    document.querySelectorAll('.serve-opt').forEach(function (btn) {
      btn.classList.toggle('on', btn.getAttribute('data-serve') === serveType);
    });
  }

  function cartCount() {
    return cart.reduce((s, i) => s + i.qty, 0);
  }

  function cartSubtotal() {
    return cart.reduce((s, i) => s + i.harga * i.qty, 0);
  }

  function cartSst(subtotal) {
    if (!i18n.sst_enabled) return 0;
    const rate = Number(i18n.sst_rate) || 0;
    return Math.round(subtotal * (rate / 100) * 100) / 100;
  }

  function cartTotal() {
    const sub = cartSubtotal();
    return sub + cartSst(sub);
  }

  function findLine(id) {
    return cart.find((i) => i.id === id);
  }

  function renderBar() {
    const bar = document.getElementById('cart-bar');
    const countEl = document.getElementById('cart-bar-count');
    const totalEl = document.getElementById('cart-bar-total');
    if (!bar) return;
    if (cart.length === 0) {
      bar.classList.remove('visible');
      return;
    }
    bar.classList.add('visible');
    if (countEl) countEl.textContent = String(cartCount());
    if (totalEl) totalEl.textContent = money(cartTotal());
  }

  function renderSheet() {
    const body = document.getElementById('cart-sheet-body');
    const totalEl = document.getElementById('cart-sheet-total');
    const submitBtn = document.getElementById('btn-submit-order');
    if (!body) return;

    if (cart.length === 0) {
      body.innerHTML = '<div class="empty-cart">' + escapeHtml(i18n.cart_empty || 'Empty') + '</div>';
      if (totalEl) totalEl.textContent = money(0);
      if (submitBtn) submitBtn.disabled = true;
      const sstRowsEmpty = document.getElementById('cart-sst-rows');
      if (sstRowsEmpty) sstRowsEmpty.innerHTML = '';
      return;
    }

    body.innerHTML = cart.map((item) => {
      return (
        '<div class="cart-line" data-id="' + item.id + '">' +
          '<div>' +
            '<div class="cart-line-name">' + escapeHtml(item.nama) + '</div>' +
            '<div class="cart-line-meta">' + money(item.harga) + '</div>' +
            '<div class="qty-control">' +
              '<button type="button" class="qty-btn" data-action="dec" aria-label="-">−</button>' +
              '<span>' + item.qty + '</span>' +
              '<button type="button" class="qty-btn" data-action="inc" aria-label="+">+</button>' +
              '<button type="button" data-action="remove" class="cart-remove">' + escapeHtml(i18n.remove || 'Remove') + '</button>' +
            '</div>' +
            '<textarea class="cart-note" data-action="note" rows="1" placeholder="' + escapeHtml(i18n.item_note_ph || '') + '">' + escapeHtml(item.catatan || '') + '</textarea>' +
          '</div>' +
          '<div style="font-weight:800">' + money(item.harga * item.qty) + '</div>' +
        '</div>'
      );
    }).join('');

    if (totalEl) totalEl.textContent = money(cartTotal());
    if (submitBtn) submitBtn.disabled = false;

    const sstRows = document.getElementById('cart-sst-rows');
    if (sstRows) {
      if (i18n.sst_enabled && cart.length > 0) {
        const sub = cartSubtotal();
        const sst = cartSst(sub);
        sstRows.innerHTML =
          '<div class="cart-total-row" style="font-size:0.95rem;font-weight:600;color:var(--ink-muted)">' +
            '<span>' + escapeHtml(i18n.subtotal || 'Subtotal') + '</span><span>' + money(sub) + '</span>' +
          '</div>' +
          '<div class="cart-total-row" style="font-size:0.95rem;font-weight:600;color:var(--ink-muted)">' +
            '<span>' + escapeHtml(i18n.sst || 'SST') + ' (' + (Number(i18n.sst_rate) || 0) + '%)</span><span>' + money(sst) + '</span>' +
          '</div>';
      } else {
        sstRows.innerHTML = '';
      }
    }
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function refresh() {
    persist();
    renderBar();
    renderSheet();
    syncServeButtons();
  }

  function openSheet() {
    document.getElementById('sheet-overlay')?.classList.add('open');
    document.getElementById('cart-sheet')?.classList.add('open');
    renderSheet();
  }

  function closeSheet() {
    document.getElementById('sheet-overlay')?.classList.remove('open');
    document.getElementById('cart-sheet')?.classList.remove('open');
  }

  function addToCart(id, nama, harga) {
    const existing = findLine(id);
    if (existing) {
      existing.qty += 1;
    } else {
      cart.push({ id, nama, harga, qty: 1, catatan: '' });
    }
    if (window.TableTapSound) TableTapSound.unlock();
    refresh();
  }

  let detailItem = null;

  function closeDetail() {
    document.getElementById('detail-overlay')?.classList.remove('open');
    document.getElementById('detail-sheet')?.classList.remove('open');
  }

  function openDetail(data) {
    detailItem = data;
    const title = document.getElementById('detail-title');
    const body = document.getElementById('detail-body');
    const addBtn = document.getElementById('detail-add');
    if (title) title.textContent = data.nama || '';
    const photos = Array.isArray(data.photos) ? data.photos : [];
    let gallery = '';
    if (photos.length) {
      gallery = '<div class="detail-gallery">' + photos.map(function (src, i) {
        return '<img src="' + escapeHtml(src) + '" alt="" loading="lazy"' + (i === 0 ? ' class="on"' : '') + '>';
      }).join('') + '</div>';
      if (photos.length > 1) {
        gallery += '<div class="detail-dots">' + photos.map(function (_, i) {
          return '<button type="button" class="detail-dot' + (i === 0 ? ' on' : '') + '" data-slide="' + i + '"></button>';
        }).join('') + '</div>';
      }
    }
    if (body) {
      body.innerHTML = gallery +
        (data.desc ? '<p class="detail-desc">' + escapeHtml(data.desc) + '</p>' : '') +
        '<p class="detail-price">' + escapeHtml(data.harga_l || '') + '</p>';
    }
    if (addBtn) {
      addBtn.disabled = !!data.out;
    }
    document.getElementById('detail-overlay')?.classList.add('open');
    document.getElementById('detail-sheet')?.classList.add('open');
  }

  // Add buttons
  document.querySelectorAll('[data-add-item]').forEach((btn) => {
    btn.addEventListener('click', () => {
      addToCart(Number(btn.dataset.addItem), btn.dataset.nama || '', Number(btn.dataset.harga) || 0);
    });
  });

  document.querySelectorAll('[data-open-detail]').forEach(function (el) {
    el.addEventListener('click', function () {
      try {
        openDetail(JSON.parse(el.getAttribute('data-open-detail') || '{}'));
      } catch (err) { /* ignore */ }
    });
  });

  document.getElementById('detail-overlay')?.addEventListener('click', closeDetail);
  document.getElementById('btn-close-detail')?.addEventListener('click', closeDetail);
  document.getElementById('detail-add')?.addEventListener('click', function () {
    if (!detailItem || detailItem.out) return;
    addToCart(Number(detailItem.id), detailItem.nama || '', Number(detailItem.harga) || 0);
    closeDetail();
  });
  document.getElementById('detail-body')?.addEventListener('click', function (e) {
    const dot = e.target.closest('[data-slide]');
    if (!dot) return;
    const i = Number(dot.getAttribute('data-slide'));
    const imgs = document.querySelectorAll('.detail-gallery img');
    const dots = document.querySelectorAll('.detail-dot');
    imgs.forEach(function (img, idx) { img.classList.toggle('on', idx === i); });
    dots.forEach(function (d, idx) { d.classList.toggle('on', idx === i); });
  });

  document.getElementById('cart-bar-btn')?.addEventListener('click', openSheet);
  document.getElementById('sheet-overlay')?.addEventListener('click', closeSheet);
  document.getElementById('btn-close-cart')?.addEventListener('click', closeSheet);

  document.querySelectorAll('.serve-opt').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const next = btn.getAttribute('data-serve');
      if (next === 'takeaway' || next === 'dine_in') {
        serveType = next;
        persist();
        syncServeButtons();
      }
    });
  });

  document.getElementById('cart-sheet-body')?.addEventListener('click', (e) => {
    const target = e.target;
    if (!(target instanceof HTMLElement)) return;
    const line = target.closest('.cart-line');
    if (!line) return;
    const id = Number(line.dataset.id);
    const item = findLine(id);
    if (!item) return;
    const action = target.dataset.action;
    if (action === 'inc') item.qty += 1;
    else if (action === 'dec') {
      item.qty -= 1;
      if (item.qty <= 0) cart = cart.filter((i) => i.id !== id);
    } else if (action === 'remove') {
      cart = cart.filter((i) => i.id !== id);
    } else {
      return;
    }
    refresh();
  });

  document.getElementById('cart-sheet-body')?.addEventListener('input', (e) => {
    const target = e.target;
    if (!(target instanceof HTMLTextAreaElement)) return;
    if (target.dataset.action !== 'note') return;
    const line = target.closest('.cart-line');
    if (!line) return;
    const item = findLine(Number(line.dataset.id));
    if (item) {
      item.catatan = target.value.slice(0, 255);
      persist();
    }
  });

  document.getElementById('guest-name')?.addEventListener('input', persist);

  document.getElementById('btn-submit-order')?.addEventListener('click', async () => {
    if (cart.length === 0) {
      alert(i18n.select_items || 'Select items');
      return;
    }
    let guestName = '';
    if (fulfillment === 'self_pickup') {
      const nameInput = document.getElementById('guest-name');
      guestName = (nameInput && nameInput.value ? nameInput.value : '').trim();
      if (guestName.length < 2) {
        alert(i18n.guest_name_required || 'Enter your name');
        if (nameInput) nameInput.focus();
        return;
      }
    }
    if (window.TableTapSound) TableTapSound.unlock();
    const btn = document.getElementById('btn-submit-order');
    if (btn) {
      btn.disabled = true;
      btn.textContent = i18n.submitting || 'Submitting...';
    }

    try {
      const res = await fetch(submitUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          meja: meja,
          token: token,
          jenis_hidang: serveType,
          nama_pelanggan: guestName,
          items: cart.map((i) => ({
            menu_item_id: i.id,
            qty: i.qty,
            catatan: i.catatan || '',
          })),
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) {
        throw new Error(data.error || i18n.order_failed);
      }
      try {
        sessionStorage.removeItem('tt_cart_' + meja);
        sessionStorage.removeItem('tt_serve_' + meja);
        sessionStorage.removeItem('tt_name_' + meja);
      } catch (e) { /* ignore */ }
      window.location.href = data.redirect;
    } catch (err) {
      alert(err.message || i18n.order_failed);
      if (btn) {
        btn.disabled = false;
        btn.textContent = i18n.submit_order || 'Submit';
      }
    }
  });

  // Category tab highlight on scroll / click
  document.querySelectorAll('.category-tabs a').forEach((a) => {
    a.addEventListener('click', () => {
      document.querySelectorAll('.category-tabs a').forEach((x) => x.classList.remove('active'));
      a.classList.add('active');
    });
  });

  refresh();
})();
