/* ============================================================
   GVC Display — Player da TV

   Estados:
   1. PAIRING   → TV não configurada → mostra código + QR
   2. WAITING   → TV configurada mas sem playlist → aguardando
   3. PLAYING   → Exibe slides em loop

   Heartbeat a cada 5s detecta mudanças e recarrega automaticamente.
   ============================================================ */

const TOKEN = window.__DEVICE_TOKEN__ || '';
const BASE  = window.__BASE_URL__     || (() => {
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
  pairTimer:     null,
  pairCountdown: null,
  lastHash:      null,
  lastPlId:      null,
};

// ── Bootstrap ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  if (!TOKEN) {
    setPhase('error', '⚙️', 'Acesse pelo endereço /tv/ no navegador');
    return;
  }
  heartbeat();
  S.hbTimer = setInterval(heartbeat, 5000);
});

// ════════════════════════════════════════════════════════════════
//  HEARTBEAT
// ════════════════════════════════════════════════════════════════
async function heartbeat() {
  try {
    const r = await fetch(`${API}/devices/heartbeat`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: TOKEN }),
    });

    if (r.status === 401) {
      if (S.phase !== 'pairing') enterPairing();
      return;
    }
    if (!r.ok) {
      if (S.phase === 'init') setPhase('error', '📡', 'Sem conexão com o servidor');
      return;
    }

    const d = (await r.json()).data ?? {};

    // Não configurada → pareamento
    if (!d.configured) {
      if (S.phase !== 'pairing') enterPairing();
      return;
    }

    // Configurada mas sem playlist
    if (!d.playlist_id) {
      if (S.phase !== 'waiting') {
        exitPairing();
        setPhase('waiting', '📋', 'Aguardando playlist', 'Atribua uma playlist a esta TV no painel');
      }
      S.lastPlId = null;
      S.lastHash = null;
      return;
    }

    // Com playlist — saiu do pareamento/waiting?
    if (S.phase === 'pairing') exitPairing();

    // Playlist ou conteúdo mudou?
    if (d.playlist_id !== S.lastPlId || d.playlist_hash !== S.lastHash) {
      S.lastPlId = d.playlist_id;
      S.lastHash = d.playlist_hash;
      await loadAndPlay();
    }

  } catch (e) {
    if (S.phase === 'init') setPhase('error', '📡', 'Sem conexão', 'Tentando reconectar...');
  }
}

// ════════════════════════════════════════════════════════════════
//  PAREAMENTO
// ════════════════════════════════════════════════════════════════
function enterPairing() {
  S.phase = 'pairing';
  S.lastPlId = null;
  S.lastHash = null;
  clearSlideTimer();

  el('stage').innerHTML              = '';
  el('status-screen').style.display  = 'none';
  el('pairing-screen').style.display = 'flex';

  generatePairingCode();
}

function exitPairing() {
  clearInterval(S.pairTimer);
  clearInterval(S.pairCountdown);
  S.pairTimer = S.pairCountdown = null;
  el('pairing-screen').style.display = 'none';
}

async function generatePairingCode() {
  clearInterval(S.pairTimer);
  clearInterval(S.pairCountdown);

  setDigits('------');
  setText('pair-ttl', 'Gerando código...');

  try {
    const r    = await fetch(`${API}/pairing/generate?token=${enc(TOKEN)}`);
    const data = (await r.json()).data ?? {};
    const code = data.code;
    if (!code) throw new Error('sem código');

    setDigits(code);

    // QR contém os 6 dígitos (scanner do admin extrai \d{6})
    const qr = el('pair-qr');
    const fb = el('pair-qr-fallback');
    if (qr) {
      qr.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&bgcolor=ffffff&color=000000&qzone=1&data=${enc(code)}`;
      qr.style.display = 'block';
      if (fb) fb.style.display = 'none';
      qr.onerror = () => { qr.style.display = 'none'; if (fb) fb.style.display = 'flex'; };
    }

    const urlEl = el('pair-admin-url');
    if (urlEl) urlEl.textContent = BASE + '/';

    // Countdown 30 min
    let secs = 30 * 60;
    S.pairCountdown = setInterval(() => {
      secs--;
      const m = String(Math.floor(secs / 60)).padStart(2, '0');
      const s = String(secs % 60).padStart(2, '0');
      setText('pair-ttl', `Código válido por ${m}:${s}`);
      if (secs <= 0) {
        clearInterval(S.pairCountdown);
        generatePairingCode();
      }
    }, 1000);

    setText('pair-status-txt', 'Aguardando vinculação...');

  } catch {
    setDigits('------');
    setText('pair-ttl', 'Erro ao gerar código. Tentando em 15s...');
    S.pairTimer = setTimeout(generatePairingCode, 15000);
  }
}

function setDigits(code) {
  const d = document.getElementById('pair-digits');
  if (!d) return;
  const chars = String(code).padEnd(6, '-').slice(0, 6);
  d.innerHTML = chars.split('').map(c => `<span>${c}</span>`).join('');
}

// ════════════════════════════════════════════════════════════════
//  PLAYLIST & SLIDES
// ════════════════════════════════════════════════════════════════
async function loadAndPlay() {
  try {
    const r = await fetch(`${API}/devices/tv-playlist?token=${enc(TOKEN)}`);
    if (r.status === 204) {
      setPhase('waiting', '📺', 'Playlist vazia', 'Adicione itens no painel');
      return;
    }
    if (!r.ok) {
      console.warn('[GVC Player] tv-playlist HTTP', r.status);
      return;
    }

    const body = await r.json();
    const pl   = body.data ?? body;

    if (!pl.items?.length) {
      setPhase('waiting', '📺', 'Playlist vazia', 'Adicione itens no painel');
      return;
    }

    S.playlist = pl.items;
    S.phase    = 'playing';
    S.idx      = 0;

    el('status-screen').style.display  = 'none';
    el('pairing-screen').style.display = 'none';

    showSlide(0);

  } catch (e) {
    console.warn('[GVC Player] loadAndPlay error:', e);
  }
}

function showSlide(i) {
  clearSlideTimer();
  if (!S.playlist?.length) return;

  const item = S.playlist[i % S.playlist.length];
  S.idx = i % S.playlist.length;

  const url     = resolveUrl(item.media_url || item.url || '');
  const isVideo = item.type === 'video' || /\.(mp4|webm|ogg|mov)$/i.test(url);
  const isPage  = item.type === 'page';

  if (isVideo) renderVideo(url, item);
  else if (isPage) renderPage(url, item);
  else renderImage(url, item);
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
  el('stage').innerHTML               = '';
  el('pairing-screen').style.display  = 'none';
  el('status-screen').style.display   = 'flex';
  el('status-icon').textContent       = icon || '📺';
  el('status-msg').textContent        = msg  || '';
  el('status-sub').textContent        = sub  || '';
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
function setText(id, t) { const e = el(id); if (e) e.textContent = t; }
function enc(s)        { return encodeURIComponent(s); }
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c =>
    ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
