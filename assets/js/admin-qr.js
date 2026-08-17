document.querySelectorAll('.qr-copy-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var id = btn.getAttribute('data-copy-target');
    var input = id ? document.getElementById(id) : null;
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    var text = input.value;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        btn.textContent = btn.dataset.copiedLabel || 'OK';
        setTimeout(function () {
          btn.textContent = btn.dataset.originalLabel || btn.textContent;
        }, 1500);
      });
    } else {
      document.execCommand('copy');
    }
  });
});

document.querySelectorAll('.qr-copy-btn').forEach(function (btn) {
  btn.dataset.originalLabel = btn.textContent.trim();
});
