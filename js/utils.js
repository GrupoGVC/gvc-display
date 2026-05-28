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
  ok:   '<span class="msi" style="font-size:16px;color:var(--green)">check_circle</span>',
  err:  '<span class="msi" style="font-size:16px;color:var(--red)">error</span>',
  info: '<span class="msi" style="font-size:16px;color:var(--primary)">info</span>',
};

export function toast(msg, type = "ok", duration = 4000) {
  const wrap = document.getElementById("toasts");
  if (!wrap) return;
  const t = document.createElement("div");
  t.className = `tst${type === "err" ? " err" : ""}`;
  t.innerHTML = `${ICONS[type] ?? ICONS.info}<span>${esc(msg)}</span>`;
  wrap.prepend(t);
  setTimeout(() => t.remove(), duration);
}

// ── Copy to clipboard ─────────────────────────────────────────
export function copyText(text) {
  navigator.clipboard
    ?.writeText(text)
    .then(() => toast("Copiado!", "info"))
    .catch(() => toast("Copie manualmente", "info"));
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
