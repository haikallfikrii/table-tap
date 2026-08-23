/**
 * TableTap — customer cart & order submit
 */
(function () {
  const root = document.getElementById('order-app');
  if (!root) return;

  const meja = root.dataset.meja;
  const token = root.dataset.token;
  const sessionToken = root.dataset.session || '';
  const sessionUrl = root.dataset.sessionUrl || '';
  const cafeBrowse = root.dataset.cafeBrowse === '1';
  const deliveryMode = root.dataset.delivery === '1';
  const shopSlug = root.dataset.shop || '';
  const shopToken = root.dataset.shopToken || '';
  const cafeVerify = root.dataset.cafeVerify || 'email';
  const needsOtp = root.dataset.needsOtp === '1' || cafeVerify === 'email' || cafeVerify === 'email_phone';
  const requirePhone = root.dataset.requirePhone === '1' || cafeVerify === 'phone' || cafeVerify === 'email_phone';
  const checkoutUrl = root.dataset.checkoutUrl || '';
  const sendOtpUrl = root.dataset.sendOtpUrl || '';
  const prefillName = (root.dataset.prefillName || '').trim();
  const tableId = Number(root.dataset.tableId || 0);
  const submitUrl = root.dataset.submitUrl;
  const fulfillment = root.dataset.fulfillment || 'waiter';
  const staffMode = root.dataset.staff === '1';
  const staffFrom = root.dataset.from || 'waiter';
  let payMethods = { counter: true, cod: false, duitnow: false };
  try { payMethods = JSON.parse(root.dataset.payMethods || '{}'); } catch (e) { /* ignore */ }
  const cartKey = sessionToken || (cafeBrowse ? ((deliveryMode ? 'del_' : 'shop_') + shopSlug) : meja);
  const cartStore = (staffMode ? 'tt_staff_cart_' : 'tt_cart_') + cartKey;
  const serveStore = (staffMode ? 'tt_staff_serve_' : 'tt_serve_') + cartKey;
  const nameStore = (staffMode ? 'tt_staff_name_' : 'tt_name_') + cartKey;
  const i18n = JSON.parse(root.dataset.i18n || '{}');

  const money = (n) => {
    const v = Number(n) || 0;
    return 'RM ' + v.toFixed(2);
  };

  /** @type {Array<{id:number,key:string,nama:string,harga:number,qty:number,catatan:string,addon_ids?:number[]}>} */
  let cart = [];
  let serveType = deliveryMode ? 'delivery' : 'dine_in';

  function lineKey(id, addonIds) {
    const sorted = (addonIds || []).slice().sort(function (a, b) { return a - b; });
    return String(id) + ':' + sorted.join(',');
  }

  function cartItemPayload(i) {
    const row = {
      menu_item_id: i.id,
      qty: i.qty,
      catatan: i.catatan || '',
    };
    if (i.addon_ids && i.addon_ids.length) {
      row.addon_ids = i.addon_ids;
    }
    return row;
  }

  function itemHasAddons(item) {
    if (!item || !item.addons) return false;
    const a = item.addons;
    return (Array.isArray(a.choices) && a.choices.length > 0)
      || (Array.isArray(a.extras) && a.extras.length > 0);
  }

  function formatAddonDelta(delta) {
    const v = Number(delta) || 0;
    if (v <= 0) return '';
    return ' (+' + money(v).replace('RM ', 'RM') + ')';
  }

  try {
    const saved = sessionStorage.getItem(cartStore);
    if (saved) {
      cart = JSON.parse(saved) || [];
      cart = cart.map(function (i) {
        if (!i.key) {
          i.key = lineKey(i.id, i.addon_ids || []);
        }
        return i;
      });
    }
    const savedServe = sessionStorage.getItem(serveStore);
    if (savedServe === 'takeaway' || savedServe === 'dine_in') serveType = savedServe;
    const savedName = sessionStorage.getItem(nameStore);
    const nameInput = document.getElementById('guest-name');
    if (nameInput && savedName) nameInput.value = savedName;
  } catch (e) {
    cart = [];
  }

  function persist() {
    try {
      sessionStorage.setItem(cartStore, JSON.stringify(cart));
      sessionStorage.setItem(serveStore, serveType);
      const nameInput = document.getElementById('guest-name');
      if (nameInput) sessionStorage.setItem(nameStore, nameInput.value.trim());
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

  function findLine(key) {
    return cart.find(function (i) { return i.key === key; });
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

    body.innerHTML = cart.map(function (item) {
      return (
        '<div class="cart-line" data-key="' + escapeHtml(item.key) + '">' +
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

  function buildLineFromItem(item, choiceId, extraIds) {
    const addonIds = [];
    if (choiceId) addonIds.push(choiceId);
    (extraIds || []).forEach(function (id) {
      if (id && addonIds.indexOf(id) === -1) addonIds.push(id);
    });
    addonIds.sort(function (a, b) { return a - b; });

    let harga = Number(item.harga) || 0;
    const labels = [];
    const addons = item.addons || { choices: [], extras: [] };

    (addons.choices || []).forEach(function (c) {
      if (c.id === choiceId) {
        harga += Number(c.harga_delta) || 0;
        labels.push(c.nama);
      }
    });
    (addons.extras || []).forEach(function (ex) {
      if (extraIds.indexOf(ex.id) !== -1) {
        harga += Number(ex.harga_delta) || 0;
        labels.push(ex.nama);
      }
    });

    const nama = labels.length ? (item.nama + ' · ' + labels.join(' · ')) : item.nama;
    return {
      id: Number(item.id),
      key: lineKey(item.id, addonIds),
      nama: nama,
      harga: Math.round(harga * 100) / 100,
      qty: 1,
      catatan: '',
      addon_ids: addonIds,
    };
  }

  function addLineToCart(line) {
    const existing = findLine(line.key);
    if (existing) {
      existing.qty += 1;
    } else {
      cart.push(line);
    }
    if (window.TableTapSound) TableTapSound.unlock();
    refresh();
  }

  function addToCart(id, nama, harga) {
    addLineToCart({
      id: id,
      key: lineKey(id, []),
      nama: nama,
      harga: harga,
      qty: 1,
      catatan: '',
      addon_ids: [],
    });
  }

  let detailItem = null;
  let detailChoiceId = 0;
  let detailExtraIds = [];

  function renderAddonOptions(item) {
    const addons = item.addons || { choices: [], extras: [] };
    let html = '';
    if (addons.choices && addons.choices.length) {
      html += '<div class="addon-group"><div class="addon-group-title">' + escapeHtml(i18n.addon_pick || 'Options') + ' <span class="addon-req">' + escapeHtml(i18n.addon_required || 'Required') + '</span></div>';
      addons.choices.forEach(function (c, idx) {
        const checked = detailChoiceId ? (detailChoiceId === c.id) : (idx === 0);
        if (idx === 0 && !detailChoiceId) detailChoiceId = c.id;
        html += '<label class="addon-opt"><input type="radio" name="detail-choice" value="' + c.id + '"' + (checked ? ' checked' : '') + '><span>' + escapeHtml(c.nama) + escapeHtml(formatAddonDelta(c.harga_delta)) + '</span></label>';
      });
      html += '</div>';
    }
    if (addons.extras && addons.extras.length) {
      html += '<div class="addon-group"><div class="addon-group-title">' + escapeHtml(i18n.addon_extras || 'Add-ons') + '</div>';
      addons.extras.forEach(function (ex) {
        const on = detailExtraIds.indexOf(ex.id) !== -1;
        html += '<label class="addon-opt"><input type="checkbox" name="detail-extra" value="' + ex.id + '"' + (on ? ' checked' : '') + '><span>' + escapeHtml(ex.nama) + escapeHtml(formatAddonDelta(ex.harga_delta)) + '</span></label>';
      });
      html += '</div>';
    }
    return html;
  }

  function syncDetailSelection() {
    const choiceEl = document.querySelector('input[name="detail-choice"]:checked');
    detailChoiceId = choiceEl ? Number(choiceEl.value) : 0;
    detailExtraIds = [];
    document.querySelectorAll('input[name="detail-extra"]:checked').forEach(function (el) {
      detailExtraIds.push(Number(el.value));
    });
    const live = document.getElementById('detail-price-live');
    if (live && detailItem) {
      const line = buildLineFromItem(detailItem, detailChoiceId, detailExtraIds);
      live.textContent = money(line.harga);
    }
  }

  function closeDetail() {
    document.getElementById('detail-overlay')?.classList.remove('open');
    document.getElementById('detail-sheet')?.classList.remove('open');
    detailChoiceId = 0;
    detailExtraIds = [];
  }

  function openDetail(data) {
    detailItem = data;
    detailChoiceId = 0;
    detailExtraIds = [];
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
    const addonHtml = itemHasAddons(data) ? renderAddonOptions(data) : '';
    if (body) {
      body.innerHTML = gallery +
        (data.desc ? '<p class="detail-desc">' + escapeHtml(data.desc) + '</p>' : '') +
        addonHtml +
        (!itemHasAddons(data) ? '<p class="detail-price">' + escapeHtml(data.harga_l || money(data.harga)) + '</p>' : '');
    }
    syncDetailSelection();
    if (addBtn) {
      addBtn.disabled = !!data.out;
    }
    document.getElementById('detail-overlay')?.classList.add('open');
    document.getElementById('detail-sheet')?.classList.add('open');
  }

  function handleAddItem(item) {
    if (!item || item.out) return;
    if (itemHasAddons(item)) {
      openDetail(item);
      return;
    }
    addToCart(Number(item.id), item.nama || '', Number(item.harga) || 0);
  }

  document.querySelectorAll('[data-item]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      try {
        handleAddItem(JSON.parse(btn.getAttribute('data-item') || '{}'));
      } catch (err) { /* ignore */ }
    });
  });

  root.addEventListener('click', function (e) {
    const el = e.target.closest('[data-open-detail]');
    if (!el || el.closest('[data-item]')) return;
    try {
      openDetail(JSON.parse(el.getAttribute('data-open-detail') || '{}'));
    } catch (err) { /* ignore */ }
  });

  document.getElementById('detail-body')?.addEventListener('change', function (e) {
    if (e.target && (e.target.matches('input[name="detail-choice"]') || e.target.matches('input[name="detail-extra"]'))) {
      syncDetailSelection();
    }
  });

  document.querySelectorAll('img.menu-item-photo').forEach(function (img) {
    img.addEventListener('error', function () {
      const el = document.createElement('div');
      el.className = img.className + ' placeholder';
      el.setAttribute('aria-hidden', 'true');
      const detail = img.getAttribute('data-open-detail');
      if (detail) el.setAttribute('data-open-detail', detail);
      const letter = (img.getAttribute('alt') || '?').trim().charAt(0);
      el.textContent = letter.toUpperCase();
      img.replaceWith(el);
    });
  });

  document.getElementById('detail-overlay')?.addEventListener('click', closeDetail);
  document.getElementById('btn-close-detail')?.addEventListener('click', closeDetail);
  document.getElementById('detail-add')?.addEventListener('click', function () {
    if (!detailItem || detailItem.out) return;
    if (itemHasAddons(detailItem)) {
      const addons = detailItem.addons || {};
      if (addons.choices && addons.choices.length && !detailChoiceId) {
        alert(i18n.addon_choice_required || 'Pick an option');
        return;
      }
      addLineToCart(buildLineFromItem(detailItem, detailChoiceId, detailExtraIds));
    } else {
      addToCart(Number(detailItem.id), detailItem.nama || '', Number(detailItem.harga) || 0);
    }
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
    const lineKeyVal = line.dataset.key;
    const item = findLine(lineKeyVal);
    if (!item) return;
    const action = target.dataset.action;
    if (action === 'inc') item.qty += 1;
    else if (action === 'dec') {
      item.qty -= 1;
      if (item.qty <= 0) cart = cart.filter(function (i) { return i.key !== lineKeyVal; });
    } else if (action === 'remove') {
      cart = cart.filter(function (i) { return i.key !== lineKeyVal; });
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
    const item = findLine(line.dataset.key);
    if (item) {
      item.catatan = target.value.slice(0, 255);
      persist();
    }
  });

  document.getElementById('guest-name')?.addEventListener('input', persist);

  function resolveGuestName() {
    const nameInput = document.getElementById('guest-name');
    if (nameInput) {
      const v = (nameInput.value || '').trim();
      if (v.length >= 2) return v;
    }
    if (prefillName.length >= 2) return prefillName;
    return '';
  }

  function checkoutGuestName() {
    const n = (checkoutName?.value || '').trim();
    if (n.length >= 2) return n;
    const email = (checkoutEmail?.value || '').trim();
    if (email.includes('@')) {
      const local = email.split('@')[0].trim();
      if (local.length >= 2) return local;
    }
    return n;
  }

  document.getElementById('btn-submit-order')?.addEventListener('click', async () => {
    if (cart.length === 0) {
      alert(i18n.select_items || 'Select items');
      return;
    }
    const guestName = resolveGuestName();
    // Session sudah ada nama — server guna nama sesi; jangan block di client
    if (fulfillment === 'self_pickup' && !cafeBrowse && !sessionToken && guestName.length < 2) {
      alert(i18n.guest_name_required || 'Enter your name');
      document.getElementById('guest-name')?.focus();
      return;
    }
    if (cafeBrowse) {
      openCheckoutSheet();
      return;
    }
    await submitOrder(guestName || resolveGuestName());
  });

  async function submitOrder(guestName) {
    if (!staffMode && window.TableTapSound) TableTapSound.unlock();
    const btn = document.getElementById('btn-submit-order');
    if (btn) {
      btn.disabled = true;
      btn.textContent = i18n.submitting || 'Submitting...';
    }
    try {
      const payload = {
        jenis_hidang: serveType,
        nama_pelanggan: guestName,
        items: cart.map(cartItemPayload),
      };
      if (sessionToken) {
        payload.session = sessionToken;
      } else {
        payload.meja = meja;
        payload.token = token;
        payload.table_id = tableId;
        payload.from = staffFrom;
      }
      const res = await fetch(submitUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || i18n.order_failed);
      clearCartStorage();
      window.location.href = data.redirect;
    } catch (err) {
      alert(err.message || i18n.order_failed);
      if (btn) {
        btn.disabled = false;
        btn.textContent = i18n.submit_order || 'Submit';
      }
    }
  }

  function clearCartStorage() {
    try {
      sessionStorage.removeItem(cartStore);
      sessionStorage.removeItem(serveStore);
      sessionStorage.removeItem(nameStore);
    } catch (e) { /* ignore */ }
  }

  const checkoutSheet = document.getElementById('checkout-sheet');
  const checkoutOverlay = document.getElementById('checkout-overlay');
  const checkoutEmail = document.getElementById('checkout-email');
  const checkoutPhone = document.getElementById('checkout-phone');
  const checkoutAddress = document.getElementById('checkout-address');
  const checkoutName = document.getElementById('checkout-name');
  const checkoutOtp = document.getElementById('checkout-otp');
  const checkoutStepDetails = document.getElementById('checkout-step-details');
  const checkoutStepOtp = document.getElementById('checkout-step-otp');
  const checkoutOtpSent = document.getElementById('checkout-otp-sent');
  const duitnowPreview = document.getElementById('duitnow-preview');

  function selectedPayMethod() {
    const el = document.querySelector('input[name="pay_method"]:checked');
    return el ? el.value : 'cod';
  }

  function syncDuitnowPreview() {
    if (!duitnowPreview) return;
    const show = selectedPayMethod() === 'duitnow';
    duitnowPreview.classList.toggle('is-collapsed', !show);
    duitnowPreview.setAttribute('aria-hidden', show ? 'false' : 'true');
  }

  document.querySelectorAll('input[name="pay_method"]').forEach(function (el) {
    el.addEventListener('change', syncDuitnowPreview);
  });
  syncDuitnowPreview();

  function openCheckoutSheet() {
    document.getElementById('cart-sheet')?.classList.remove('open');
    document.getElementById('sheet-overlay')?.classList.remove('open');
    checkoutSheet?.classList.add('open');
    checkoutOverlay?.classList.add('open');
    if (checkoutStepDetails) checkoutStepDetails.hidden = false;
    if (checkoutStepOtp) checkoutStepOtp.hidden = true;
    if (checkoutOtp) checkoutOtp.value = '';
    syncDuitnowPreview();
    (checkoutName || checkoutPhone || checkoutEmail)?.focus();
  }

  function closeCheckoutSheet() {
    checkoutSheet?.classList.remove('open');
    checkoutOverlay?.classList.remove('open');
  }

  document.getElementById('btn-close-checkout')?.addEventListener('click', closeCheckoutSheet);
  checkoutOverlay?.addEventListener('click', closeCheckoutSheet);

  function validEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  }

  function validPhone(v) {
    const d = String(v || '').replace(/\D/g, '');
    return d.length >= 9 && d.length <= 15;
  }

  async function runCafeCheckout(code) {
    const guestName = checkoutGuestName();
    if ((fulfillment === 'self_pickup' || deliveryMode) && guestName.length < 2) {
      throw new Error(i18n.guest_name_required || 'Enter your name');
    }
    if (deliveryMode) {
      const address = (checkoutAddress?.value || '').trim();
      if (address.length < 8) {
        throw new Error(i18n.address_required || 'Enter address');
      }
      if (requirePhone && !validPhone(checkoutPhone?.value || '')) {
        throw new Error(i18n.phone_required || 'Enter phone');
      }
      if (!validEmail((checkoutEmail?.value || '').trim())) {
        throw new Error(i18n.cafe_email_invalid || 'Invalid email');
      }
      const pay = selectedPayMethod();
      if (!payMethods[pay]) {
        throw new Error(i18n.pay_method_required || 'Select payment');
      }
      const res = await fetch(checkoutUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          shop: shopSlug,
          token: shopToken,
          nama_pelanggan: guestName,
          email: (checkoutEmail?.value || '').trim(),
          phone: (checkoutPhone?.value || '').trim(),
          code: code || '',
          alamat: address,
          payment_method: pay,
          items: cart.map(cartItemPayload),
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || i18n.order_failed);
      clearCartStorage();
      window.location.href = data.redirect;
      return;
    }
    if (requirePhone && !validPhone(checkoutPhone?.value || '')) {
      throw new Error(i18n.phone_required || 'Enter phone');
    }
    const res = await fetch(checkoutUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        shop: shopSlug,
        token: shopToken,
        nama_pelanggan: guestName,
        email: (checkoutEmail?.value || '').trim(),
        phone: (checkoutPhone?.value || '').trim(),
        code: code || '',
        jenis_hidang: serveType,
        items: cart.map(cartItemPayload),
      }),
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || i18n.order_failed);
    clearCartStorage();
    window.location.href = data.redirect;
  }

  document.getElementById('btn-checkout-send')?.addEventListener('click', async function () {
    const name = checkoutGuestName();
    const email = (checkoutEmail?.value || '').trim();
    if ((fulfillment === 'self_pickup' || deliveryMode) && name.length < 2) {
      alert(i18n.guest_name_required || 'Enter name');
      checkoutName?.focus();
      return;
    }
    if (deliveryMode) {
      const address = (checkoutAddress?.value || '').trim();
      if (address.length < 8) {
        alert(i18n.address_required || 'Enter address');
        checkoutAddress?.focus();
        return;
      }
      if (requirePhone && !validPhone(checkoutPhone?.value || '')) {
        alert(i18n.phone_required || 'Enter phone');
        checkoutPhone?.focus();
        return;
      }
    } else if (requirePhone && !validPhone(checkoutPhone?.value || '')) {
      alert(i18n.phone_required || 'Enter phone');
      checkoutPhone?.focus();
      return;
    }
    if (needsOtp) {
      if (!validEmail(email)) {
        alert(i18n.cafe_email_invalid || 'Invalid email');
        checkoutEmail?.focus();
        return;
      }
      const btn = document.getElementById('btn-checkout-send');
      if (btn) { btn.disabled = true; btn.textContent = i18n.cafe_sending || 'Sending...'; }
      try {
        const res = await fetch(sendOtpUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({
            shop: shopSlug,
            token: shopToken,
            email: email,
            nama_pelanggan: name || email.split('@')[0],
            lang: document.documentElement.lang === 'en' ? 'en' : 'my',
          }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.error || i18n.order_failed);
        if (checkoutOtpSent) checkoutOtpSent.textContent = data.email_masked || email;
        if (checkoutStepDetails) checkoutStepDetails.hidden = true;
        if (checkoutStepOtp) checkoutStepOtp.hidden = false;
        checkoutOtp?.focus();
      } catch (err) {
        alert(err.message || i18n.order_failed);
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = i18n.cafe_send_code || 'Send code'; }
      }
      return;
    }
    const btn = document.getElementById('btn-checkout-send');
    if (btn) { btn.disabled = true; btn.textContent = i18n.submitting || 'Submitting...'; }
    try {
      await runCafeCheckout('');
    } catch (err) {
      alert(err.message || i18n.order_failed);
      if (btn) { btn.disabled = false; btn.textContent = i18n.cafe_confirm_order || 'Confirm'; }
    }
  });

  document.getElementById('btn-checkout-verify')?.addEventListener('click', async function () {
    const code = (checkoutOtp?.value || '').trim();
    if (code.length !== 6) {
      alert(i18n.cafe_otp_required || 'Enter code');
      checkoutOtp?.focus();
      return;
    }
    const btn = document.getElementById('btn-checkout-verify');
    if (btn) { btn.disabled = true; btn.textContent = i18n.submitting || 'Submitting...'; }
    try {
      await runCafeCheckout(code);
    } catch (err) {
      alert(err.message || i18n.order_failed);
      if (btn) { btn.disabled = false; btn.textContent = i18n.cafe_confirm_order || 'Confirm'; }
    }
  });

  // Category tab highlight on scroll / click
  const categoryTabs = document.getElementById('category-tabs');
  const stickyBar = document.getElementById('menu-sticky-bar');
  const customerHeader = document.querySelector('.customer-header');

  function syncStickyBarTop() {
    if (customerHeader && stickyBar) {
      stickyBar.style.top = customerHeader.offsetHeight + 'px';
    }
  }

  syncStickyBarTop();
  window.addEventListener('resize', syncStickyBarTop, { passive: true });

  categoryTabs?.querySelectorAll('a').forEach((a) => {
    a.addEventListener('click', () => {
      categoryTabs.querySelectorAll('a').forEach((x) => x.classList.remove('active'));
      a.classList.add('active');
      a.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
    });
  });

  // Menu search — compact toggle + filter
  const searchToggle = document.getElementById('menu-search-toggle');
  const searchPanel = document.getElementById('menu-search-panel');
  const searchInput = document.getElementById('menu-search');
  const searchClear = document.getElementById('menu-search-clear');
  const searchEmpty = document.getElementById('menu-search-empty');

  function normalizeSearch(value) {
    return (value || '').trim().toLowerCase().replace(/\s+/g, ' ');
  }

  function setSearchOpen(open) {
    if (!searchPanel || !searchToggle) return;
    searchPanel.classList.toggle('is-open', open);
    searchPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
    searchToggle.classList.toggle('is-active', open);
    searchToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    stickyBar?.classList.toggle('is-search-open', open);
    if (open) {
      syncStickyBarTop();
      window.setTimeout(() => searchInput?.focus(), 120);
    } else if (searchInput && searchInput.value !== '') {
      searchInput.value = '';
      applyMenuSearch();
    }
  }

  function applyMenuSearch() {
    const q = normalizeSearch(searchInput?.value);
    const isSearching = q.length > 0;
    let totalVisible = 0;

    document.querySelectorAll('.menu-item').forEach(function (el) {
      const text = normalizeSearch(el.getAttribute('data-search') || '');
      const show = !isSearching || text.indexOf(q) !== -1;
      el.classList.toggle('is-filtered-out', !show);
      if (show) totalVisible++;
    });

    document.querySelectorAll('.menu-section').forEach(function (sec) {
      const count = sec.querySelectorAll('.menu-item:not(.is-filtered-out)').length;
      sec.classList.toggle('is-filtered-out', isSearching && count === 0);
      sec.classList.toggle('is-searching', isSearching && count > 0);
    });

    if (searchEmpty) {
      searchEmpty.hidden = !isSearching || totalVisible > 0;
    }
    if (searchClear) {
      searchClear.hidden = !isSearching;
    }
    if (categoryTabs) {
      categoryTabs.classList.toggle('is-searching', isSearching);
    }
    if (searchToggle) {
      searchToggle.classList.toggle('is-active', isSearching || searchPanel?.classList.contains('is-open'));
    }
    if (isSearching) {
      categoryTabs?.querySelectorAll('a').forEach(function (a) {
        a.classList.remove('active');
      });
    }
  }

  searchToggle?.addEventListener('click', function () {
    const open = !searchPanel?.classList.contains('is-open');
    setSearchOpen(open);
  });

  searchClear?.addEventListener('click', function () {
    if (searchInput) searchInput.value = '';
    applyMenuSearch();
    searchInput?.focus();
  });

  if (searchInput) {
    searchInput.addEventListener('input', applyMenuSearch);
    searchInput.addEventListener('search', applyMenuSearch);
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && searchPanel?.classList.contains('is-open')) {
      setSearchOpen(false);
    }
  });

  // Hide category/search bar on scroll down, show on scroll up
  let lastScrollY = window.scrollY;
  let scrollTicking = false;

  function onMenuScroll() {
    const y = window.scrollY;
    const scrollingDown = y > lastScrollY + 6;
    const scrollingUp = y < lastScrollY - 6;
    const pastHeader = y > (customerHeader?.offsetHeight || 56);

    stickyBar?.classList.toggle('is-scrolled', y > 8);
    if (pastHeader && scrollingDown && !searchPanel?.classList.contains('is-open')) {
      stickyBar?.classList.add('is-bar-hidden');
    } else if (scrollingUp || y <= (customerHeader?.offsetHeight || 56)) {
      stickyBar?.classList.remove('is-bar-hidden');
    }
    lastScrollY = y;
    scrollTicking = false;
  }

  window.addEventListener('scroll', function () {
    if (!scrollTicking) {
      scrollTicking = true;
      window.requestAnimationFrame(onMenuScroll);
    }
  }, { passive: true });

  // Cafe mode — private order link + QR
  const linkBtn = document.getElementById('btn-my-link');
  const linkSheet = document.getElementById('link-sheet');
  const linkOverlay = document.getElementById('link-overlay');
  const linkUrlInput = document.getElementById('cafe-link-url');
  const linkQrImg = document.getElementById('cafe-link-qr');
  const copyLinkBtn = document.getElementById('btn-copy-link');

  function openLinkSheet() {
    if (!linkSheet || !sessionUrl) return;
    if (linkQrImg) {
      linkQrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(sessionUrl);
    }
    if (linkUrlInput) linkUrlInput.value = sessionUrl;
    linkSheet.classList.add('open');
    linkOverlay?.classList.add('open');
  }

  function closeLinkSheet() {
    linkSheet?.classList.remove('open');
    linkOverlay?.classList.remove('open');
  }

  linkBtn?.addEventListener('click', openLinkSheet);
  document.getElementById('btn-close-link')?.addEventListener('click', closeLinkSheet);
  linkOverlay?.addEventListener('click', closeLinkSheet);
  copyLinkBtn?.addEventListener('click', async function () {
    if (!sessionUrl) return;
    try {
      await navigator.clipboard.writeText(sessionUrl);
      alert(i18n.cafe_link_copied || 'Link copied');
    } catch (e) {
      if (linkUrlInput) {
        linkUrlInput.select();
        document.execCommand('copy');
        alert(i18n.cafe_link_copied || 'Link copied');
      }
    }
  });

  refresh();
})();
