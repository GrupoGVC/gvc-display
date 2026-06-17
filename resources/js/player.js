/* ============================================================
   GVC Display — Player da TV
   ============================================================ */

// Token e slug injetados pelo tv.php via json_encode (valores reais, sem placeholder)
const DEVICE_TOKEN = window.__DEVICE_TOKEN__ || '';
const DEVICE_SLUG  = window.__DEVICE_SLUG__  || '';

// BASE dinâmico — injetado pelo tv.php via window.__BASE_URL__
// Fallback: detecta pelo pathname (acesso direto ao HTML)
const BASE = window.__BASE_URL__ ||
  (window.location.origin +
    window.location.pathname
      .replace(/\/tv\/[^/]*\/?$/, '')
      .replace(/\/tv\/?$/, '')
      .replace(/\/html\/player\.html.*$/, '')
      .replace(/\/+$/, ''));
const API = BASE + '/api';

// ── Estado ────────────────────────────────────────────────────
const state = {
  playlist:     null,
  currentIndex: 0,
  timer:        null,
  heartbeatInt: null,
  lastHash:     null,
  pairingInt:   null,
  isPairing:    false,
  pairingCode:  null,
};

// ── Init ──────────────────────────────────────────────────────
async function init() {
  if (!DEVICE_TOKEN) {
    // Sem token injetado — tv.php não foi usado (acesso direto ao HTML)
    showStatus('📺', 'Configure esta TV', 'Acesse pelo endereço /tv/ no seu navegador');
    return;
  }

  // Injeta a URL do painel no QR (ex: https://display.drc-gvc.tech/index.php)
  const adminUrl = BASE + '/';
  const adminUrlEl = document.getElementById('pair-admin-url');
  if (adminUrlEl) adminUrlEl.textContent = adminUrl;

  // Inicia heartbeat imediatamente
  await heartbeat();
  state.heartbeatInt = setInterval(heartbeat, 8000);
}

// ── Heartbeat ─────────────────────────────────────────────────
async function heartbeat() {
  try {
    const res = await fetch(`${API}/devices/heartbeat`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ token: DEVICE_TOKEN }),
    });

    if (!res.ok) {
      // 401 = token inválido (device deletado/resetado) → mostra pareamento novamente
      if (res.status === 401) {
        if (!state.isPairing) showPairing();
      } else {
        showStatus('📡', 'Sem conexão', 'Tentando reconectar...');
      }
      return;
    }

    const raw  = await res.text();
    const data = JSON.parse(raw.replace(/^[^{[]*/, ''));
    const d    = data.data ?? data;

    // ── Verifica se a TV já foi confirmada pelo admin ────────
    // configured=false → TV criada automaticamente, nunca pareada
    // configured=true  → admin já deu nome, mas pode não ter playlist ainda
    if (!d.configured) {
      if (!state.isPairing) showPairing();
      return;
    }

    // TV configurada mas sem playlist → aguardando atribuição
    if (!d.playlist_id) {
      showStatus('📋', 'Aguardando playlist',
        'Acesse o painel e atribua uma playlist a esta TV');
      return;
    }

    // ── Com playlist → esconde pareamento e mostra conteúdo ───
    if (state.isPairing) stopPairing();

    document.getElementById('status-screen').style.display  = 'none';

    // Recarrega playlist só se o conteúdo mudou (hash diferente)
    if (d.playlist_hash !== state.lastHash) {
      state.lastHash = d.playlist_hash;
      await loadPlaylist(d.playlist_id);
    }

  } catch {
    // Sem conexão com o servidor — não sai do que está exibindo
    if (!state.playlist) {
      showStatus('📡', 'Sem conexão', 'Tentando reconectar...');
    }
    // Se já está exibindo conteúdo, continua normalmente até reconectar
  }
}

// ── Para o estado de pareamento ───────────────────────────────
function stopPairing() {
  state.isPairing  = false;
  state.pairingCode = null;
  if (state.pairingInt) { clearInterval(state.pairingInt); state.pairingInt = null; }
  document.getElementById('pairing-screen').style.display = 'none';
}

// ── Playlist ──────────────────────────────────────────────────
async function loadPlaylist(id) {
  try {
    const res  = await fetch(`${API}/playlists/${id}`, {
      headers: { Authorization: `Bearer ${DEVICE_TOKEN}` },
    });
    if (!res.ok) return;
    const raw  = await res.text();
    const data = JSON.parse(raw.replace(/^[^{[]*/, ''));
    const pl   = data.data ?? data;

    if (!pl?.items?.length) {
      showStatus('📺', 'Playlist vazia', 'Adicione itens no painel administrativo');
      return;
    }

    state.playlist     = pl.items;
    state.currentIndex = 0;
    clearTimeout(state.timer);

    document.getElementById('status-screen').style.display  = 'none';
    document.getElementById('pairing-screen').style.display = 'none';

    showSlide(0);
  } catch {
    showStatus('⚠️', 'Erro ao carregar playlist', 'Tentando novamente em breve...');
  }
}

// ── Player ────────────────────────────────────────────────────
function showSlide(index) {
  const items = state.playlist;
  if (!items?.length) return;
  const item = items[index % items.length];
  state.currentIndex = index % items.length;
  clearTimeout(state.timer);

  const url = resolveUrl(item.media_url || item.url || '');
  if      (item.type === 'video') showVideo(url, item);
  else if (item.type === 'page')  showPage(url, item);
  else                            showImage(url, item);
}

function next() { showSlide(state.currentIndex + 1); }

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

function showImage(src, item) {
  const dur   = (item.duration || 10) * 1000;
  const stage = document.getElementById('stage');
  stage.innerHTML = '';

  const img = new Image();
  img.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;';
  img.onload  = () => { stage.appendChild(img); state.timer = setTimeout(next, dur); };
  img.onerror = () => { state.timer = setTimeout(next, 2000); };
  img.src = src;
  if (img.complete && img.naturalWidth) {
    stage.appendChild(img);
    state.timer = setTimeout(next, dur);
  }
}

function showVideo(src, item) {
  const stage = document.getElementById('stage');
  stage.innerHTML = `
    <video src="${esc(src)}" autoplay muted playsinline
      style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
    </video>`;
  const vid = stage.querySelector('video');
  vid.onended = next;
  vid.onerror = () => { state.timer = setTimeout(next, 2000); };
  state.timer = setTimeout(next, ((item.duration || 60) + 10) * 1000);
  vid.play().catch(() => { state.timer = setTimeout(next, 3000); });
}

function showPage(src, item) {
  const stage = document.getElementById('stage');
  stage.innerHTML = `<iframe src="${esc(src)}"
    style="position:absolute;inset:0;width:100%;height:100%;border:none;"
    sandbox="allow-scripts allow-same-origin allow-forms"></iframe>`;
  state.timer = setTimeout(next, (item.duration || 30) * 1000);
}

// ── Tela de Status ────────────────────────────────────────────
function showStatus(icon, msg, sub) {
  clearTimeout(state.timer);
  document.getElementById('stage').innerHTML               = '';
  document.getElementById('pairing-screen').style.display = 'none';
  document.getElementById('status-screen').style.display  = 'flex';
  document.getElementById('status-icon').textContent      = icon;
  document.getElementById('status-msg').textContent       = msg;
  document.getElementById('status-sub').textContent       = sub || '';
}

// ── Tela de Pareamento ────────────────────────────────────────
async function showPairing() {
  state.isPairing = true;
  clearTimeout(state.timer);
  document.getElementById('stage').innerHTML               = '';
  document.getElementById('status-screen').style.display  = 'none';
  document.getElementById('pairing-screen').style.display = 'flex';
  await generateCode();
}

async function generateCode() {
  const digitsEl = document.getElementById('pair-digits');
  const qrEl     = document.getElementById('pair-qr');
  const ttlEl    = document.getElementById('pair-ttl');
  const statusEl = document.getElementById('pair-status-txt');

  // Mostra traços enquanto carrega
  if (digitsEl) {
    digitsEl.innerHTML = '<span>-</span><span>-</span><span>-</span><span>-</span><span>-</span><span>-</span>';
  }
  if (ttlEl) ttlEl.textContent = 'Gerando código...';

  try {
    const res  = await fetch(`${API}/pairing/generate?token=${encodeURIComponent(DEVICE_TOKEN)}`);
    const raw  = await res.text();
    const data = JSON.parse(raw.replace(/^[^{[]*/, ''));
    const code = data.data?.code ?? data.code;
    if (!code) throw new Error('sem código');

    state.pairingCode = code;

    // Exibe os 6 dígitos separados
    if (digitsEl) {
      digitsEl.innerHTML = code.split('').map(d => `<span>${d}</span>`).join('');
    }

    // QR Code aponta para o painel admin
    const adminUrl = BASE + '/';
    const qrData   = encodeURIComponent(adminUrl);
    const qrUrl    = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${qrData}&bgcolor=ffffff&color=000000&qzone=1`;
    if (qrEl) {
      qrEl.src = qrUrl;
    }

    // Atualiza URL do painel no rodapé do QR
    const adminUrlEl = document.getElementById('pair-admin-url');
    if (adminUrlEl) adminUrlEl.textContent = adminUrl;

    // Contador regressivo 30 minutos
    let secs = 30 * 60;
    if (state.pairingInt) clearInterval(state.pairingInt);
    state.pairingInt = setInterval(() => {
      secs--;
      const m = String(Math.floor(secs / 60)).padStart(2, '0');
      const s = String(secs % 60).padStart(2, '0');
      if (ttlEl) ttlEl.textContent = `Código válido por ${m}:${s}`;
      if (secs <= 0) {
        clearInterval(state.pairingInt);
        generateCode(); // renova automaticamente
      }
    }, 1000);

    if (statusEl) statusEl.textContent = 'Aguardando vinculação...';

  } catch {
    if (digitsEl) digitsEl.innerHTML = '<span>-</span><span>-</span><span>-</span><span>-</span><span>-</span><span>-</span>';
    if (ttlEl) ttlEl.textContent = 'Erro ao gerar código. Tentando novamente...';
    setTimeout(generateCode, 10000);
  }
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// ── Start ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', init);
