/**
 * Daily closing report — print page / Bluetooth thermal.
 */
(function () {
  const root = document.getElementById('daily-report-app');
  if (!root) return;

  const i18n = (function () {
    try { return JSON.parse(root.dataset.i18n || '{}'); } catch (e) { return {}; }
  })();
  const jsonUrl = root.dataset.jsonUrl || '';
  const statusEl = document.getElementById('print-status');

  function setStatus(msg, ok) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.className = 'print-status' + (ok ? ' ok' : (msg ? ' warn' : ''));
  }

  document.getElementById('btn-print-page')?.addEventListener('click', function () {
    window.print();
  });

  document.getElementById('btn-print-bt')?.addEventListener('click', async function () {
    const btn = this;
    if (!window.TableTapPrint || !TableTapPrint.supported()) {
      setStatus(i18n.printer_unsupported || 'Bluetooth print needs Chrome/Edge.', false);
      return;
    }
    btn.disabled = true;
    const prev = btn.textContent;
    btn.textContent = i18n.printing || '…';
    try {
      const res = await fetch(jsonUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'fail');
      await TableTapPrint.ensureConnected({ interactive: true });
      await TableTapPrint.printDailyClosing(data, i18n);
      setStatus(i18n.daily_print_ok || 'Printed', true);
    } catch (err) {
      setStatus(i18n.print_failed || 'Print failed', false);
    } finally {
      btn.disabled = false;
      btn.textContent = prev;
      if (window.TableTapPrint && TableTapPrint.isConnected()) {
        // keep connected status visible briefly then restore
        setTimeout(function () {
          if (TableTapPrint.isConnected()) {
            setStatus(i18n.printer_connected || 'Printer connected', true);
          }
        }, 2000);
      }
    }
  });

  // Soft reconnect on load (same as kasir) so one prior grant is enough
  if (window.TableTapPrint && TableTapPrint.supported()) {
    const reconnect = TableTapPrint.reconnectWithRetry
      ? TableTapPrint.reconnectWithRetry(3, 600)
      : TableTapPrint.reconnect();
    reconnect.then(function () {
      setStatus(i18n.printer_connected || 'Printer connected', true);
    }).catch(function () { /* first visit */ });
  }
})();
