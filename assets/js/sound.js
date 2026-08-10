/**
 * TableTap — sound notification helper (requires user gesture)
 */
window.TableTapSound = (function () {
  let enabled = false;
  let audioCtx = null;

  function unlock() {
    try {
      const AudioContext = window.AudioContext || window.webkitAudioContext;
      if (!AudioContext) return false;
      audioCtx = audioCtx || new AudioContext();
      if (audioCtx.state === 'suspended') {
        audioCtx.resume();
      }
      // silent blip to unlock
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      gain.gain.value = 0.0001;
      osc.connect(gain);
      gain.connect(audioCtx.destination);
      osc.start();
      osc.stop(audioCtx.currentTime + 0.01);
      enabled = true;
      return true;
    } catch (e) {
      return false;
    }
  }

  function beep() {
    if (!enabled || !audioCtx) return;
    try {
      if (audioCtx.state === 'suspended') audioCtx.resume();
      const now = audioCtx.currentTime;
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(880, now);
      osc.frequency.setValueAtTime(660, now + 0.15);
      gain.gain.setValueAtTime(0.0001, now);
      gain.gain.exponentialRampToValueAtTime(0.25, now + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.4);
      osc.connect(gain);
      gain.connect(audioCtx.destination);
      osc.start(now);
      osc.stop(now + 0.42);
    } catch (e) { /* ignore */ }
  }

  function bindButton(btn, labels) {
    if (!btn) return;
    btn.addEventListener('click', function () {
      if (unlock()) {
        btn.textContent = (labels && labels.on) || 'Sound on';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-secondary');
        beep();
      }
    });
  }

  return { unlock, beep, bindButton, isEnabled: () => enabled };
})();
