/* ============================================================
   GVC Display — Player da TV (NOVO FLUXO)
   ============================================================

   O TvController injeta:
     window.__DEVICE_TOKEN__  → token da TV (null se não pareada)
     window.__CLIENT_ID__     → fingerprint persistente da TV
     window.__PAIRED__        → true/false
     window.__BASE_URL__      → base do app

   Estados:
     PAIRING  → mostra código, faz polling em /pairing/tv-status
     WAITING  → pareada mas sem playlist
     PLAYING  → exibe a playlist
============================================================ */

const TOKEN     = window.__DEVICE_TOKEN__ || null;
const CLIENT_ID = window.__CLIENT_ID__    || '';
const IS_PAIRED = !!window.__PAIRED__;
const BASE      = window.__BASE_URL__ || (() => {
  const p = window.location.pathname
    .replace(/\/tv(\/[^/]*)?$/, '')
    .replace(/\/+$/, '');
  return window.location.origin + p;
})();
const API = BASE + '/api';

const S = {
  phase:         'init',
  playlist:      [],
  idx:           0,
  slideTimer:    null,
  hbTimer:       null,
  pairPollTimer: null,
  pairCountdown: null,
  currentCode:   null,
  lastHash:      null,
  lastPlId:      null,
};

// ── Bootstrap ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  if (IS_PAIRED && TOKEN) {
    // TV já pareada — vai direto pro modo player
    startPlayer();
  } else {
    // TV não pareada — inicia fluxo de pareamento
    startPairing();
  }
});

// ════════════════════════════════════════════════════════════════
//  MODO PAREAMENTO
// ════════════════════════════════════════════════════════════════
async function startPairing() {
  S.phase = 'pairing';
  el('stage').innerHTML             = '';
  el('status-screen').style.display = 'none';
  el('pairing-screen').style.display = 'flex';

  await generatePairingCode();
  // Polling do status a cada 3s
  S.pairPollTimer = setInterval(pollPairingStatus, 3000);
  // Primeira consulta imediata (para casos onde admin já pareou entre gerar e o primeiro poll)
  pollPairingStatus();
}

async function generatePairingCode() {
  setDigits('------');
  setText('pair-ttl', 'Gerando código...');

  try {
    const r = await fetch(`${API}/pairing/tv-generate`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ client_id: CLIENT_ID }),
    });
    if (!r.ok) throw new Error('http ' + r.status);
    const body = await r.json();
    const code = (body.data && body.data.code) || body.code;
    if (!code) throw new Error('sem código');

    S.currentCode = code;
    setDigits(code);

    // QR aponta pro URL do admin (facilita abrir no celular)
    const qr = el('pair-qr');
    const fb = el('pair-qr-fallback');
    if (qr) {
      const adminUrl = BASE + '/';
      qr.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&bgcolor=ffffff&color=000000&qzone=1&data=${enc(adminUrl)}`;
      qr.style.display = 'block';
      if (fb) fb.style.display = 'none';
      qr.onerror = () => { qr.style.display = 'none'; if (fb) fb.style.display = 'flex'; };
    }
    const urlEl = el('pair-admin-url');
    if (urlEl) urlEl.textContent = BASE + '/';

    // Countdown 30 min
    startCountdown(30 * 60);
    setText('pair-status-txt', 'Aguardando vinculação...');

  } catch (e) {
    setDigits('------');
    setText('pair-ttl', 'Erro ao gerar. Tentando de novo em 10s...');
    setTimeout(generatePairingCode, 10000);
  }
}

function startCountdown(secs) {
  clearInterval(S.pairCountdown);
  S.pairCountdown = setInterval(() => {
    secs--;
    const m = String(Math.floor(secs / 60)).padStart(2, '0');
    const s = String(secs % 60).padStart(2, '0');
    setText('pair-ttl', `Código válido por ${m}:${s}`);
    if (secs <= 0) {
      clearInterval(S.pairCountdown);
      generatePairingCode();  // regenera código expirado
    }
  }, 1000);
}

async function pollPairingStatus() {
  if (!CLIENT_ID) return;
  try {
    const r = await fetch(`${API}/pairing/tv-status?client_id=${enc(CLIENT_ID)}`);
    if (!r.ok) return;
    const body = await r.json();
    const d = body.data || body;

    if (d.paired && d.token) {
      // Foi pareada! Salva cookie e reinicia como player.
      document.cookie = `gvc_tv_token=${d.token}; path=/; max-age=${10*365*24*3600}; SameSite=Lax`;
      // Limpa timers
      clearInterval(S.pairPollTimer);
      clearInterval(S.pairCountdown);
      S.pairPollTimer = S.pairCountdown = null;

      setText('pair-status-txt', 'Vinculação confirmada! Carregando...');
      // Recarrega — TvController vai reconhecer o cookie e injetar __PAIRED__ = true
      setTimeout(() => window.location.reload(), 800);
    } else if (d.code && d.code !== S.currentCode) {
      // Código mudou (expirou e foi renovado no servidor)
      S.currentCode = d.code;
      setDigits(d.code);
    }
  } catch { /* silencioso */ }
}

function setDigits(code) {
  const d = document.getElementById('pair-digits');
  if (!d) return;
  const chars = String(code).padEnd(6, '-').slice(0, 6);
  d.innerHTML = chars.split('').map(c => `<span>${c}</span>`).join('');
}

// ════════════════════════════════════════════════════════════════
//  MODO PLAYER (TV pareada)
// ════════════════════════════════════════════════════════════════
function startPlayer() {
  S.phase = 'init';
  el('pairing-screen').style.display = 'none';
  heartbeat();
  S.hbTimer = setInterval(heartbeat, 5000);
}

async function heartbeat() {
  try {
    const r = await fetch(`${API}/devices/heartbeat`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ token: TOKEN }),
    });

    if (r.status === 401) {
      // TV foi despareada no admin — apaga cookie e volta pro pareamento
      document.cookie = 'gvc_tv_token=; path=/; max-age=0';
      clearInterval(S.hbTimer);
      window.location.reload();
      return;
    }
    if (!r.ok) {
      if (S.phase === 'init') setPhase('error', '📡', 'Sem conexão com o servidor');
      return;
    }

    const body = await r.json();
    const d    = body.data || body;

    // Sem playlist → tela "aguardando"
    if (!d.playlist_id) {
      if (S.phase !== 'waiting') {
        setPhase('waiting', '📋', 'Aguardando apresentação',
                 'Atribua uma playlist a esta TV no painel administrativo');
      }
      S.lastPlId = null;
      S.lastHash = null;
      return;
    }

    // Playlist ou hash mudou?
    if (d.playlist_id !== S.lastPlId || d.playlist_hash !== S.lastHash) {
      S.lastPlId = d.playlist_id;
      S.lastHash = d.playlist_hash;
      await loadAndPlay();
    }
  } catch (e) {
    if (S.phase === 'init') setPhase('error', '📡', 'Sem conexão', 'Tentando reconectar...');
  }
}

async function loadAndPlay() {
  try {
    const r = await fetch(`${API}/devices/tv-playlist?token=${enc(TOKEN)}`);
    if (r.status === 204) {
      setPhase('waiting', '📺', 'Playlist vazia', 'Adicione itens no painel');
      return;
    }
    if (!r.ok) return;

    const body = await r.json();
    const pl   = body.data || body;

    if (!pl.items || !pl.items.length) {
      setPhase('waiting', '📺', 'Playlist vazia', 'Adicione itens no painel');
      return;
    }

    S.playlist = pl.items;
    S.phase    = 'playing';
    S.idx      = 0;

    el('status-screen').style.display = 'none';
    el('pairing-screen').style.display = 'none';
    showSlide(0);
  } catch (e) {
    console.warn('[GVC Player] loadAndPlay error:', e);
  }
}

function showSlide(i) {
  clearSlideTimer();
  if (!S.playlist || !S.playlist.length) return;

  const item = S.playlist[i % S.playlist.length];
  S.idx = i % S.playlist.length;

  const url     = resolveUrl(item.media_url || item.url || '');
  const isVideo = item.type === 'video' || /\.(mp4|webm|ogg|mov)$/i.test(url);
  const isPage  = item.type === 'page';

  if (isVideo)     renderVideo(url, item);
  else if (isPage) renderPage(url, item);
  else             renderImage(url, item);
}

function nextSlide() { showSlide(S.idx + 1); }

function renderImage(src, item) {
  const dur   = (item.duration || 10) * 1000;
  const stage = el('stage');
  stage.innerHTML = '';
  const img = new Image();
  img.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;';
  let fired = false;
  const show = () => {
    if (fired) return;
    fired = true;
    stage.appendChild(img);
    S.slideTimer = setTimeout(nextSlide, dur);
  };
  img.onload  = show;
  img.onerror = () => { if (!fired) { fired = true; S.slideTimer = setTimeout(nextSlide, 2000); } };
  img.src     = src;
  if (img.complete && img.naturalWidth) show();
}

function renderVideo(src, item) {
  const stage = el('stage');
  stage.innerHTML = `<video autoplay muted playsinline
    style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
    <source src="${esc(src)}" type="${src.endsWith('.webm') ? 'video/webm' : 'video/mp4'}">
  </video>`;
  const vid = stage.querySelector('video');
  vid.onended = nextSlide;
  vid.onerror = () => { S.slideTimer = setTimeout(nextSlide, 2000); };
  S.slideTimer = setTimeout(nextSlide, ((item.duration || 30) + 10) * 1000);
  vid.play().catch(() => { S.slideTimer = setTimeout(nextSlide, 3000); });
}

function renderPage(src, item) {
  el('stage').innerHTML = `<iframe src="${esc(src)}"
    style="position:absolute;inset:0;width:100%;height:100%;border:none;"
    sandbox="allow-scripts allow-same-origin allow-forms"></iframe>`;
  S.slideTimer = setTimeout(nextSlide, (item.duration || 30) * 1000);
}

function clearSlideTimer() {
  clearTimeout(S.slideTimer);
  S.slideTimer = null;
}

// ════════════════════════════════════════════════════════════════
//  STATUS GENÉRICA
// ════════════════════════════════════════════════════════════════
function setPhase(phase, icon, msg, sub) {
  S.phase = phase;
  clearSlideTimer();
  el('stage').innerHTML              = '';
  el('pairing-screen').style.display = 'none';
  el('status-screen').style.display  = 'flex';
  el('status-icon').textContent      = icon || '📺';
  el('status-msg').textContent       = msg  || '';
  el('status-sub').textContent       = sub  || '';
}

// ════════════════════════════════════════════════════════════════
//  UTILS
// ════════════════════════════════════════════════════════════════
function resolveUrl(url) {
  if (!url) return '';
  if (url.startsWith('http://') || url.startsWith('https://')) {
    try {
      const u = new URL(url);
      const i = u.pathname.indexOf('/uploads/');
      if (i >= 0) return BASE + u.pathname.slice(i);
    } catch {}
    return url;
  }
  if (url.startsWith('/uploads/')) return BASE + url;
  return url;
}

function el(id)        { return document.getElementById(id); }
function setText(id, t){ const e = el(id); if (e) e.textContent = t; }
function enc(s)        { return encodeURIComponent(s); }
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c =>
    ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
