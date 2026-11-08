(() => {
  'use strict';
  const config = window.siaNativeAds || {};
  if (!/^https:\/\/pagead2\.googlesyndication\.com\/pagead\/js\/adsbygoogle\.js\?client=ca-pub-[0-9]{16}$/.test(config.src || '')) return;
  let started = false;
  const start = () => {
    if (started) return;
    started = true;
    listeners.forEach(([name, options]) => window.removeEventListener(name, start, options));
    const script = document.createElement('script');
    script.async = true;
    script.crossOrigin = 'anonymous';
    script.src = config.src;
    script.onload = () => document.querySelectorAll('.sia-native-ad .adsbygoogle').forEach((slot) => {
      if (slot.dataset.siaRequested === '1') return;
      slot.dataset.siaRequested = '1';
      try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (_) {}
    });
    document.head.appendChild(script);
  };
  const listeners = [
    ['pointerdown', { passive: true }], ['touchstart', { passive: true }],
    ['keydown', false], ['scroll', { passive: true }],
  ];
  listeners.forEach(([name, options]) => window.addEventListener(name, start, { ...((options && typeof options === 'object') ? options : {}), once: true }));
  const parsedDelay = Number(config.delayMs);
  const delay = Number.isFinite(parsedDelay) ? Math.max(0, Math.min(15000, parsedDelay)) : 8000;
  window.setTimeout(start, delay);
})();
