(() => {
  'use strict';
  const cfg = window.siaGA4Config;
  const panel = document.getElementById('sia-analytics-consent');
  const change = document.getElementById('sia-analytics-change');
  if (!panel || !change || !cfg || !/^G-[A-Z0-9]{4,20}$/.test(cfg.id)) return;
  // A pre-existing Google tag or GTM container owns measurement on this page.
  if (document.querySelector('script[src*="googletagmanager.com/gtag/js"],script[src*="googletagmanager.com/gtm.js"]')) { change.hidden=true; return; }
  const key = 'sia-analytics-consent-v1';
  let choice = null;
  try { choice = localStorage.getItem(key); } catch (_) { /* choice remains undecided */ }
  const denied = {analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'};
  function start() {
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    if (!window.siaGA4Loaded) {
      window.gtag('consent','default',denied);
      window.gtag('consent','update',{analytics_storage:'granted'});
      window.gtag('js',new Date());
      window.gtag('config',cfg.id);
      window['ga-disable-' + cfg.id] = false;
      const script=document.createElement('script');
      script.async=true;
      script.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(cfg.id);
      document.head.appendChild(script);
      window.siaGA4Loaded=true;
    } else {
      window['ga-disable-' + cfg.id] = false;
      window.gtag('consent','update',{analytics_storage:'granted'});
    }
  }
  function apply(value) {
    try { localStorage.setItem(key,value); } catch (_) { /* current-page choice still applies */ }
    if (value==='allow') start();
    else if (window.siaGA4Loaded && typeof window.gtag==='function') {
      window.gtag('consent','update',denied);
      window['ga-disable-' + cfg.id] = true;
    }
    panel.hidden=true;
  }
  panel.querySelector('[data-sia-analytics="accept"]').addEventListener('click',()=>apply('allow'));
  panel.querySelector('[data-sia-analytics="reject"]').addEventListener('click',()=>apply('deny'));
  change.addEventListener('click',()=>{panel.hidden=false; panel.querySelector('[data-sia-analytics="accept"]').focus();});
  if (choice==='allow') start();
  else if (choice!=='deny') panel.hidden=false;
})();
