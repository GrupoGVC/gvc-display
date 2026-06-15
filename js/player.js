/* ============================================================
   GVC Display — Player JS
   Fullscreen kiosk para Smart TVs
   ============================================================ */

"use strict";

// ── QR Code Generator (pure JS, sem dependência externa) ─────
// Baseado em algoritmo QR Code simples para URLs curtas
function generateQRSVG(text, size = 200) {
  // Usa a API do QR Server como fallback robusto
  return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(text)}&bgcolor=ffffff&color=000000&margin=10`;
}

// ── Config ────────────────────────────────────────────────────
// Resolve URL relativa ou absoluta de mídia
function mediaUrl(url) {
  if (!url) return "";
  if (url.startsWith("http://") || url.startsWith("https://")) return url;
  return API_BASE.replace(/\/api$/, "") + url;
}

const API_BASE = (() => {
  const u = new URL(location.href);
  // /html/player.html → raiz do projeto
  return u.origin + u.pathname.replace(/\/html\/.*$/, "") + "/api";
})();

const HEARTBEAT_MS = 5_000;
const PAIRING_POLL = 5_000;
const PAIRING_TTL = 30 * 60; // segundos (30 minutos)

const DEBUG = new URLSearchParams(location.search).has("debug");
if (DEBUG) document.getElementById("debug").style.display = "block";

// ── Estado ────────────────────────────────────────────────────
let token =
  new URLSearchParams(location.search).get("token") ||
  localStorage.getItem("gvc_tv_token");
let curHash = null;
let curPlaylist = null;
let curIdx = 0;
let slideTimer = null;
let heartbeatTimer = null;
let pairingCode = null;
let pairingInterval = null;
let pairingPoll = null;

// ── Init ──────────────────────────────────────────────────────
async function init() {
  if (token) {
    localStorage.setItem("gvc_tv_token", token);
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
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token }),
    });

    if (res.status === 404) {
      localStorage.removeItem("gvc_tv_token");
      token = null;
      clearInterval(heartbeatTimer);
      await startPairing();
      return;
    }

    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    hideError();
    const json = await res.json();
    const pl = json.data?.playlist;

    if (!pl?.items?.length) {
      showError("Nenhuma apresentação configurada");
      return;
    }

    if (pl.hash !== curHash) {
      dbg(`Nova playlist: ${pl.name} (${pl.items.length} itens)`);
      curHash = pl.hash;
      curPlaylist = pl;
      clearTimeout(slideTimer);
      buildSlides();
      showSlide(0);
    }
  } catch (e) {
    showError("Sem conexão — reconectando...");
    dbg("Heartbeat error: " + e.message);
  }
}

// ── Slides ────────────────────────────────────────────────────
function buildSlides() {
  const stage = document.getElementById("stage");
  stage.innerHTML = "";

  curPlaylist.items.forEach((item, i) => {
    const slide = document.createElement("div");
    slide.className = "slide";
    slide.dataset.idx = i;

    const src = mediaUrl(item.media_url || item.url || "");

    if (item.type === "video") {
      const v = document.createElement("video");
      v.src = src;
      v.muted = true;
      v.playsInline = true;
      v.preload = "auto";
      v.setAttribute("playsinline", "");
      v.addEventListener("ended", nextSlide);
      v.addEventListener("error", nextSlide);
      slide.appendChild(v);
    } else if (item.type === "page") {
      const f = document.createElement("iframe");
      f.src = src;
      f.sandbox = "allow-scripts allow-same-origin allow-forms";
      slide.appendChild(f);
    } else {
      const img = document.createElement("img");
      img.alt = "";
      img.style.cssText = "width:100%;height:100%;object-fit:contain;";
      // Preload: não usar lazy em slideshow
      img.addEventListener("error", nextSlide);
      img.src = src;
      slide.appendChild(img);
    }

    stage.appendChild(slide);
  });
}

function showSlide(idx) {
  if (!curPlaylist?.items?.length) return;
  clearTimeout(slideTimer);

  const items = curPlaylist.items;
  curIdx = ((idx % items.length) + items.length) % items.length;
  const item = items[curIdx];

  document.querySelectorAll(".slide").forEach((s, i) => {
    s.classList.remove("active", "prev");
    if (i === curIdx) s.classList.add("active");
  });

  dbg(
    `[${curIdx + 1}/${items.length}] ${item.type} — ${item.media_url || item.url || ""}`,
  );

  const vid = document.querySelector(`.slide.active video`);
  if (vid) {
    vid.currentTime = 0;
    vid.play().catch(() => scheduleNext(item.duration || 10));
    // Timeout de segurança caso o vídeo trave
    slideTimer = setTimeout(nextSlide, ((item.duration || 60) + 30) * 1000);
    updateHud(item.duration || 30);
    return;
  }

  // Para imagens: garantir que o timer só começa após carregar
  const imgEl = document.querySelector(".slide.active img");
  if (imgEl) {
    const dur = (item.duration || 10) * 1000;
    if (imgEl.complete && imgEl.naturalWidth > 0) {
      scheduleNext(item.duration || 10);
    } else {
      imgEl.onload = () => scheduleNext(item.duration || 10);
      imgEl.onerror = () => scheduleNext(2); // pula rápido se erro
      // Fallback: se não carregar em 5s, avança mesmo assim
      slideTimer = setTimeout(nextSlide, Math.max(dur, 5000));
    }
    updateHud(item.duration || 10);
    return;
  }

  scheduleNext(item.duration || 10);
}

function scheduleNext(seconds) {
  clearTimeout(slideTimer);
  updateHud(seconds);
  slideTimer = setTimeout(nextSlide, seconds * 1000);
}

function nextSlide() {
  if (curPlaylist?.items?.length) showSlide(curIdx + 1);
}

// ── HUD bar ───────────────────────────────────────────────────
function updateHud(seconds) {
  const bar = document.getElementById("hud-bar");
  bar.style.transition = "none";
  bar.style.width = "0%";
  requestAnimationFrame(() => {
    bar.style.transition = `width ${seconds}s linear`;
    bar.style.width = "100%";
  });
}

// ── Pairing ───────────────────────────────────────────────────
async function startPairing() {
  showPairing();
  clearInterval(pairingPoll);
  clearInterval(pairingInterval);

  try {
    // Passa slug da URL (?slug=sala1) para associar ao device quando parear
    const urlSlug =
      new URLSearchParams(window.location.search).get("slug") || "";
    const res = await fetch(`${API_BASE}/pairing/index.php?action=generate`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ slug: urlSlug }),
    });
    if (!res.ok) throw new Error("Erro ao gerar código");
    const json = await res.json();
    pairingCode = json.data.code;

    document.getElementById("pair-code").textContent = pairingCode;
    document.getElementById("pair-hint").textContent =
      "Aguardando vinculação...";

    // ── Gera QR Code com URL do painel admin ──────────────────
    // URL do painel: raiz do projeto (sem /api e sem /html)
    const adminBase = API_BASE.replace(/\/api$/, "");
    const adminUrl = adminBase + "/index.html";

    // Mostra a URL embaixo do QR
    const qrUrlEl = document.getElementById("qr-admin-url");
    if (qrUrlEl) qrUrlEl.textContent = adminUrl;

    // QR Code — tenta múltiplas APIs com fallback
    const qrBox = document.getElementById("qr-box");
    if (qrBox) {
      const sz = Math.round(qrBox.getBoundingClientRect().width) || 220;

      // Lista de APIs de QR code (fallback em ordem)
      const qrApis = [
        `https://api.qrserver.com/v1/create-qr-code/?size=${sz}x${sz}&data=${encodeURIComponent(adminUrl)}&bgcolor=ffffff&color=000000&margin=10`,
        `https://quickchart.io/qr?text=${encodeURIComponent(adminUrl)}&size=${sz}&margin=10`,
        `https://chart.googleapis.com/chart?cht=qr&chs=${sz}x${sz}&chl=${encodeURIComponent(adminUrl)}&chld=M|1`,
      ];

      const img = document.createElement("img");
      img.alt = "QR Code";
      img.style.cssText =
        "width:100%;height:100%;border-radius:8px;display:block;";

      let apiIdx = 0;
      img.onerror = () => {
        apiIdx++;
        if (apiIdx < qrApis.length) {
          img.src = qrApis[apiIdx];
        } else {
          // Fallback: mostra código em destaque
          qrBox.innerHTML = `
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:8px;">
              <p style="color:#888;font-size:11px;text-align:center;">QR indisponível</p>
              <p style="color:#4f8cff;font-size:28px;font-family:monospace;font-weight:700;letter-spacing:8px;">${pairingCode}</p>
              <p style="color:#888;font-size:10px;">Digite no painel admin</p>
            </div>`;
        }
      };
      img.src = qrApis[0];
      qrBox.innerHTML = "";
      qrBox.appendChild(img);
    }

    // Countdown
    let ttl = PAIRING_TTL;
    const tick = () => {
      const m = Math.floor(ttl / 60),
        s = String(ttl % 60).padStart(2, "0");
      document.getElementById("pair-timer").textContent =
        `Código válido por ${m}:${s}`;
      if (--ttl < 0) {
        clearInterval(pairingInterval);
        startPairing();
      }
    };
    tick();
    pairingInterval = setInterval(tick, 1000);

    // Poll: checar vinculação
    pairingPoll = setInterval(async () => {
      try {
        const r = await fetch(`${API_BASE}/pairing/index.php?action=check`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ code: pairingCode }),
        });
        const j = await r.json();
        if (j.data?.paired) {
          clearInterval(pairingPoll);
          clearInterval(pairingInterval);
          token = j.data.token;
          localStorage.setItem("gvc_tv_token", token);
          const url = new URL(location.href);
          url.searchParams.set("token", token);
          history.replaceState(null, "", url.toString());
          hidePairing();
          await heartbeat();
          heartbeatTimer = setInterval(heartbeat, HEARTBEAT_MS);
        }
      } catch {}
    }, PAIRING_POLL);
  } catch {
    document.getElementById("pair-hint").textContent =
      "Sem conexão. Tentando novamente...";
    setTimeout(startPairing, 10_000);
  }
}

// ── UI helpers ────────────────────────────────────────────────
function showPairing() {
  document.getElementById("pairing").classList.remove("hidden");
}
function hidePairing() {
  document.getElementById("pairing").classList.add("hidden");
}

function showError(msg) {
  document.querySelector("#error-screen .err-msg").textContent = msg;
  document.getElementById("error-screen").classList.add("show");
}
function hideError() {
  document.getElementById("error-screen").classList.remove("show");
}

function dbg(msg) {
  if (DEBUG) document.getElementById("debug").textContent = msg;
}

// ── Fullscreen ────────────────────────────────────────────────
function goFullscreen() {
  const el = document.documentElement;
  (
    el.requestFullscreen ||
    el.webkitRequestFullscreen ||
    el.mozRequestFullScreen ||
    el.msRequestFullscreen ||
    Function()
  ).call(el);
}
["click", "touchstart"].forEach((ev) =>
  document.addEventListener(ev, goFullscreen, { once: true }),
);
setTimeout(goFullscreen, 500);

// ── Anti-sleep ────────────────────────────────────────────────
setInterval(
  () =>
    document.dispatchEvent(
      new MouseEvent("mousemove", {
        bubbles: true,
        clientX: Math.random(),
        clientY: Math.random(),
      }),
    ),
  60_000,
);

// ── Start ─────────────────────────────────────────────────────
init();
