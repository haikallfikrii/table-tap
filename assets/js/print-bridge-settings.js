/**
 * Owner settings — queue a Print Bridge test ticket per station.
 * The job sits in MySQL until the Android bridge app picks it up over HTTPS.
 */
(function () {
  'use strict';

  var root = document.getElementById('print-bridge-settings');
  if (!root) return;

  var testUrl = root.dataset.testUrl || '';
  var queuedLabel = root.dataset.queuedLabel || 'Test ticket queued';
  var failedLabel = root.dataset.failedLabel || 'Could not queue test ticket';
  var status = document.getElementById('print-bridge-test-status');

  function say(text) {
    if (status) status.textContent = text;
  }

  root.addEventListener('click', async function (e) {
    var btn = e.target.closest('[data-bridge-test]');
    if (!btn || !testUrl) return;
    e.preventDefault();

    var station = btn.getAttribute('data-bridge-test');
    btn.disabled = true;
    try {
      var res = await fetch(testUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ station: station }),
      });
      var data = await res.json();
      say(data.ok ? queuedLabel + ' — ' + station : (data.error || failedLabel));
    } catch (err) {
      say(failedLabel);
    } finally {
      btn.disabled = false;
    }
  });
})();
