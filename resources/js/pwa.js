/* ============================================================
   GVC Display — PWA
   Comportamento:
   - Celular: oferece instalação como app (standalone/fullscreen)
   - PC:      site normal, sem prompt de instalação
   ============================================================ */

(function () {
  'use strict';

  const BASE = window.__BASE_URL__ ||
    (window.location.origin +
      window.location.pathname
        .replace(/\/(login|tv)(\/.*)?$/, '')
        .replace(/\/+$/, ''));

  // ── Detecta se é mobile ───────────────────────────────────
  const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent)
    || window.matchMedia('(max-width: 768px)').matches
    || ('ontouchstart' in window && navigator.maxTouchPoints > 1);

  // ── Detecta se já está instalado como PWA ─────────────────
  const isInstalled = window.matchMedia('(display-mode: standalone)').matches
    || window.matchMedia('(display-mode: fullscreen)').matches
    || window.navigator.standalone === true; // iOS Safari

  // ── Registra Service Worker (todos os dispositivos) ───────
  // O SW é registrado em todos para habilitar cache offline
  // mas o prompt de instalação só aparece em mobile
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
      try {
        const reg = await navigator.serviceWorker.register(
          `${BASE}/sw.js`,
          { scope: BASE + '/' }
        );

        // Auto-atualiza quando há nova versão do SW
        reg.addEventListener('updatefound', () => {
          const sw = reg.installing;
          sw?.addEventListener('statechange', () => {
            if (sw.state === 'installed' && navigator.serviceWorker.controller) {
              window.location.reload();
            }
          });
        });

      } catch (err) {
        // Falha silenciosa — não afeta o funcionamento do site
        console.warn('[PWA] SW não registrado:', err.message);
      }
    });
  }

  // ── Prompt de instalação — APENAS em mobile ───────────────
  if (!isMobile || isInstalled) return;

  let deferredPrompt = null;

  window.addEventListener('beforeinstallprompt', (e) => {
    // Impede o mini-infobar automático do Chrome no mobile
    e.preventDefault();
    deferredPrompt = e;

    // Mostra o botão de instalação se existir na página
    showInstallButton();
  });

  function showInstallButton() {
    // Cria o botão flutuante se não existir
    let btn = document.getElementById('btn-install-pwa');

    if (!btn) {
      btn = document.createElement('button');
      btn.id        = 'btn-install-pwa';
      btn.innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2v13M7 11l5 5 5-5"/><rect x="3" y="18" width="18" height="3" rx="1"/>
        </svg>
        Instalar app
      `;
      btn.style.cssText = `
        position:fixed; bottom:20px; right:20px; z-index:9999;
        display:flex; align-items:center; gap:8px;
        background:#4f8cff; color:#fff;
        border:none; border-radius:12px;
        padding:12px 18px; font-size:14px; font-weight:600;
        cursor:pointer; box-shadow:0 4px 20px rgba(79,140,255,.4);
        font-family:inherit;
        animation: slideUp .3s ease;
      `;

      // Animação de entrada
      const style = document.createElement('style');
      style.textContent = `
        @keyframes slideUp {
          from { transform: translateY(80px); opacity: 0; }
          to   { transform: translateY(0);    opacity: 1; }
        }
      `;
      document.head.appendChild(style);
      document.body.appendChild(btn);
    }

    btn.style.display = 'flex';

    btn.onclick = async () => {
      if (!deferredPrompt) return;
      btn.style.display = 'none';
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      deferredPrompt = null;
    };
  }

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    const btn = document.getElementById('btn-install-pwa');
    if (btn) btn.remove();
  });

})();
