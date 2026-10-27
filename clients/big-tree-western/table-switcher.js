(function () {
  var root = document.body;
  var MAX = Math.max(1, parseInt(root.getAttribute('data-max-meja') || '26', 10) || 26);
  var params = new URLSearchParams(window.location.search);
  var initial = Math.max(1, Math.min(MAX, parseInt(params.get('meja') || '1', 10) || 1));
  var select = document.getElementById('meja-select');
  var numEl = document.getElementById('meja-num');
  var qrEl = document.getElementById('qr');

  function apply(n) {
    n = Math.max(1, Math.min(MAX, Number(n) || 1));
    if (numEl) numEl.textContent = String(n);
    if (qrEl) {
      qrEl.src = 'qr/meja' + n + '.png';
      qrEl.alt = 'QR Meja ' + n;
    }
    if (select) select.value = String(n);
    document.querySelectorAll('.meja-num-sync').forEach(function (el) {
      el.textContent = String(n);
    });
    document.querySelectorAll('.qr-sync').forEach(function (el) {
      el.src = 'qr/meja' + n + '.png';
      el.alt = 'QR Meja ' + n;
    });
    var url = new URL(window.location.href);
    url.searchParams.set('meja', String(n));
    history.replaceState(null, '', url);
    window.dispatchEvent(new CustomEvent('tt-meja-change', { detail: { meja: n } }));
  }

  if (select) {
    select.innerHTML = '';
    for (var i = 1; i <= MAX; i++) {
      var opt = document.createElement('option');
      opt.value = String(i);
      opt.textContent = String(i);
      select.appendChild(opt);
    }
    select.addEventListener('change', function () {
      apply(select.value);
    });
  }
  apply(initial);
})();
