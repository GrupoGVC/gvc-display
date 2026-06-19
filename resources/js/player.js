/* ============================================================
   GVC Display — Player da TV  (reescrito)

   Lógica de estados:
   1. PAIRING   → TV nunca foi configurada → mostra código + QR
   2. WAITING   → TV configurada mas sem playlist → "Aguardando..."
   3. PLAYING   → Exibe slides em loop

   Transições automáticas via heartbeat a cada 5s.
   Detecta mudanças de hash e recarrega playlist sem refresh.
   ============================================================ */

const TOKEN = window.__DEVICE_TOKEN__ || '';
const BASE  = window.__BASE_URL__      || (() => {
  const p = window.location.pathname
    .replace(/\/tv(\/[^/]*)?$/, '')
    .replace(/\/+$/, '');
  return window.location.origin + p;
})();
const API = BASE + '/api';

// ── Estados ───────────────────────────────────────────────────
const S = {
  phase:        'init',   // 'pairing' | 'waiting' | 'playing'
  playlist:     [],
  idx:          0,
  slideTimer:   null,
  hbTimer:      null,
  pairTimer:    null,
  pairCountdown:null,
  lastHash:     null,
  lastPlId:     null,
};

// ── Bootstrap ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  if (!TOKEN) {
    setPhase('error', '⚙️', 'Acesse pelo endereço /tv/ no navegador');
    return;
  }
  heartbeat();                         // primeiro heartbeat imediato
  S.hbTimer = setInterval(heartbeat, 5000); // depois a cada 5s
});

// ════════════════════════════════════════════════════════════════
//  HEARTBEAT — cérebro de toda a lógica de estado
// ════════════════════════════════════════════════════════════════
async function heartbeat() {
  try {
    const r = await fetch(`${API}/devices/heartbeat`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: TOKEN }),
    });

    // Token inválido → TV deletada do painel
    if (r.status === 401) {
      if (S.phase !== 'pairing') enterPairing();
      return;
    }
    if (!r.ok) {
      if (S.phase === 'init') setPhase('error', '📡', 'Sem conexão com o servidor');
      return;
    }

    const d = (await r.json()).data ?? {};

    // ── Não configurada → pareamento ──────────────────────────
    if (!d.configured) {
      if (S.phase !== 'pairing') enterPairing();
      return;
    }

    // ── Configurada mas sem playlist → aguardando ─────────────
    if (!d.playlist_id) {
      if (S.phase !== 'waiting') setPhase('waiting', '📋', 'Aguardando playlist', 'Atribua uma playlist a esta TV no painel');
      return;
    }

    // ── Com playlist ──────────────────────────────────────────
    // Saiu do pareamento? Limpa tudo
    if (S.phase === 'pairing') exitPairing();

    // Playlist mudou (hash diferente ou id diferente)?
    if (d.playlist_id !== S.lastPlId || d.playlist_hash !== S.lastHash) {
      S.lastHash = d.playlist_hash;
      S.lastPlId = d.playlist_id;
      await loadAndPlay(d.playlist_id);
    }

  } catch {
    // Sem internet — continua exibindo o que tem
    if (S.phase === 'init') setPhase('error', '📡', 'Sem conexão', 'Tentando reconectar...');
  }
}

// ════════════════════════════════════════════════════════════════
//  PAREAMENTO
// ════════════════════════════════════════════════════════════════
function enterPairing() {
  S.phase = 'pairing';
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
  // Limpa timers anteriores
  clearInterval(S.pairTimer);
  clearInterval(S.pairCountdown);

  // Mostra traços enquanto gera
  setDigits('------');
  setText('pair-ttl', 'Gerando código...');

  try {
    const r    = await fetch(`${API}/pairing/generate?token=${enc(TOKEN)}`);
    const data = (await r.json()).data ?? {};
    const code = data.code;
    if (!code) throw new Error('sem código');

    setDigits(code);

    // QR Code aponta para o painel admin
    const qr = el('pair-qr');
    if (qr) {
      qr.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&bgcolor=ffffff&color=000000&qzone=1&data=${enc(BASE + '/')}`;
      qr.style.display = 'block';
      if (qr.nextElementSibling) qr.nextElementSibling.style.display = 'none';
    }

    const urlEl = el('pair-admin-url');
    if (urlEl) urlEl.textContent = BASE + '/';

    // Contador 30 minutos
    let secs = 30 * 60;
    S.pairCountdown = setInterval(() => {
      secs--;
      const m = String(Math.floor(secs / 60)).padStart(2, '0');
      const s = String(secs % 60).padStart(2, '0');
      setText('pair-ttl', `Código válido por ${m}:${s}`);
      if (secs <= 0) {
        clearInterval(S.pairCountdown);
        generatePairingCode(); // renova automaticamente
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
  const el = document.getElementById('pair-digits');
  if (!el) return;
  const chars = String(code).padEnd(6, '-').slice(0, 6);
  el.innerHTML = chars.split('').map(d => `<span>${d}</span>`).join('');
}

// ════════════════════════════════════════════════════════════════
//  PLAYLIST & SLIDES
// ════════════════════════════════════════════════════════════════
async function loadAndPlay(plId) {
  try {
    const r  = await fetch(`${API}/playlists/${plId}`, {
      headers: { Authorization: `Bearer ${TOKEN}` },
    });
    if (!r.ok) return;
    const pl = (await r.json()).data ?? {};

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
  } catch {
    // Mantém o que está exibindo — tenta no próximo heartbeat
  }
}

function showSlide(i) {
  clearSlideTimer();
  if (!S.playlist?.length) return;

  const item = S.playlist[i % S.playlist.length];
  S.idx = i % S.playlist.length;

  const url = resolveUrl(item.media_url || item.url || '');
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

  const img    = new Image();
  img.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;';
  img.onload  = () => { stage.appendChild(img); S.slideTimer = setTimeout(nextSlide, dur); };
  img.onerror = () => { S.slideTimer = setTimeout(nextSlide, 2000); };
  img.src     = src;

  // Se já está em cache e carregada
  if (img.complete && img.naturalWidth) {
    stage.appendChild(img);
    S.slideTimer = setTimeout(nextSlide, dur);
  }
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
  // Safety timeout: duração + 10s para vídeos sem metadata
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
//  TELA DE STATUS GENÉRICA
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

function el(id)       { return document.getElementById(id); }
function setText(id, t) { const e = el(id); if (e) e.textContent = t; }
function enc(s)       { return encodeURIComponent(s); }
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c =>
    ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
