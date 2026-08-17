/**
 * TableTap — blog interactivity (search, filter, TOC, reading progress, share)
 */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Index: search & category filter ---------- */
  var searchInput = document.getElementById('blog-search');
  var filterBtns = document.querySelectorAll('.blog-filter');
  var cards = document.querySelectorAll('.blog-card, .blog-featured');
  var noResults = document.getElementById('blog-no-results');
  var activeFilter = 'all';

  function normalize(s) {
    return (s || '').toLowerCase().trim();
  }

  function applyFilters() {
    if (!cards.length) return;
    var q = searchInput ? normalize(searchInput.value) : '';
    var visible = 0;
    cards.forEach(function (card) {
      var cat = card.getAttribute('data-category') || '';
      var title = card.getAttribute('data-title') || '';
      var excerpt = card.getAttribute('data-excerpt') || '';
      var catOk = activeFilter === 'all' || cat === activeFilter;
      var textOk = !q || title.indexOf(q) !== -1 || excerpt.indexOf(q) !== -1;
      var show = catOk && textOk;
      card.hidden = !show;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (noResults) {
      noResults.hidden = visible > 0;
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
  }

  filterBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      activeFilter = btn.getAttribute('data-filter') || 'all';
      filterBtns.forEach(function (b) {
        b.classList.toggle('active', b === btn);
      });
      applyFilters();
    });
  });

  /* Pre-select category from URL ?cat= */
  var params = new URLSearchParams(window.location.search);
  var catParam = params.get('cat');
  if (catParam) {
    filterBtns.forEach(function (btn) {
      if (btn.getAttribute('data-filter') === catParam) {
        btn.click();
      }
    });
  }

  /* ---------- Single: fixed TOC sidebar ---------- */
  var tocAside = document.querySelector('.blog-toc-aside');
  var tocPanel = document.getElementById('blog-toc-panel');
  var singleLayout = document.querySelector('.blog-single-layout');
  var navOffset = 88;

  function pinToc() {
    if (!tocAside || !tocPanel) return;

    if (window.innerWidth < 960) {
      tocPanel.style.cssText = '';
      tocAside.classList.remove('is-at-bottom');
      tocAside.style.minHeight = '';
      return;
    }

    var asideRect = tocAside.getBoundingClientRect();
    var panelH = tocPanel.offsetHeight;
    var layoutBottom = singleLayout
      ? singleLayout.getBoundingClientRect().bottom
      : asideRect.bottom;

    tocPanel.style.position = 'fixed';
    tocPanel.style.top = navOffset + 'px';
    tocPanel.style.left = asideRect.left + 'px';
    tocPanel.style.width = asideRect.width + 'px';

    var pinBottom = layoutBottom - panelH - 16 < navOffset;
    tocAside.classList.toggle('is-at-bottom', pinBottom);
    tocAside.style.minHeight = pinBottom ? panelH + 'px' : '';

    if (pinBottom) {
      tocPanel.style.position = '';
      tocPanel.style.top = '';
      tocPanel.style.left = '';
      tocPanel.style.width = '';
    }
  }

  if (tocPanel) {
    window.addEventListener('scroll', pinToc, { passive: true });
    window.addEventListener('resize', pinToc);
    pinToc();
  }

  /* ---------- Single: reading progress bar ---------- */
  var progressBar = document.getElementById('blog-read-progress');
  var article = document.getElementById('blog-article');

  function updateProgress() {
    if (!progressBar || !article) return;
    var rect = article.getBoundingClientRect();
    var total = article.offsetHeight - window.innerHeight;
    if (total <= 0) {
      progressBar.style.width = '0%';
      return;
    }
    var scrolled = -rect.top;
    var pct = Math.min(100, Math.max(0, (scrolled / total) * 100));
    progressBar.style.width = pct + '%';
  }

  if (progressBar && article) {
    window.addEventListener('scroll', updateProgress, { passive: true });
    updateProgress();
  }

  /* ---------- Single: TOC active section ---------- */
  var tocLinks = document.querySelectorAll('#blog-toc-nav a');
  var headings = [];

  tocLinks.forEach(function (link) {
    var id = link.getAttribute('href');
    if (!id || id.charAt(0) !== '#') return;
    var el = document.getElementById(id.slice(1));
    if (el) headings.push({ link: link, el: el });
  });

  if (headings.length && 'IntersectionObserver' in window) {
    var tocObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        headings.forEach(function (h) {
          h.link.classList.toggle('active', h.el === entry.target);
        });
      });
    }, { rootMargin: '-20% 0px -60% 0px', threshold: 0 });

    headings.forEach(function (h) { tocObserver.observe(h.el); });
  }

  tocLinks.forEach(function (link) {
    link.addEventListener('click', function (e) {
      var id = link.getAttribute('href');
      if (!id || id.charAt(0) !== '#') return;
      var target = document.getElementById(id.slice(1));
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
      history.replaceState(null, '', id);
    });
  });

  /* ---------- Single: copy link ---------- */
  var copyBtn = document.getElementById('blog-copy-link');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      var url = copyBtn.getAttribute('data-url') || window.location.href;
      var done = function () {
        var orig = copyBtn.innerHTML;
        copyBtn.textContent = copyBtn.getAttribute('data-copied') || '✓';
        setTimeout(function () { copyBtn.innerHTML = orig; }, 2000);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done).catch(function () {
          window.prompt('Copy link:', url);
        });
      } else {
        window.prompt('Copy link:', url);
      }
    });
  }

  /* ---------- Scroll reveal (blog pages) ---------- */
  var revealTargets = document.querySelectorAll('.blog-page .reveal');
  if ('IntersectionObserver' in window && !reduceMotion) {
    var revealObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('in-view');
        revealObserver.unobserve(entry.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    revealTargets.forEach(function (el, i) {
      el.style.transitionDelay = Math.min(i % 5, 4) * 60 + 'ms';
      revealObserver.observe(el);
    });
  } else {
    revealTargets.forEach(function (el) { el.classList.add('in-view'); });
  }
})();
