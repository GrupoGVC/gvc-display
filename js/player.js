/* ============================================================
   GVC Signage — Player JS
   Fullscreen kiosk para Smart TVs
   ============================================================ */

'use strict';

// ── Config ────────────────────────────────────────────────────
const API_BASE = (() => {
  const u = new URL(location.href);
  // /html/player.html → raiz do projeto
  return u.origin + u.pathname.replace(/\/html\/.*$/, '') + '/api';
})();

const HEARTBEAT_MS  = 30_000;
const PAIRING_POLL  = 5_000;
const PAIRING_TTL   = 10 * 60;      // segundos

const DEBUG = new URLSearchParams(location.search).has('debug');
if (DEBUG) document.getElementById('debug').style.display = 'block';

// ── Estado ────────────────────────────────────────────────────
let token           = new URLSearchParams(location.search).get('token') || localStorage.getItem('gvc_tv_token');
let curHash         = null;
let curPlaylist     = null;
let curIdx          = 0;
let slideTimer      = null;
let heartbeatTimer  = null;
let pairingCode     = null;
let pairingInterval = null;
let pairingPoll     = null;

// ── Init ──────────────────────────────────────────────────────
async function init() {
  if (token) {
    localStorage.setItem('gvc_tv_token', token);
    hidePairing();
    await heartbeat();
    heartbeatTimer = setInterval(heartbeat, HEARTBEAT_MS);
  } else {
    await startPairing();
  }
}

// ── Heartbeat ─────────────────────────────────────────────────
async function heartbeat() {
  try {
    const res = await fetch(`${API_BASE}/devices/heartbeat.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token }),
    });

    if (res.status === 404) {
      localStorage.removeItem('gvc_tv_token');
      token = null;
      clearInterval(heartbeatTimer);
      await startPairing();
      return;
    }

    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    hideError();
    const json = await res.json();
    const pl   = json.data?.playlist;

    if (!pl?.items?.length) {
      showError('Nenhuma apresentação configurada');
      return;
    }

    if (pl.hash !== curHash) {
      dbg(`Nova playlist: ${pl.name} (${pl.items.length} itens)`);
      curHash     = pl.hash;
      curPlaylist = pl;
      clearTimeout(slideTimer);
      buildSlides();
      showSlide(0);
    }
  } catch (e) {
    showError('Sem conexão — reconectando...');
    dbg('Heartbeat error: ' + e.message);
  }
}

// ── Slides ────────────────────────────────────────────────────
function buildSlides() {
  const stage = document.getElementById('stage');
  stage.innerHTML = '';

  curPlaylist.items.forEach((item, i) => {
    const slide = document.createElement('div');
    slide.className   = 'slide';
    slide.dataset.idx = i;

    const src = item.media_url || item.url || '';

    if (item.type === 'video') {
      const v = document.createElement('video');
      v.src         = src;
      v.muted       = true;
      v.playsInline = true;
      v.preload     = 'auto';
      v.setAttribute('playsinline', '');
      v.addEventListener('ended', nextSlide);
      v.addEventListener('error', nextSlide);
      slide.appendChild(v);

    } else if (item.type === 'page') {
      const f = document.createElement('iframe');
      f.src     = src;
      f.sandbox = 'allow-scripts allow-same-origin allow-forms';
      slide.appendChild(f);

    } else {
      const img = document.createElement('img');
      img.src     = src;
      img.alt     = '';
      img.loading = 'lazy';
      img.addEventListener('error', () => { img.src = ''; });
      slide.appendChild(img);
    }

    stage.appendChild(slide);
  });
}

function showSlide(idx) {
  if (!curPlaylist?.items?.length) return;
  clearTimeout(slideTimer);

  const items  = curPlaylist.items;
  curIdx       = ((idx % items.length) + items.length) % items.length;
  const item   = items[curIdx];

  document.querySelectorAll('.slide').forEach((s, i) => {
    s.classList.remove('active', 'prev');
    if (i === curIdx) s.classList.add('active');
  });

  dbg(`[${curIdx + 1}/${items.length}] ${item.type} — ${item.media_url || item.url || ''}`);

  const vid = document.querySelector(`.slide.active video`);
  if (vid) {
    vid.currentTime = 0;
    vid.play().catch(() => scheduleNext(item.duration || 10));
    // Timeout de segurança caso o vídeo trave
    slideTimer = setTimeout(nextSlide, ((item.duration || 60) + 30) * 1000);
    updateHud(item.duration || 30);
    return;
  }

  scheduleNext(item.duration || 10);
}

function scheduleNext(seconds) {
  updateHud(seconds);
  slideTimer = setTimeout(nextSlide, seconds * 1000);
}

function nextSlide() {
  if (curPlaylist?.items?.length) showSlide(curIdx + 1);
}

// ── HUD bar ───────────────────────────────────────────────────
function updateHud(seconds) {
  const bar = document.getElementById('hud-bar');
  bar.style.transition = 'none';
  bar.style.width = '0%';
  requestAnimationFrame(() => {
    bar.style.transition = `width ${seconds}s linear`;
    bar.style.width = '100%';
  });
}

// ── Pairing ───────────────────────────────────────────────────
async function startPairing() {
  showPairing();
  clearInterval(pairingPoll);
  clearInterval(pairingInterval);

  try {
    const res = await fetch(`${API_BASE}/pairing/index.php?action=generate`, { method: 'POST' });
    if (!res.ok) throw new Error('Erro ao gerar código');
    const json = await res.json();
    pairingCode = json.data.code;

    document.getElementById('pair-code').textContent = pairingCode;
    document.getElementById('pair-hint').textContent = 'Digite este código no painel administrativo';

    // Countdown
    let ttl = PAIRING_TTL;
    const tick = () => {
      const m = Math.floor(ttl / 60), s = String(ttl % 60).padStart(2, '0');
      document.getElementById('pair-timer').textContent = `Código válido por ${m}:${s}`;
      if (--ttl < 0) { clearInterval(pairingInterval); startPairing(); }
    };
    tick();
    pairingInterval = setInterval(tick, 1000);

    // Poll: checar vinculação
    pairingPoll = setInterval(async () => {
      try {
        const r = await fetch(`${API_BASE}/pairing/index.php?action=check`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ code: pairingCode }),
        });
        const j = await r.json();
        if (j.data?.paired) {
          clearInterval(pairingPoll);
          clearInterval(pairingInterval);
          token = j.data.token;
          localStorage.setItem('gvc_tv_token', token);
          const url = new URL(location.href);
          url.searchParams.set('token', token);
          history.replaceState(null, '', url.toString());
          hidePairing();
          await heartbeat();
          heartbeatTimer = setInterval(heartbeat, HEARTBEAT_MS);
        }
      } catch {}
    }, PAIRING_POLL);

  } catch {
    document.getElementById('pair-hint').textContent = 'Sem conexão. Tentando novamente...';
    setTimeout(startPairing, 10_000);
  }
}

// ── UI helpers ────────────────────────────────────────────────
function showPairing() { document.getElementById('pairing').classList.remove('hidden'); }
function hidePairing() { document.getElementById('pairing').classList.add('hidden'); }

function showError(msg) {
  document.querySelector('#error-screen .err-msg').textContent = msg;
  document.getElementById('error-screen').classList.add('show');
}
function hideError() { document.getElementById('error-screen').classList.remove('show'); }

function dbg(msg) {
  if (DEBUG) document.getElementById('debug').textContent = msg;
}

// ── Fullscreen ────────────────────────────────────────────────
function goFullscreen() {
  const el = document.documentElement;
  (el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen || Function())
    .call(el);
}
['click', 'touchstart'].forEach(ev => document.addEventListener(ev, goFullscreen, { once: true }));
setTimeout(goFullscreen, 500);

// ── Anti-sleep ────────────────────────────────────────────────
setInterval(() => document.dispatchEvent(new MouseEvent('mousemove', { bubbles: true, clientX: Math.random(), clientY: Math.random() })), 60_000);

// ── Start ─────────────────────────────────────────────────────
init();
