// ── Seletores ─────────────────────────────────────────────────
export const q  = (s) => document.querySelector(s);
export const qa = (s) => [...document.querySelectorAll(s)];
export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c =>
  ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

// ── Modal Bootstrap ───────────────────────────────────────────
export function openModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  bootstrap.Modal.getOrCreateInstance(el).show();
}
export function closeModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  bootstrap.Modal.getInstance(el)?.hide();
}

// ── Toast ─────────────────────────────────────────────────────
export function toast(msg, type = 'ok') {
  const wrap = document.getElementById('toasts');
  if (!wrap) return;
  const t = document.createElement('div');
  t.className = `gvc-toast ${type === 'err' ? 'toast-err' : 'toast-ok'}`;
  t.innerHTML = `<span>${esc(msg)}</span>`;
  wrap.appendChild(t);
  requestAnimationFrame(() => t.classList.add('show'));
  setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 320); }, 3500);
}

// ── Copy ──────────────────────────────────────────────────────
export function copyText(text) {
  navigator.clipboard?.writeText(text).then(() => toast('Copiado!')).catch(() => {});
}

// ── Formato de data ───────────────────────────────────────────
export function fmtDate(d) {
  if (!d) return '—';
  const dt = new Date(d.replace(' ', 'T') + (d.includes('Z') ? '' : 'Z'));
  return dt.toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo',
    day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
}

// ── Formato de tamanho ────────────────────────────────────────
export function fmtSize(bytes) {
  if (!bytes) return '0 B';
  const u = ['B','KB','MB','GB'];
  let i = 0, b = bytes;
  while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
  return b.toFixed(i > 0 ? 1 : 0) + ' ' + u[i];
}

// ── Popular <select> ──────────────────────────────────────────
export function populateSelect(id, items, labelKey, opts = {}) {
  const sel = document.getElementById(id);
  if (!sel) return;
  sel.innerHTML = '';
  if (opts.emptyLabel !== undefined) {
    sel.insertAdjacentHTML('beforeend', `<option value="">${esc(opts.emptyLabel)}</option>`);
  }
  if (opts.prependOption) {
    sel.insertAdjacentHTML('beforeend',
      `<option value="${opts.prependOption.value}">${esc(opts.prependOption.label)}</option>`);
  }
  items.forEach(it => {
    const sel_ = opts.selectedVal != null && String(it.id) === String(opts.selectedVal) ? ' selected' : '';
    sel.insertAdjacentHTML('beforeend', `<option value="${it.id}"${sel_}>${esc(it[labelKey])}</option>`);
  });
}
