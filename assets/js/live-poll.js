/**
 * Live polling that stays fresh on MIUI / Xiaomi Chrome.
 * setInterval is throttled there; GET responses are often cached; hung
 * fetches can freeze the busy flag forever.
 */
(function (w) {
  'use strict';

  var wakeLock = null;

  function bust(url) {
    var sep = url.indexOf('?') >= 0 ? '&' : '?';
    return url + sep + '_=' + Date.now();
  }

  function fetchLive(url, opts) {
    opts = opts || {};
    var timeoutMs = opts.timeout || 10000;
    var ctrl = typeof AbortController === 'function' ? new AbortController() : null;
    var timer = 0;
    if (ctrl) {
      timer = setTimeout(function () {
        try { ctrl.abort(); } catch (e) { /* ignore */ }
      }, timeoutMs);
    }
    var headers = {
      Accept: 'application/json',
      'Cache-Control': 'no-cache',
      Pragma: 'no-cache',
    };
    if (opts.headers) {
      Object.keys(opts.headers).forEach(function (k) {
        headers[k] = opts.headers[k];
      });
    }
    var init = {
      method: opts.method || 'GET',
      cache: 'no-store',
      credentials: opts.credentials || 'same-origin',
      headers: headers,
    };
    if (ctrl) init.signal = ctrl.signal;
    if (opts.body != null) init.body = opts.body;
    return Promise.resolve(fetch(bust(url), init)).then(function (res) {
      if (timer) clearTimeout(timer);
      return res;
    }, function (err) {
      if (timer) clearTimeout(timer);
      throw err;
    });
  }

  function requestWakeLock() {
    if (!navigator.wakeLock || document.visibilityState !== 'visible') return;
    navigator.wakeLock.request('screen').then(function (lock) {
      wakeLock = lock;
      lock.addEventListener('release', function () {
        if (wakeLock === lock) wakeLock = null;
      });
    }).catch(function () {
      /* permission / unsupported */
    });
  }

  function loop(tick, intervalMs, options) {
    options = options || {};
    var interval = Math.max(800, Number(intervalMs) || 3000);
    var timer = 0;
    var watchdog = 0;
    var running = false;
    var stopped = false;
    var lastStart = 0;

    function schedule(delay) {
      if (stopped) return;
      clearTimeout(timer);
      timer = setTimeout(run, delay == null ? interval : delay);
    }

    function run() {
      if (stopped || running) return;
      running = true;
      lastStart = Date.now();
      Promise.resolve()
        .then(tick)
        .catch(function () { /* keep looping */ })
        .then(function () {
          running = false;
          schedule(interval);
        });
    }

    function kick() {
      if (stopped) return;
      if (options.keepAwake) requestWakeLock();
      if (running) return;
      clearTimeout(timer);
      run();
    }

    function onVisible() {
      if (document.visibilityState === 'visible') kick();
    }

    document.addEventListener('visibilitychange', onVisible);
    w.addEventListener('pageshow', kick);
    w.addEventListener('focus', kick);
    w.addEventListener('online', kick);
    document.addEventListener('resume', kick);

    watchdog = setInterval(function () {
      if (stopped || running) return;
      if (Date.now() - lastStart > interval * 2.2) kick();
    }, Math.min(2000, interval));

    if (options.keepAwake) {
      requestWakeLock();
      document.addEventListener('click', requestWakeLock, { once: true });
    }

    kick();

    return {
      kick: kick,
      stop: function () {
        stopped = true;
        clearTimeout(timer);
        clearInterval(watchdog);
        document.removeEventListener('visibilitychange', onVisible);
        w.removeEventListener('pageshow', kick);
        w.removeEventListener('focus', kick);
        w.removeEventListener('online', kick);
        document.removeEventListener('resume', kick);
        if (wakeLock) {
          try { wakeLock.release(); } catch (e) { /* ignore */ }
          wakeLock = null;
        }
      },
    };
  }

  w.TableTapLive = {
    fetch: fetchLive,
    loop: loop,
  };
})(window);
