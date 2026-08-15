/**
 * TableTap — louder looping alerts (requires user gesture to unlock)
 */
window.TableTapSound = (function () {
  let enabled = false;
  let audioCtx = null;
  let settings = {
    mode: 'until_cleared',
    count: 8,
    duration_sec: 45,
    interval_ms: 900,
    volume: 100,
  };
  let alarmTimer = null;
  let alarmStartedAt = 0;
  let alarmBeeps = 0;
  let onAlarmDone = null;
  let wantAlarm = false;
  let pendingChime = null;

  function unlock() {
    try {
      const AudioContext = window.AudioContext || window.webkitAudioContext;
      if (!AudioContext) return false;
      audioCtx = audioCtx || new AudioContext();
      if (audioCtx.state === 'suspended') {
        audioCtx.resume();
      }
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      gain.gain.value = 0.0001;
      osc.connect(gain);
      gain.connect(audioCtx.destination);
      osc.start();
      osc.stop(audioCtx.currentTime + 0.01);
      enabled = true;
      if (pendingChime) {
        const kind = pendingChime;
        pendingChime = null;
        chime(kind);
      }
      if (wantAlarm) {
        startAlarm(onAlarmDone);
      }
      return true;
    } catch (e) {
      return false;
    }
  }

  function armAutoUnlock() {
    unlock();
    const go = function () { unlock(); };
    ['pointerdown', 'touchstart', 'click', 'keydown'].forEach(function (ev) {
      document.addEventListener(ev, go, { capture: true, passive: true });
    });
  }

  function configure(next) {
    if (!next || typeof next !== 'object') return;
    if (next.mode) settings.mode = next.mode;
    if (next.count) settings.count = Number(next.count) || settings.count;
    if (next.duration_sec) settings.duration_sec = Number(next.duration_sec) || settings.duration_sec;
    if (next.interval_ms) settings.interval_ms = Number(next.interval_ms) || settings.interval_ms;
    if (next.volume) settings.volume = Number(next.volume) || settings.volume;
  }

  function peakGain() {
    const v = Math.max(20, Math.min(100, Number(settings.volume) || 100)) / 100;
    return 0.28 + v * 0.62;
  }

  function beep() {
    if (!enabled || !audioCtx) return;
    try {
      if (audioCtx.state === 'suspended') audioCtx.resume();
      const now = audioCtx.currentTime;
      const master = audioCtx.createGain();
      const peak = peakGain();
      master.gain.setValueAtTime(0.0001, now);
      master.gain.exponentialRampToValueAtTime(peak, now + 0.02);
      master.gain.exponentialRampToValueAtTime(peak * 0.85, now + 0.12);
      master.gain.exponentialRampToValueAtTime(0.0001, now + 0.55);
      master.connect(audioCtx.destination);

      const hi = audioCtx.createOscillator();
      hi.type = 'square';
      hi.frequency.setValueAtTime(980, now);
      hi.frequency.setValueAtTime(1318, now + 0.16);
      hi.frequency.setValueAtTime(880, now + 0.32);
      const hiGain = audioCtx.createGain();
      hiGain.gain.value = 0.55;
      hi.connect(hiGain);
      hiGain.connect(master);

      const lo = audioCtx.createOscillator();
      lo.type = 'sawtooth';
      lo.frequency.setValueAtTime(490, now);
      lo.frequency.setValueAtTime(659, now + 0.16);
      const loGain = audioCtx.createGain();
      loGain.gain.value = 0.35;
      lo.connect(loGain);
      loGain.connect(master);

      hi.start(now);
      lo.start(now);
      hi.stop(now + 0.56);
      lo.stop(now + 0.56);
    } catch (e) { /* ignore */ }
  }

  function tone(freq, type, start, dur, vol) {
    const osc = audioCtx.createOscillator();
    const g = audioCtx.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, start);
    g.gain.setValueAtTime(0.0001, start);
    g.gain.exponentialRampToValueAtTime(Math.max(0.0002, vol), start + 0.018);
    g.gain.exponentialRampToValueAtTime(0.0001, start + dur);
    osc.connect(g);
    g.connect(audioCtx.destination);
    osc.start(start);
    osc.stop(start + dur + 0.03);
  }

  function chime(kind) {
    if (!enabled || !audioCtx) {
      pendingChime = kind;
      return;
    }
    pendingChime = null;
    try {
      if (audioCtx.state === 'suspended') audioCtx.resume();
      const now = audioCtx.currentTime;
      const peak = peakGain();
      if (kind === 'cooking') {
        tone(392, 'triangle', now, 0.16, peak * 0.4);
        tone(523, 'triangle', now + 0.14, 0.2, peak * 0.48);
        tone(659, 'sine', now + 0.3, 0.28, peak * 0.42);
        return;
      }
      if (kind === 'done') {
        tone(523, 'sine', now, 0.18, peak * 0.38);
        tone(659, 'sine', now + 0.12, 0.2, peak * 0.44);
        tone(784, 'sine', now + 0.26, 0.42, peak * 0.5);
        return;
      }
      if (kind === 'queue') {
        tone(494, 'sine', now, 0.14, peak * 0.28);
        return;
      }
      beep();
    } catch (e) { /* ignore */ }
  }

  function stopAlarm() {
    wantAlarm = false;
    if (alarmTimer) {
      clearTimeout(alarmTimer);
      alarmTimer = null;
    }
    alarmBeeps = 0;
    onAlarmDone = null;
  }

  function alarmTick() {
    alarmTimer = null;
    if (!enabled) return;
    if (settings.mode === 'count' && alarmBeeps >= settings.count) {
      if (typeof onAlarmDone === 'function') onAlarmDone();
      return;
    }
    if (settings.mode === 'duration' && Date.now() - alarmStartedAt >= settings.duration_sec * 1000) {
      if (typeof onAlarmDone === 'function') onAlarmDone();
      return;
    }
    beep();
    alarmBeeps += 1;
    alarmTimer = setTimeout(alarmTick, settings.interval_ms);
  }

  function startAlarm(done) {
    if (!enabled) {
      wantAlarm = true;
      return;
    }
    if (alarmTimer) return;
    wantAlarm = false;
    onAlarmDone = typeof done === 'function' ? done : null;
    alarmStartedAt = Date.now();
    alarmBeeps = 0;
    alarmTick();
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

  return { unlock, armAutoUnlock, beep, chime, configure, startAlarm, stopAlarm, bindButton, isEnabled: () => enabled };
})();
