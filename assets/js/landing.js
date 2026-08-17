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

  /* ---------- Case study: waiter vs pickup ---------- */
  var casePlay = document.getElementById('case-play');
  var caseBtns = document.querySelectorAll('[data-case]');
  var lpHero = document.getElementById('lp-track-hero');
  var lpTitle = document.getElementById('lp-track-title');
  var lpWaiter = document.getElementById('lp-waiter-hero');
  var lpWaiterTitle = document.getElementById('lp-waiter-title');
  var lpWaiterBadge = document.getElementById('lp-waiter-badge');
  var lpWaiterCard = document.getElementById('lp-waiter-card');
  var waiterTimer = null;
  var waiterIdx = 0;
  var waiterStages = ['scan', 'send', 'cook', 'serve'];

  function setWaiterStage(stage) {
    if (!lpWaiter) return;
    lpWaiter.setAttribute('data-stage', stage);
    if (lpWaiterTitle) {
      lpWaiterTitle.textContent = lpWaiter.getAttribute('data-t-' + stage) || '';
    }
    if (lpWaiterBadge) {
      lpWaiterBadge.textContent = lpWaiter.getAttribute('data-b-' + stage) || '';
      lpWaiterBadge.className = 'badge ' + (stage === 'serve' ? 'green' : (stage === 'cook' ? 'blue' : 'amber'));
    }
    if (lpWaiterCard) {
      lpWaiterCard.classList.toggle('cook', stage === 'cook');
      lpWaiterCard.classList.toggle('serve', stage === 'serve');
    }
    document.querySelectorAll('[data-w-step]').forEach(function (el) {
      var step = el.getAttribute('data-w-step');
      var i = waiterStages.indexOf(step);
      var now = waiterStages.indexOf(stage);
      el.classList.toggle('is-current', step === stage);
      el.classList.toggle('is-done', i < now);
    });
  }

  function stopWaiterLoop() {
    if (waiterTimer) {
      clearInterval(waiterTimer);
      waiterTimer = null;
    }
  }

  function startWaiterLoop() {
    stopWaiterLoop();
    waiterIdx = 0;
    setWaiterStage(waiterStages[0]);
    if (reduceMotion) return;
    waiterTimer = setInterval(function () {
      waiterIdx = (waiterIdx + 1) % waiterStages.length;
      setWaiterStage(waiterStages[waiterIdx]);
    }, 1400);
  }
  var pickupTimer = null;
  var pickupIdx = 0;
  var pickupStages = ['queue', 'cooking', 'ready', 'done'];

  function setPickupStage(stage) {
    if (!lpHero) return;
    lpHero.setAttribute('data-stage', stage);
    if (lpTitle) {
      lpTitle.textContent = lpHero.getAttribute('data-t-' + stage) || '';
    }
    document.querySelectorAll('[data-lp-step]').forEach(function (el) {
      var step = el.getAttribute('data-lp-step');
      var order = pickupStages;
      var i = order.indexOf(step);
      var now = order.indexOf(stage);
      el.classList.toggle('is-current', step === stage);
      el.classList.toggle('is-done', i < now);
    });
  }

  function stopPickupLoop() {
    if (pickupTimer) {
      clearInterval(pickupTimer);
      pickupTimer = null;
    }
  }

  function startPickupLoop() {
    stopPickupLoop();
    pickupIdx = 0;
    setPickupStage(pickupStages[0]);
    if (reduceMotion) return;
    pickupTimer = setInterval(function () {
      pickupIdx = (pickupIdx + 1) % pickupStages.length;
      setPickupStage(pickupStages[pickupIdx]);
    }, 1400);
  }

  function setCaseMode(mode) {
    if (!casePlay) return;
    casePlay.setAttribute('data-mode', mode);
    caseBtns.forEach(function (b) {
      var on = b.getAttribute('data-case') === mode;
      b.classList.toggle('on', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    if (mode === 'pickup') {
      stopWaiterLoop();
      stopCafeLoop();
      startPickupLoop();
    } else if (mode === 'cafe') {
      stopPickupLoop();
      stopWaiterLoop();
      startCafeLoop();
    } else {
      stopPickupLoop();
      stopCafeLoop();
      startWaiterLoop();
    }
  }

  caseBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      setCaseMode(btn.getAttribute('data-case') || 'waiter');
    });
  });

  if (casePlay && 'IntersectionObserver' in window) {
    var caseObs = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) {
          stopPickupLoop();
          stopWaiterLoop();
          stopCafeLoop();
          return;
        }
        var mode = casePlay.getAttribute('data-mode');
        if (mode === 'pickup') startPickupLoop();
        else if (mode === 'cafe') startCafeLoop();
        else startWaiterLoop();
      });
    }, { threshold: 0.25 });
    caseObs.observe(casePlay);
  }

  /* ---------- Cafe case loop ---------- */
  var lpCafe = document.getElementById('lp-cafe-hero');
  var lpCafeTitle = document.getElementById('lp-cafe-title');
  var cafeTimer = null;
  var cafeIdx = 0;
  var cafeStages = ['scan', 'otp', 'track', 'receipt'];
  var cafeTitles = cafeStages.map(function (s) {
    var el = document.querySelector('[data-c-step="' + s + '"]');
    return el ? el.textContent : s;
  });

  function setCafeStage(stage) {
    if (!lpCafe) return;
    lpCafe.setAttribute('data-stage', stage);
    var i = cafeStages.indexOf(stage);
    if (lpCafeTitle && cafeTitles[i]) lpCafeTitle.textContent = cafeTitles[i];
    document.querySelectorAll('[data-c-step]').forEach(function (el) {
      var step = el.getAttribute('data-c-step');
      var si = cafeStages.indexOf(step);
      el.classList.toggle('is-current', step === stage);
      el.classList.toggle('is-done', si >= 0 && si < i);
    });
  }

  function stopCafeLoop() {
    if (cafeTimer) { clearInterval(cafeTimer); cafeTimer = null; }
  }

  function startCafeLoop() {
    stopCafeLoop();
    cafeIdx = 0;
    setCafeStage(cafeStages[0]);
    if (reduceMotion) return;
    cafeTimer = setInterval(function () {
      cafeIdx = (cafeIdx + 1) % cafeStages.length;
      setCafeStage(cafeStages[cafeIdx]);
    }, 1400);
  }

  /* ---------- All-in-one peek animation ---------- */
  var aioStage = document.getElementById('aio-stage');
  var aioStatus = document.getElementById('aio-status');
  var aioChips = aioStage ? aioStage.querySelectorAll('.aio-chip') : [];
  var toolKeys = ['pos', 'calc', 'paper'];
  var toolIdx = 0;
  var toolTimer = null;
  var printerTimer = null;
  var aioLabels = {};

  aioChips.forEach(function (chip) {
    var k = chip.getAttribute('data-aio');
    var lab = chip.querySelector('.aio-label');
    if (k && lab) aioLabels[k] = lab.textContent;
  });

  function aioStatusText(key) {
    if (!aioStatus || !aioLabels[key]) return;
    var prefix = document.documentElement.lang === 'en' ? 'No longer needed: ' : 'Tak perlu lagi: ';
    aioStatus.textContent = prefix + aioLabels[key];
  }

  function peekAio(key) {
    aioChips.forEach(function (chip) {
      chip.classList.remove('is-peek', 'is-struck');
    });
    var chip = aioStage.querySelector('.aio-chip[data-aio="' + key + '"]');
    if (!chip) return;
    void chip.offsetWidth;
    chip.classList.add('is-peek', 'is-struck');
    aioStatusText(key);
  }

  function stopAioLoops() {
    if (toolTimer) { clearInterval(toolTimer); toolTimer = null; }
    if (printerTimer) { clearInterval(printerTimer); printerTimer = null; }
    aioChips.forEach(function (chip) {
      chip.classList.remove('is-peek', 'is-struck');
    });
  }

  function startAioLoops() {
    stopAioLoops();
    if (reduceMotion) {
      peekAio('pos');
      return;
    }
    peekAio(toolKeys[0]);
    toolTimer = setInterval(function () {
      toolIdx = (toolIdx + 1) % toolKeys.length;
      peekAio(toolKeys[toolIdx]);
    }, 3800);
    printerTimer = setInterval(function () {
      peekAio('printer');
    }, 7600);
    setTimeout(function () {
      peekAio('printer');
    }, 1900);
  }

  if (aioStage && aioChips.length) {
    aioChips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        var k = chip.getAttribute('data-aio');
        if (!k) return;
        if (k !== 'printer') {
          toolIdx = toolKeys.indexOf(k);
          if (toolIdx < 0) return;
        }
        stopAioLoops();
        peekAio(k);
        if (!reduceMotion) {
          toolTimer = setInterval(function () {
            toolIdx = (toolIdx + 1) % toolKeys.length;
            peekAio(toolKeys[toolIdx]);
          }, 3800);
          printerTimer = setInterval(function () {
            peekAio('printer');
          }, 7600);
        }
      });
    });
    if ('IntersectionObserver' in window) {
      var aioObs = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) startAioLoops();
          else stopAioLoops();
        });
      }, { threshold: 0.2 });
      aioObs.observe(aioStage);
    } else {
      startAioLoops();
    }
  }

  document.querySelectorAll('.card[data-feat]').forEach(function (card) {
    card.addEventListener('click', function () {
      card.classList.remove('bump');
      void card.offsetWidth;
      card.classList.add('bump');
    });
  });

})();
