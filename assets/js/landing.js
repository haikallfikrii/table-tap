/**
 * TableTap — landing page interactions
 * Vanilla JS only: nav, scroll reveal, counters, pricing toggle, tabs, FAQ.
 */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Sticky nav shadow ---------- */
  var nav = document.querySelector('.lp-nav');
  function onScroll() {
    if (!nav) return;
    nav.classList.toggle('scrolled', window.scrollY > 8);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- Mobile menu ---------- */
  var burger = document.getElementById('lp-burger');
  var mobileMenu = document.getElementById('lp-mobile-menu');
  if (burger && mobileMenu) {
    burger.addEventListener('click', function () {
      var open = mobileMenu.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    mobileMenu.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') {
        mobileMenu.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---------- Scroll reveal ---------- */
  var revealTargets = document.querySelectorAll('.reveal, .step');
  if ('IntersectionObserver' in window && !reduceMotion) {
    var revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('in-view');
        revealObserver.unobserve(entry.target);
      });
    }, { threshold: 0.16, rootMargin: '0px 0px -60px 0px' });

    revealTargets.forEach(function (el, i) {
      el.style.transitionDelay = Math.min(i % 4, 3) * 70 + 'ms';
      revealObserver.observe(el);
    });
  } else {
    revealTargets.forEach(function (el) { el.classList.add('in-view'); });
  }

  /* ---------- Pricing billing toggle ---------- */
  var billingBtns = document.querySelectorAll('[data-billing]');
  var priceEls = document.querySelectorAll('[data-monthly]');
  var yearNotes = document.querySelectorAll('.price-year');

  function formatPrice(value) {
    return Number(value).toLocaleString('en-MY');
  }

  function setBilling(mode) {
    var yearly = mode === 'yearly';
    billingBtns.forEach(function (b) {
      b.classList.toggle('on', b.getAttribute('data-billing') === mode);
      b.setAttribute('aria-pressed', b.getAttribute('data-billing') === mode ? 'true' : 'false');
    });

    priceEls.forEach(function (el) {
      var next = yearly
        ? el.getAttribute('data-yearly')
        : el.getAttribute('data-monthly');
      if (!next) return;
      el.textContent = formatPrice(next);
      if (!reduceMotion) {
        el.animate(
          [
            { opacity: 0.25, transform: 'translateY(-6px)' },
            { opacity: 1, transform: 'none' }
          ],
          { duration: 260, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' }
        );
      }
    });

    yearNotes.forEach(function (el) {
      el.hidden = !yearly;
    });
  }

  billingBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      setBilling(btn.getAttribute('data-billing'));
    });
  });

  /* ---------- Demo tabs ---------- */
  var tabs = document.querySelectorAll('.demo-tab');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var target = tab.getAttribute('data-pane');
      tabs.forEach(function (t) {
        var on = t === tab;
        t.classList.toggle('on', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      document.querySelectorAll('.demo-pane').forEach(function (pane) {
        pane.classList.toggle('on', pane.id === target);
      });

      // Scroll only inside .demo-tabs — never scrollIntoView (that shifts the whole page).
      var scroller = tab.parentElement;
      if (scroller && scroller.scrollWidth > scroller.clientWidth + 1) {
        var left = tab.offsetLeft - (scroller.clientWidth - tab.offsetWidth) / 2;
        scroller.scrollTo({
          left: Math.max(0, left),
          behavior: reduceMotion ? 'auto' : 'smooth'
        });
      }
    });
  });

  /* ---------- FAQ accordion ---------- */
  document.querySelectorAll('.qa-q').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item = btn.closest('.qa');
      var answer = item.querySelector('.qa-a');
      var isOpen = item.classList.contains('open');

      document.querySelectorAll('.qa.open').forEach(function (other) {
        other.classList.remove('open');
        other.querySelector('.qa-a').style.maxHeight = '0px';
        other.querySelector('.qa-q').setAttribute('aria-expanded', 'false');
      });

      if (!isOpen) {
        item.classList.add('open');
        answer.style.maxHeight = answer.scrollHeight + 'px';
        btn.setAttribute('aria-expanded', 'true');
      }
    });
  });

  /* ---------- Footer year ---------- */
  var yearEl = document.getElementById('lp-year');
  if (yearEl) yearEl.textContent = String(new Date().getFullYear());

  /* ---------- Hero phone story loop ---------- */
  var hero = document.getElementById('hero-phone');
  var heroCap = document.getElementById('hero-phone-caption');
  var cartBar = document.getElementById('hero-cart-bar');
  var cartN = document.getElementById('hero-cart-n');
  var cartRm = document.getElementById('hero-cart-rm');
  var prices = [8, 7, 2.5];
  var qty = [0, 0, 0];
  var heroTimer = 0;

  function heroCaption(scene) {
    if (!hero || !heroCap) return;
    heroCap.textContent = hero.getAttribute('data-cap-' + scene) || '';
  }

  function setHeroScene(scene) {
    if (!hero) return;
    hero.setAttribute('data-scene', scene);
    heroCaption(scene);
  }

  function bumpCart() {
    var n = qty[0] + qty[1] + qty[2];
    var rm = qty[0] * prices[0] + qty[1] * prices[1] + qty[2] * prices[2];
    if (cartN) cartN.textContent = String(n);
    if (cartRm) cartRm.textContent = 'RM ' + rm.toFixed(2);
    if (cartBar) cartBar.classList.toggle('show', n > 0);
  }

  function tapItem(i) {
    var el = hero && hero.querySelector('.ps-item[data-tap="' + i + '"]');
    if (!el) return;
    el.classList.add('hit');
    qty[i] += 1;
    bumpCart();
    setTimeout(function () { el.classList.remove('hit'); }, 420);
  }

  function resetHeroCart() {
    qty = [0, 0, 0];
    bumpCart();
    if (hero) {
      hero.querySelectorAll('.ps-item.hit').forEach(function (el) {
        el.classList.remove('hit');
      });
    }
  }

  function later(ms) {
    return new Promise(function (resolve) {
      heroTimer = window.setTimeout(resolve, ms);
    });
  }

  async function heroLoop() {
    if (!hero) return;
    while (true) {
      resetHeroCart();
      setHeroScene('scan');
      await later(reduceMotion ? 1600 : 2200);

      setHeroScene('menu');
      if (!reduceMotion) {
        await later(500);
        tapItem(0);
        await later(520);
        tapItem(0);
        await later(520);
        tapItem(2);
        await later(900);
      } else {
        qty = [2, 0, 1];
        bumpCart();
        await later(1600);
      }

      setHeroScene('cart');
      await later(reduceMotion ? 1600 : 2400);

      setHeroScene('done');
      await later(reduceMotion ? 1600 : 2200);
    }
  }

  if (hero) {
    heroLoop();
  }

  /* ---------- Desktop cinema loop ---------- */
  var cinema = document.getElementById('desk-cinema');
  var cinemaTitle = document.getElementById('cinema-title');
  if (cinema) {
    var scenes = cinema.querySelectorAll('.tb-scene');
    var ci = 0;
    function cinemaTick() {
      ci = (ci + 1) % scenes.length;
      scenes.forEach(function (s, i) { s.classList.toggle('on', i === ci); });
      var next = scenes[ci];
      if (cinemaTitle && next) {
        cinemaTitle.textContent = next.getAttribute('data-title') || '';
      }
    }
    setInterval(cinemaTick, reduceMotion ? 4000 : 2800);
  }

  /* ---------- Interactive demo + real dashboard sound ---------- */
  var proto = document.getElementById('demo-proto');
  var Sound = window.TableTapSound;
  var originals = {};

  if (proto) {
    proto.querySelectorAll('[data-demo-card]').forEach(function (card) {
      originals[card.getAttribute('data-demo-card')] = card.outerHTML;
    });
  }

  function ping() {
    if (!Sound) return;
    Sound.unlock();
    Sound.beep();
  }

  function restoreCard(id, delay) {
    window.setTimeout(function () {
      var live = proto && proto.querySelector('[data-demo-card="' + id + '"]');
      if (!live || !originals[id]) return;
      live.outerHTML = originals[id];
    }, delay || 4200);
  }

  if (proto) {
    proto.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-demo-act]');
      if (!btn) return;
      var card = btn.closest('[data-demo-card]');
      var act = btn.getAttribute('data-demo-act');
      ping();

      if (act === 'pay' && card) {
        card.classList.remove('unpaid');
        card.classList.add('done');
        var unpaid = card.querySelector('[data-unpaid]');
        if (unpaid) unpaid.remove();
        btn.disabled = true;
        btn.textContent = '✓';
        restoreCard(card.getAttribute('data-demo-card'));
      }

      if (act === 'cook' && card) {
        card.classList.remove('menunggu');
        card.classList.add('sedang_dimasak');
        btn.disabled = true;
      }

      if (act === 'ready' && card) {
        card.classList.remove('menunggu', 'sedang_dimasak');
        card.classList.add('siap', 'done');
        var acts = card.querySelector('.kitchen-actions');
        if (acts) acts.remove();
        restoreCard(card.getAttribute('data-demo-card'));
      }

      if (act === 'pickup' && card) {
        card.classList.remove('siap');
        card.classList.add('diambil', 'done');
        var waitActs = card.querySelector('.kitchen-actions');
        if (waitActs) waitActs.remove();
        restoreCard(card.getAttribute('data-demo-card'));
      }
    });
  }

})();
