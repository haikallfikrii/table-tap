/**
 * TableTap — language toggle (cookie + localStorage)
 */
(function () {
  const KEY = 'tabletap_lang';

  function setCookie(lang) {
    document.cookie = KEY + '=' + encodeURIComponent(lang) + ';path=/;max-age=31536000;SameSite=Lax';
  }

  function getStored() {
    try {
      return localStorage.getItem(KEY);
    } catch (e) {
      return null;
    }
  }

  // Sync localStorage → cookie on load if mismatch
  const stored = getStored();
  if (stored === 'my' || stored === 'en') {
    const match = document.cookie.match(/(?:^|; )tabletap_lang=([^;]*)/);
    const cookieLang = match ? decodeURIComponent(match[1]) : null;
    if (cookieLang !== stored) {
      setCookie(stored);
      // Reload once so PHP picks up language
      if (!sessionStorage.getItem('tt_lang_synced')) {
        sessionStorage.setItem('tt_lang_synced', '1');
        const url = new URL(window.location.href);
        url.searchParams.set('lang', stored);
        window.location.replace(url.toString());
        return;
      }
    }
  }
  sessionStorage.removeItem('tt_lang_synced');

  document.querySelectorAll('[data-set-lang]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const lang = btn.getAttribute('data-set-lang');
      if (lang !== 'my' && lang !== 'en') return;
      try {
        localStorage.setItem(KEY, lang);
      } catch (e) { /* ignore */ }
      setCookie(lang);
      const url = new URL(window.location.href);
      url.searchParams.set('lang', lang);
      window.location.href = url.toString();
    });
  });
})();
