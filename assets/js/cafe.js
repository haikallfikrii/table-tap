/**
 * Cafe entry — email OTP or instant session
 */
(function () {
  const root = document.getElementById('cafe-entry');
  if (!root) return;

  const shop = root.dataset.shop;
  const token = root.dataset.token;
  const verifyMode = root.dataset.verify || 'email';
  const sendUrl = root.dataset.sendUrl;
  const verifyUrl = root.dataset.verifyUrl;
  const startUrl = root.dataset.startUrl;
  const i18n = JSON.parse(root.dataset.i18n || '{}');
  const lang = document.documentElement.lang === 'en' ? 'en' : 'my';

  const stepForm = document.getElementById('cafe-step-form');
  const stepOtp = document.getElementById('cafe-step-otp');
  const nameInput = document.getElementById('cafe-name');
  const emailInput = document.getElementById('cafe-email');
  const otpInput = document.getElementById('cafe-otp');
  const btnStart = document.getElementById('cafe-btn-start');
  const btnVerify = document.getElementById('cafe-btn-verify');
  const btnBack = document.getElementById('cafe-btn-back');
  const otpSent = document.getElementById('cafe-otp-sent');

  let pendingEmail = '';

  function validEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  }

  btnStart?.addEventListener('click', async function () {
    const name = (nameInput?.value || '').trim();
    if (name.length < 2) {
      alert(i18n.cafe_name_required || 'Enter name');
      nameInput?.focus();
      return;
    }

    if (verifyMode === 'none') {
      btnStart.disabled = true;
      btnStart.textContent = i18n.cafe_starting || 'Starting...';
      try {
        const res = await fetch(startUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ shop, token, nama_pelanggan: name }),
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.error || i18n.order_failed);
        window.location.href = data.redirect;
      } catch (err) {
        alert(err.message || i18n.order_failed);
        btnStart.disabled = false;
        btnStart.textContent = document.documentElement.lang === 'en' ? 'Start order' : 'Mula pesanan';
      }
      return;
    }

    const email = (emailInput?.value || '').trim();
    if (!validEmail(email)) {
      alert(i18n.cafe_email_invalid || 'Invalid email');
      emailInput?.focus();
      return;
    }

    btnStart.disabled = true;
    btnStart.textContent = i18n.cafe_sending || 'Sending...';
    try {
      const res = await fetch(sendUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ shop, token, email, nama_pelanggan: name, lang }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || i18n.order_failed);
      pendingEmail = email;
      if (otpSent) otpSent.textContent = data.email_masked || email;
      stepForm.hidden = true;
      stepOtp.hidden = false;
      otpInput?.focus();
    } catch (err) {
      alert(err.message || i18n.order_failed);
    } finally {
      btnStart.disabled = false;
      btnStart.textContent = document.documentElement.lang === 'en' ? 'Send code' : 'Hantar kod';
    }
  });

  btnBack?.addEventListener('click', function () {
    stepOtp.hidden = true;
    stepForm.hidden = false;
    pendingEmail = '';
    if (otpInput) otpInput.value = '';
  });

  btnVerify?.addEventListener('click', async function () {
    const name = (nameInput?.value || '').trim();
    const code = (otpInput?.value || '').trim();
    if (code.length !== 6) {
      alert(i18n.cafe_otp_required || 'Enter 6-digit code');
      otpInput?.focus();
      return;
    }
    btnVerify.disabled = true;
    btnVerify.textContent = i18n.cafe_verifying || 'Verifying...';
    try {
      const res = await fetch(verifyUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          shop,
          token,
          email: pendingEmail,
          code,
          nama_pelanggan: name,
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || i18n.order_failed);
      window.location.href = data.redirect;
    } catch (err) {
      alert(err.message || i18n.order_failed);
      btnVerify.disabled = false;
      btnVerify.textContent = document.documentElement.lang === 'en' ? 'Verify' : 'Sahkan';
    }
  });
})();
