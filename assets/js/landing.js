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
  var perLabels = document.querySelectorAll('[data-per]');

  function formatPrice(value) {
    return Number(value).toLocaleString('en-MY');
  }

  function setBilling(mode) {
    billingBtns.forEach(function (b) {
      b.classList.toggle('on', b.getAttribute('data-billing') === mode);
      b.setAttribute('aria-pressed', b.getAttribute('data-billing') === mode ? 'true' : 'false');
    });

    priceEls.forEach(function (el) {
      var next = mode === 'yearly'
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

    perLabels.forEach(function (el) {
      el.textContent = mode === 'yearly'
        ? el.getAttribute('data-per-yearly')
        : el.getAttribute('data-per-monthly');
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

      // Keep the chosen tab visible inside the horizontal scroller on phones.
      if (tab.scrollIntoView) {
        tab.scrollIntoView({
          behavior: reduceMotion ? 'auto' : 'smooth',
          block: 'nearest',
          inline: 'center'
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
})();
