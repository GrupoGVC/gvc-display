/* ============================================================
   GVC Display — PWA / Service Worker Manager
   Registra o kill-switch para limpar SWs antigos.
   ============================================================ */

(function () {
  'use strict';

  const BASE = window.__BASE_URL__ ||
    (window.location.origin +
      window.location.pathname
        .replace(/\/(login|tv)(\/.*)?$/, '')
        .replace(/\/+$/, ''));

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(BASE + '/sw.js', { scope: BASE + '/' })
        .catch(function (err) {
          console.warn('[PWA] SW register error:', err.message);
        });
    });
  }
})();
