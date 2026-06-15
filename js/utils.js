/* ============================================================
   GVC Signage — Utilitários compartilhados
   ============================================================ */

"use strict";

// ── DOM ───────────────────────────────────────────────────────
export const q = (sel, ctx = document) => ctx.querySelector(sel);
export const qa = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

// ── Escape HTML ───────────────────────────────────────────────
export function esc(str) {
  const d = document.createElement("div");
  d.textContent = str ?? "";
  return d.innerHTML;
}

// ── Modal helpers ─────────────────────────────────────────────
export const openModal = (id) => {
  const el = document.getElementById(id);
  if (!el) return;
  // Usa Bootstrap Modal se disponível, senão fallback para hidden
  if (window.bootstrap?.Modal) {
    bootstrap.Modal.getOrCreateInstance(el).show();
  } else {
    el.classList.remove("hidden");
  }
};
export const closeModal = (id) => {
  const el = document.getElementById(id);
  if (!el) return;
  if (window.bootstrap?.Modal) {
    const m = bootstrap.Modal.getInstance(el);
    if (m) m.hide();
  } else {
    el.classList.add("hidden");
  }
};

// ── Toast ─────────────────────────────────────────────────────
const ICONS = {
  ok:   '<i class="fi fi-rr-check-circle" style="font-size:15px;color:var(--green);flex-shrink:0;"></i>',
  err:  '<i class="fi fi-rr-exclamation" style="font-size:15px;color:var(--red);flex-shrink:0;"></i>',
  info: '<i class="fi fi-rr-info" style="font-size:15px;color:var(--primary);flex-shrink:0;"></i>',
  warn: '<i class="fi fi-rr-triangle-warning" style="font-size:15px;color:var(--yellow);flex-shrink:0;"></i>',
  copy: '<i class="fi fi-rr-copy-alt" style="font-size:15px;color:var(--primary);flex-shrink:0;"></i>',
};

export function toast(msg, type = "ok", duration = 4000) {
  const wrap = document.getElementById("toasts");
  if (!wrap) return;
  const t = document.createElement("div");
  t.className = `tst${type === "err" ? " err" : type === "warn" ? " warn" : type === "copy" ? " copy" : ""}`;
  t.innerHTML = `${ICONS[type] ?? ICONS.info}<span>${esc(msg)}</i>`;
  wrap.prepend(t);
  setTimeout(() => t.remove(), duration);
}

// ── Copy to clipboard ─────────────────────────────────────────
export function copyText(text, btnEl = null) {
  const orig = btnEl?.textContent;
  navigator.clipboard
    ?.writeText(text)
    .then(() => {
      toast("Copiado para a área de transferência!", "copy");
      if (btnEl) {
        btnEl.textContent = "✓ Copiado";
        btnEl.style.color = "var(--green)";
        setTimeout(() => {
          btnEl.textContent = orig;
          btnEl.style.color = "";
        }, 2000);
      }
    })
    .catch(() => toast("Não foi possível copiar — selecione manualmente", "err"));
}

// ── Format helpers ────────────────────────────────────────────
export function fmtDate(str) {
  if (!str) return "—";
  const d = new Date(str);
  return isNaN(d)
    ? str
    : d.toLocaleString("pt-BR", {
        day: "2-digit",
        month: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
      });
}

export function fmtSize(bytes) {
  if (!bytes) return "—";
  if (bytes < 1024) return bytes + " B";
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
  return (bytes / 1048576).toFixed(1) + " MB";
}

// ── Populate <select> ─────────────────────────────────────────
export function populateSelect(
  sel,
  items,
  labelKey,
  {
    emptyLabel = null,
    emptyValue = "",
    selectedVal = null,
    prependOption = null,
  } = {},
) {
  const el = typeof sel === "string" ? document.getElementById(sel) : sel;
  if (!el) return;
  el.innerHTML = "";

  if (prependOption) {
    const o = document.createElement("option");
    o.value = prependOption.value;
    o.textContent = prependOption.label;
    el.appendChild(o);
  }

  if (emptyLabel) {
    const o = document.createElement("option");
    o.value = emptyValue;
    o.textContent = `— ${emptyLabel} —`;
    el.appendChild(o);
  }

  items.forEach((item) => {
    const o = document.createElement("option");
    o.value = item.id;
    o.textContent = item[labelKey];
    if (selectedVal !== null && String(item.id) === String(selectedVal))
      o.selected = true;
    el.appendChild(o);
  });
}

// ── Confirm modal (substitui window.confirm — Heurística Nielsen #1) ──────
export function confirmAction(msg, onConfirm, { danger = true, confirmLabel = "Confirmar", cancelLabel = "Cancelar" } = {}) {
  // Remove modal anterior se existir
  const existing = document.getElementById("_confirm-modal");
  if (existing) existing.remove();

  const overlay = document.createElement("div");
  overlay.id = "_confirm-modal";
  overlay.style.cssText = `
    position:fixed;inset:0;z-index:9998;display:flex;align-items:center;justify-content:center;
    background:rgba(0,0,0,.6);backdrop-filter:blur(4px);animation:tsIn .15s ease;
  `;

  overlay.innerHTML = `
    <div style="background:var(--bg2);border:1px solid var(--bord2);border-radius:14px;padding:28px 28px 22px;
                max-width:360px;width:90%;box-shadow:0 16px 48px rgba(0,0,0,.6);">
      <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;">
        <i class="fi ${danger ? 'fi-rr-trash' : 'fi-rr-info'}" 
           style="font-size:22px;color:${danger ? 'var(--red)' : 'var(--primary)'};flex-shrink:0;margin-top:2px;"></i>
        <p style="font-size:14px;color:var(--txt);line-height:1.5;margin:0;">${msg}</p>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button id="_confirm-cancel" style="background:rgba(255,255,255,.05);border:1px solid rgba(244,63,94,.4);
                border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;color:var(--red);cursor:pointer;">
          ${cancelLabel}
        </button>
        <button id="_confirm-ok" style="background:${danger ? 'var(--red)' : 'var(--primary)'};border:none;
                border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;color:#fff;cursor:pointer;">
          ${confirmLabel}
        </button>
      </div>
    </div>
  `;

  document.body.appendChild(overlay);

  const close = () => overlay.remove();
  overlay.querySelector("#_confirm-cancel").onclick = close;
  overlay.querySelector("#_confirm-ok").onclick = () => { close(); onConfirm(); };
  overlay.onclick = (e) => { if (e.target === overlay) close(); };
}