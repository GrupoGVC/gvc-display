/* ============================================================
   GVC Signage — Admin SPA  (ES Modules)
   ============================================================ */

import { get, post, put, del, upload, token, BASE, API } from "./api.js";
import {
  q,
  qa,
  esc,
  openModal,
  closeModal,
  toast,
  copyText,
  fmtDate,
  fmtSize,
  populateSelect,
} from "./utils.js";

// ── Confirmação customizada (substitui alert/confirm nativos) ─
function confirmDlg(msg) {
  return new Promise((resolve) => {
    const id  = 'dlg-' + Date.now();
    const el  = document.createElement('div');
    el.id     = id;
    el.style.cssText = `
      position:fixed;inset:0;z-index:10000;
      display:flex;align-items:center;justify-content:center;
      background:rgba(0,0,0,.6);backdrop-filter:blur(3px);
      animation:fadeIn .15s ease;
    `;
    el.innerHTML = `
      <div style="
        background:var(--bg2);border:1px solid var(--bord);
        border-radius:14px;padding:24px 28px;max-width:340px;width:90%;
        box-shadow:0 20px 60px rgba(0,0,0,.5);animation:slideUp .15s ease;
      ">
        <p style="font-size:14px;color:var(--txt);margin:0 0 20px;line-height:1.5;">${msg}</p>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <button id="${id}-no" class="btn-g" style="min-width:80px;">Cancelar</button>
          <button id="${id}-yes" class="btn-d" style="min-width:80px;">Confirmar</button>
        </div>
      </div>
    `;
    document.body.appendChild(el);
    const cleanup = (v) => { el.remove(); resolve(v); };
    document.getElementById(id + '-yes').onclick = () => cleanup(true);
    document.getElementById(id + '-no').onclick  = () => cleanup(false);
    el.onclick = (e) => { if (e.target === el) cleanup(false); };
  });
}



// ── Helper: resolve URL de mídia (relativa ou absoluta) ─────────
function mediaUrl(url) {
  if (!url) return '';
  // Usa o BASE exportado pelo api.js — já calculado corretamente
  // para qualquer subpasta (/gvc-display, /gvc-display-mvc, etc.)
  if (url.startsWith('/uploads/')) {
    const resolved = BASE + url;
    return resolved;
  }
  if (url.startsWith('http://') || url.startsWith('https://')) {
    try {
      const u = new URL(url);
      const i = u.pathname.indexOf('/uploads/');
      if (i >= 0) return BASE + u.pathname.slice(i);
      return url;
    } catch { return url; }
  }
  if (url.startsWith('/')) return window.location.origin + url;
  return BASE + '/' + url;
}

// ── Estado global ─────────────────────────────────────────────
const S = {
  user: null,
  devices: [],
  groups: [],
  playlists: [],
  media: [],
  curPlId: null,
  curPlItems: [],
  editDevId: null,
  editGrId: null,
};

// ── Bootstrap ─────────────────────────────────────────────────
(async () => {
  const saved = token.get();
  if (saved) {
    try {
      const d = await get("dashboard");
      S.user = { name: "Admin" };
      initApp(d);
      return;
    } catch (e) {
      if (e.status === 401) token.clear();
    }
  }
  showLogin();
})();

// ── Login ─────────────────────────────────────────────────────
function showLogin() {
  token.clear();
  location.href = 'login';
}

window.doLogin = async () => { showLogin(); };

window.doLogout = () => {
  token.clear();
  location.reload();
};

// ── App init ──────────────────────────────────────────────────
async function initApp(dashData) {
  q("#app").style.display = "flex";
  q("#conn-user").textContent = S.user?.name ?? "Admin";

  renderDash(dashData);
  await Promise.all([loadGroups(), loadPlaylists(), loadMedia()]);

  startAutoRefresh();
}

// ── Auto-refresh inteligente ──────────────────────────────────
// Atualiza a seção visível sem reload. Pausa quando a aba do navegador
// está em segundo plano (economiza requisições) e retoma ao voltar.
let _refreshTimer = null;

function activeSection() {
  const active = document.querySelector(".sec.active");
  return active ? active.id.replace("sec-", "") : "dashboard";
}

async function refreshActive() {
  if (document.hidden) return;              // aba em background → pula
  try {
    const sec = activeSection();
    switch (sec) {
      case "dashboard":
        renderDash(await get("dashboard"));
        break;
      case "dispositivos":
        S.devices = (await get("devices")) || [];
        renderDevices();
        break;
      case "grupos":
        await loadGroups();
        break;
      case "playlists":
        // Só atualiza a LISTA (não o editor aberto)
        if (!q("#pl-edit") || q("#pl-edit").classList.contains("hidden")) {
          await loadPlaylists();
        }
        break;
      case "midia":
        S.media = (await get("media")) || [];
        renderMedia();
        break;
    }
    // Dashboard sempre atualizado em background para stats
    if (sec !== "dashboard") {
      try { renderDash(await get("dashboard")); } catch {}
    }
  } catch {}
}

function startAutoRefresh() {
  if (_refreshTimer) clearInterval(_refreshTimer);
  _refreshTimer = setInterval(refreshActive, 10_000);   // a cada 10s
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) refreshActive();
  });
}

// ── Navigation ────────────────────────────────────────────────
window.nav = (section) => {
  qa(".ni").forEach((n) =>
    n.classList.toggle("active", n.dataset.s === section),
  );
  qa(".sec").forEach((s) => s.classList.remove("active"));
  q(`#sec-${section}`)?.classList.add("active");

  const titles = {
    dashboard: "Início",
    dispositivos: "Dispositivos",
    grupos: "Grupos",
    playlists: "Playlists",
    midia: "Biblioteca de Mídias",
    pareamento: "Pareamento",
    config: "Configurações",
  };
  q("#ptitle").textContent = titles[section] ?? section;
  q("#pact").innerHTML = "";

  if (section === "dispositivos") loadDevices();
  if (section === "pareamento") loadPairing();
};

// ── Dashboard ─────────────────────────────────────────────────
function renderDash(d) {
  q("#st-tvs").textContent = d.stats.total_devices;
  q("#st-on").textContent = d.stats.online_devices;
  q("#st-pl").textContent = d.stats.total_playlists;
  q("#st-md").textContent = d.stats.total_media;
  q("#cfg-tv-count").textContent = d.stats.total_devices;
  q("#cfg-media-count").textContent = d.stats.total_media;
  q("#cfg-pl-count").textContent = d.stats.total_playlists;

  q("#dash-tv").innerHTML = d.devices.length
    ? d.devices.map((tv) => `
      <div class="tv-row">
        <div class="tv-icon"><i class="bi bi-display"></i></div>
        <div class="tv-info">
          <div class="tv-name">${esc(tv.name)}</div>
          <div class="tv-loc">${esc(tv.location || "—")}${tv.playlist_name ? " · " + esc(tv.playlist_name) : ""}</div>
        </div>
        <span class="tag ${tv.status === "online" ? "tg-on" : "tg-off"}">
          <span class="sdot ${tv.status === "online" ? "sdot-on" : "sdot-off"}"></span>
          ${tv.status}
        </span>
      </div>`).join("")
    : '<p class="empty">Nenhuma TV cadastrada</p>';

  const logIcons = {
    login:           '<i class="bi bi-key log-i" style="color:#00AA8E"></i>',
    logout:          '<i class="bi bi-box-arrow-right log-i" style="color:#64748b"></i>',
    create_device:   '<i class="bi bi-display log-i" style="color:#00AA8E"></i>',
    update_device:   '<i class="bi bi-pencil log-i" style="color:#00AA8E"></i>',
    delete_device:   '<i class="bi bi-trash3 log-i" style="color:#ff4f6a"></i>',
    pair_device:     '<i class="bi bi-link-45deg log-i" style="color:#22c55e"></i>',
    unpair_device:   '<i class="bi bi-link-45deg log-i" style="color:#f97316"></i>',
    broadcast:       '<i class="bi bi-broadcast log-i" style="color:#eab308"></i>',
    upload_media:    '<i class="bi bi-cloud-upload log-i" style="color:#22c55e"></i>',
    delete_media:    '<i class="bi bi-trash3 log-i" style="color:#ff4f6a"></i>',
    create_playlist: '<i class="bi bi-play-circle log-i" style="color:#eab308"></i>',
    delete_playlist: '<i class="bi bi-trash3 log-i" style="color:#ff4f6a"></i>',
    create_group:    '<i class="bi bi-collection log-i" style="color:#f97316"></i>',
    delete_group:    '<i class="bi bi-trash3 log-i" style="color:#ff4f6a"></i>',
    create_schedule: '<i class="bi bi-calendar3 log-i" style="color:#00AA8E"></i>',
    delete_schedule: '<i class="bi bi-trash3 log-i" style="color:#ff4f6a"></i>',
    login_failed:    '<i class="bi bi-exclamation-triangle log-i" style="color:#f59e0b"></i>',
  };
  const defaultLogIcon = '<i class="bi bi-activity log-i" style="color:#64748b"></i>';

  window._allLogs = d.logs;

  const renderLogs = (list) => list.map((l) => `
    <div class="log-item">
      <div class="log-ic">${logIcons[l.action] || defaultLogIcon}</div>
      <div class="log-body">
        <div class="log-act">${esc(l.action.replace(/_/g, " "))}${l.detail ? ` <em style="color:var(--mut);font-size:11px">${esc(l.detail)}</em>` : ""}</div>
        <div class="log-time">${l.user_name ? esc(l.user_name) + " · " : ""}${fmtDate(l.created_at)}</div>
      </div>
    </div>`).join("");

  q("#dash-log").innerHTML = d.logs.length
    ? renderLogs(d.logs.slice(0, 5))
    : '<p class="empty">Sem atividade recente</p>';
}

// ── Log Modal ─────────────────────────────────────────────────
window.openLogModal = () => {
  const logs = window._allLogs || [];
  const logIcons = {
    login:           '<i class="bi bi-key log-i" style="color:#00AA8E"></i>',
    logout:          '<i class="bi bi-box-arrow-right log-i" style="color:#64748b"></i>',
    create_device:   '<i class="bi bi-display log-i" style="color:#00AA8E"></i>',
    update_device:   '<i class="bi bi-pencil log-i" style="color:#00AA8E"></i>',
    delete_device:   '<i class="bi bi-trash3 log-i" style="color:#ff4f6a"></i>',
    pair_device:     '<i class="bi bi-link-45deg log-i" style="color:#22c55e"></i>',
    broadcast:       '<i class="bi bi-broadcast log-i" style="color:#eab308"></i>',
    upload_media:    '<i class="bi bi-cloud-upload log-i" style="color:#22c55e"></i>',
    delete_media:    '<i class="bi bi-trash3 log-i" style="color:#ff4f6a"></i>',
    create_playlist: '<i class="bi bi-play-circle log-i" style="color:#eab308"></i>',
    delete_playlist: '<i class="bi bi-trash3 log-i" style="color:#ff4f6a"></i>',
    create_group:    '<i class="bi bi-collection log-i" style="color:#f97316"></i>',
    delete_group:    '<i class="bi bi-trash3 log-i" style="color:#ff4f6a"></i>',
    create_schedule: '<i class="bi bi-calendar3 log-i" style="color:#00AA8E"></i>',
    delete_schedule: '<i class="bi bi-trash3 log-i" style="color:#ff4f6a"></i>',
    login_failed:    '<i class="bi bi-exclamation-triangle log-i" style="color:#f59e0b"></i>',
  };
  const defaultLogIcon = '<i class="bi bi-activity log-i" style="color:#64748b"></i>';
  const el = document.getElementById('log-modal-body');
  if (!el) return;
  el.innerHTML = logs.length
    ? logs.map(l => `
      <div class="log-item">
        <div class="log-ic">${logIcons[l.action] || defaultLogIcon}</div>
        <div class="log-body">
          <div class="log-act">${esc(l.action.replace(/_/g,' '))}${l.detail ? ` <em style="color:var(--mut);font-size:11px">${esc(l.detail)}</em>` : ''}</div>
          <div class="log-time">${l.user_name ? esc(l.user_name) + ' · ' : ''}${fmtDate(l.created_at)}</div>
        </div>
      </div>`).join('')
    : '<p class="empty">Sem atividade recente</p>';
  openModal('m-log');
};

// ═══════════════════════════════════════════════════════════════
// DEVICES
// ═══════════════════════════════════════════════════════════════

async function loadDevices() {
  const _devs = await get("devices");
  S.devices = Array.isArray(_devs) ? _devs : [];
  renderDevices();
  buildGroupFilter();
}

function renderDevices() {
  const search = q("#dev-search")?.value.toLowerCase() ?? "";
  const gfil = q("#dev-filter-grupo")?.value ?? "";
  const rows = S.devices.filter(
    (d) =>
      (!search ||
        d.name.toLowerCase().includes(search) ||
        (d.location ?? "").toLowerCase().includes(search)) &&
      (!gfil || String(d.group_id) === gfil),
  );
  q("#dev-tbody").innerHTML = rows.length
    ? rows.map((d) => `
      <tr>
        <td><strong>${esc(d.name)}</strong><div style="font-size:12px;color:var(--mut);">${esc(d.location || "—")}</div></td>
        <td data-col="group">${d.group_name ? `<span class="tag tg">${esc(d.group_name)}</span>` : '<span style="color:var(--mut);">—</span>'}</td>
        <td data-col="status"><span class="sdot ${d.status === "online" ? "sdot-on" : "sdot-off"}"></span> <span class="tag ${d.status === "online" ? "tg-on" : "tg-off"}">${d.status}</span></td>
        <td data-col="playlist">${d.playlist_name ? esc(d.playlist_name) : '<span style="color:var(--mut);">—</span>'}</td>
        <td data-col="ping" style="font-size:12px;color:var(--mut);">${d.last_ping ? fmtDate(d.last_ping) : "nunca"}</td>
        <td class="col-actions">
          <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            ${d.paired
              ? `<button class="btn-sm" disabled title="TV já pareada"
                   style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.3);cursor:default;">
                   <i class="bi bi-check-circle-fill"></i><span class="btn-label"> Pareado</span></button>`
              : `<button class="btn-p btn-sm" onclick="openPairForDevice(${d.id},'${esc(d.name)}')" title="Parear TV">
                   <i class="bi bi-qr-code-scan"></i><span class="btn-label"> Parear</span></button>`
            }
            <button class="btn-g btn-sm" onclick="openEditDev(${d.id})" title="Editar"><i class="bi bi-pencil"></i></button>
            <button class="btn-g btn-sm" onclick="openDevInfo(${d.id})" title="Info"><i class="bi bi-gear"></i></button>
            <button class="btn-d btn-sm" onclick="delDev(${d.id})" title="Excluir"><i class="bi bi-trash3"></i></button>
          </div>
        </td>
      </tr>`).join("")
    : `<tr><td colspan="6" class="empty">Nenhum dispositivo encontrado</td></tr>`;
}

function buildGroupFilter() {
  const sel = q("#dev-filter-grupo");
  if (!sel) return;
  sel.innerHTML = '<option value="">Todos os grupos</option>';
  S.groups.forEach((g) =>
    sel.insertAdjacentHTML("beforeend", `<option value="${g.id}">${esc(g.name)}</option>`),
  );
}

window.filterDevs = () => renderDevices();

window.openAddDev = () => {
  S.editDevId = null;
  q("#m-dev-title").innerHTML = '<i class="bi bi-display"></i>Adicionar TV';
  q("#btn-save-dev").textContent = "Criar";
  q("#d-nome").value = "";
  q("#d-unit").value = "";
  populateSelect("d-grupo", S.groups, "name", { emptyLabel: "sem grupo" });
  populateSelect("d-playlist", S.playlists, "name", { emptyLabel: "nenhuma" });
  openModal("m-dev");
};

window.openEditDev = (id) => {
  const d = S.devices.find((x) => x.id === id);
  if (!d) return;
  S.editDevId = id;
  q("#m-dev-title").innerHTML = '<i class="bi bi-pencil"></i>Editar TV';
  q("#btn-save-dev").textContent = "Salvar";
  q("#d-nome").value = d.name;
  q("#d-unit").value = d.location || "";
  populateSelect("d-grupo", S.groups, "name", { emptyLabel: "sem grupo", selectedVal: d.group_id });
  populateSelect("d-playlist", S.playlists, "name", { emptyLabel: "nenhuma", selectedVal: d.playlist_id });
  openModal("m-dev");
};

// FIX: doSaveDev
// ANTES: `loadDevices()` sem await — a lista re-renderizava antes do modal fechar,
//         e a resposta do PUT retornava apenas {id}, sem group_name/playlist_name,
//         então a tabela exibia "—" nos campos de grupo e playlist até o próximo refresh.
// DEPOIS: para edição, aplica patch direto no S.devices[] com os dados completos
//         que o PHP agora retorna; para criação, insere o novo objeto retornado.
//         Ambos re-renderizam a tabela imediatamente sem nova requisição GET.
window.doSaveDev = async () => {
  const name = q("#d-nome").value.trim();
  if (!name) return toast("Nome é obrigatório", "err");

  const body = {
    name,
    location: q("#d-unit").value.trim(),
    group_id: q("#d-grupo").value || null,
    playlist_id: q("#d-playlist").value || null,
  };

  const btn = q("#btn-save-dev");
  btn.disabled = true;

  try {
    const saved = S.editDevId
      ? await put(`devices/${S.editDevId}`, body)
      : await post("devices", body);

    // Atualiza S.devices[] in-place com o objeto completo retornado pelo PHP
    // (incluindo group_name, playlist_name, player_url) — zero requisição extra
    if (S.editDevId) {
      S.devices = S.devices.map((d) => d.id === S.editDevId ? { ...d, ...saved } : d);
    } else {
      S.devices = [saved, ...S.devices];
    }

    renderDevices();
    closeModal("m-dev");
    toast(S.editDevId ? "TV atualizada" : "TV criada");
  } catch (e) {
    toast(e.message, "err");
  } finally {
    btn.disabled = false;
  }
};

window.openDevInfo = (id) => {
  const d = S.devices.find((x) => x.id === id);
  if (!d) return;
  q("#m-devinfo-title").innerHTML = `<i class="bi bi-gear"></i>${esc(d.name)}`;
  q("#dev-purl").textContent = d.player_url;
  q("#dev-token").textContent = d.token;
  openModal("m-devinfo");
};

// FIX: delDev
// ANTES: sem try/catch — erro na API causava exception silenciosa no console,
//         a linha da tabela não era removida e nenhum feedback era dado ao usuário.
// DEPOIS: remove o item de S.devices[] e re-renderiza imediatamente após sucesso,
//         sem nova requisição GET; erro exibe toast.
window.delDev = async (id) => {
  if (!await confirmDlg("Remover este dispositivo?")) return;
  try {
    await del(`devices/${id}`);
    S.devices = S.devices.filter((d) => d.id !== id);
    renderDevices();
    toast("Dispositivo removido");
  } catch (e) {
    toast(e.message, "err");
  }
};

// ═══════════════════════════════════════════════════════════════
// GROUPS
// ═══════════════════════════════════════════════════════════════

async function loadGroups() {
  const _grps = await get("groups");
  S.groups = Array.isArray(_grps) ? _grps : [];
  renderGroups();
}

function renderGroups() {
  const el = q("#gr-list");
  if (!el) return;
  el.innerHTML = S.groups.length
    ? `<table class="gvc-table"><thead><tr><th>Nome</th><th>Descrição</th><th>TVs</th><th>Ações</th></tr></thead><tbody>
       ${S.groups.map((g) => `
        <tr>
          <td><strong>${esc(g.name)}</strong></td>
          <td data-col="group-desc" style="color:var(--mut);">${esc(g.description || "—")}</td>
          <td data-col="group-count"><span class="tag tg">${g.device_count}</span></td>
          <td class="col-actions"><div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button class="btn-g btn-sm" onclick="openEditGrupo(${g.id})"><i class="bi bi-pencil"></i></button>
            <button class="btn-d btn-sm" onclick="delGrupo(${g.id})"><i class="bi bi-trash3"></i></button>
          </div></td>
        </tr>`).join("")}
       </tbody></table>`
    : '<p class="empty">Nenhum grupo criado</p>';
}

window.openAddGrupo = () => {
  S.editGrId = null;
  q("#m-grupo-title").innerHTML = '<i class="bi bi-folder-plus"></i>Novo Grupo';
  q("#btn-save-grupo").textContent = "Criar";
  q("#gr-nome").value = "";
  q("#gr-desc").value = "";
  openModal("m-grupo");
};

window.openEditGrupo = (id) => {
  const g = S.groups.find((x) => x.id === id);
  if (!g) return;
  S.editGrId = id;
  q("#m-grupo-title").innerHTML = '<i class="bi bi-pencil"></i>Editar Grupo';
  q("#btn-save-grupo").textContent = "Salvar";
  q("#gr-nome").value = g.name;
  q("#gr-desc").value = g.description || "";
  openModal("m-grupo");
};

// FIX: doSaveGrupo
// ANTES: PUT retornava apenas {id} — o S.groups[] ficava com os dados antigos
//         (nome, descrição) até o próximo loadGroups(), que era chamado com await
//         mas sem atualizar os selects de filtro e de dispositivos.
// DEPOIS: aplica patch direto no S.groups[] com os dados completos retornados,
//         re-renderiza grupos e atualiza o filtro de grupos na aba de dispositivos.
window.doSaveGrupo = async () => {
  const name = q("#gr-nome").value.trim();
  if (!name) return toast("Nome é obrigatório", "err");
  const body = { name, description: q("#gr-desc").value.trim() };

  const btn = q("#btn-save-grupo");
  btn.disabled = true;

  try {
    const saved = S.editGrId
      ? await put(`groups/${S.editGrId}`, body)
      : await post("groups", body);

    if (S.editGrId) {
      // Atualiza o item no array in-place preservando device_count
      S.groups = S.groups.map((g) => g.id === S.editGrId ? { ...g, ...saved } : g);
    } else {
      S.groups = [...S.groups, saved];
    }

    renderGroups();
    buildGroupFilter();          // atualiza o <select> de filtro em Dispositivos
    closeModal("m-grupo");
    toast(S.editGrId ? "Grupo atualizado" : "Grupo criado");
  } catch (e) {
    toast(e.message, "err");
  } finally {
    btn.disabled = false;
  }
};

// FIX: delGrupo
// ANTES: sem try/catch — erro silencioso, linha não era removida da tabela.
// DEPOIS: remove do array e re-renderiza imediatamente; erro exibe toast.
window.delGrupo = async (id) => {
  if (!await confirmDlg("Remover este grupo?")) return;
  try {
    await del(`groups/${id}`);
    S.groups = S.groups.filter((g) => g.id !== id);
    renderGroups();
    buildGroupFilter();
    toast("Grupo removido");
  } catch (e) {
    toast(e.message, "err");
  }
};

// ═══════════════════════════════════════════════════════════════
// PLAYLISTS
// ═══════════════════════════════════════════════════════════════

async function loadPlaylists() {
  const _playlists = await get("playlists");
  S.playlists = Array.isArray(_playlists) ? _playlists : [];
  renderPlaylists();
}

function renderPlaylists() {
  const el = q("#pl-tbody");
  if (!el) return;
  el.innerHTML = S.playlists.length
    ? `<table class="gvc-table"><thead><tr><th>Nome</th><th>Itens</th><th>Tipo</th><th>Ações</th></tr></thead><tbody>
       ${S.playlists.map((p) => `
        <tr>
          <td><strong>${esc(p.name)}</strong></td>
          <td data-col="pl-count"><span class="tag tg">${p.item_count} itens</span></td>
          <td data-col="pl-default">${p.is_default ? '<span class="tag tg-def">⭐ Padrão</span>' : ""}</td>
          <td class="col-actions"><div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button class="btn-p btn-sm" onclick="editPlaylist(${p.id})"><i class="bi bi-pencil"></i><span class="btn-label"> Editar</span></button>
            <button class="btn-d btn-sm" onclick="delPl(${p.id})"><i class="bi bi-trash3"></i></button>
          </div></td>
        </tr>`).join("")}
       </tbody></table>`
    : '<p class="empty">Nenhuma playlist criada</p>';
}

window.showPlList = () => {
  q("#pl-list").classList.remove("hidden");
  q("#pl-edit").classList.add("hidden");
  loadPlaylists();
};

window.editPlaylist = async (id) => {
  S.curPlId = id;

  if (!S.media || S.media.length === 0) {
    try {
      const m = await get("media");
      S.media = Array.isArray(m) ? m : [];
    } catch { S.media = []; }
  }

  const pl = await get(`playlists/${id}`);
  S.curPlItems = Array.isArray(pl.items) ? pl.items : [];
  S.curPl = pl;
  q("#pl-edit-name").textContent = pl.name;
  q("#pl-edit-default-badge").classList.toggle("hidden", !pl.is_default);
  // Popula card de configurações
  const renameInput = q("#pl-edit-rename");
  if (renameInput) renameInput.value = pl.name;
  const defCheck = q("#pl-edit-is-default");
  if (defCheck) defCheck.checked = !!pl.is_default;

  q("#pl-list").classList.add("hidden");
  q("#pl-edit").classList.remove("hidden");
  renderPlItems();
};

function renderPlItems() {
  const el = q("#pl-items");
  if (!S.curPlItems.length) {
    el.innerHTML = '<p class="empty">Nenhum item. Clique em + Adicionar</p>';
    q("#pl-hint").style.display = "none";
    return;
  }
  q("#pl-hint").style.display = "block";
  el.innerHTML = S.curPlItems.map((item, i) => {
    const _rawSrc = item.media_url || item.url || "";
    const src = mediaUrl(_rawSrc);
    // DEBUG: remova após confirmar
    if (i === 0) console.log('[GVC Debug] item:', JSON.stringify(item), '→ src:', src);
    const isVideo = item.type === "video" || /\.(mp4|webm|ogg)$/i.test(src);
    const thumb =
      isVideo
        ? `<video src="${src}" muted preload="metadata" playsinline
             style="width:100%;height:100%;object-fit:cover;display:block;"
             onloadeddata="this.currentTime=1"></video>`
        : item.type === "page"
          ? `<i class="bi bi-globe" style="font-size:24px;color:var(--primary)"></i>`
          : `<img src="${src}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block;" />`;
    return `
      <div class="pl-item" draggable="true" data-i="${i}"
           ondragstart="onDragStart(event,${i})" ondragover="onDragOver(event)" ondrop="onDrop(event,${i})">
        <span class="pl-drag">⠿</span>
        <div class="pl-thumb">${thumb}</div>
        <div class="pl-info" onclick="previewItem(${i})" style="cursor:pointer;">
          <div class="pl-iname">${esc(src || "(sem URL)")}</div>
          <div class="pl-imeta">${item.type} · ${item.duration}s</div>
        </div>
        <div class="pl-dur-edit" title="Duração em segundos">
          <input type="number" min="1" max="3600" value="${item.duration}"
                 id="dur-in-${item.id}" draggable="false"
                 onmousedown="event.stopPropagation()"
                 onchange="saveItemDur(${item.id}, this.value)"
                 onkeydown="if(event.key==='Enter')this.blur()"/>
          <span>s</span>
        </div>
        <div><button class="btn-d btn-sm" onclick="delItem(${item.id})" title="Remover"><i class="bi bi-trash3"></i></button></div>
      </div>`;
  }).join("");
}

window.saveItemDur = async (id, val) => {
  let dur = parseInt(val, 10);
  if (isNaN(dur) || dur < 1) dur = 1;
  if (dur > 3600) dur = 3600;

  const inp = document.getElementById(`dur-in-${id}`);
  if (inp) inp.value = dur;

  try {
    await put(`items/${id}`, { duration: dur });
    // Atualiza cache local + meta
    S.curPlItems = S.curPlItems.map(it => it.id === id ? { ...it, duration: dur } : it);
    const item = S.curPlItems.find(it => it.id === id);
    if (item) {
      const row = inp?.closest('.pl-item');
      const meta = row?.querySelector('.pl-imeta');
      if (meta) meta.textContent = `${item.type} · ${dur}s`;
    }
    toast(`Duração: ${dur}s`);
  } catch (e) {
    toast(e.message || 'Erro ao salvar duração', 'err');
  }
};

let _dragSrc = null;
window.onDragStart = (e, i) => { _dragSrc = i; e.dataTransfer.effectAllowed = "move"; };
window.onDragOver  = (e) => { e.preventDefault(); };
window.onDrop = async (e, i) => {
  e.preventDefault();
  if (_dragSrc === null || _dragSrc === i) return;
  const items = [...S.curPlItems];
  const [m] = items.splice(_dragSrc, 1);
  items.splice(i, 0, m);
  S.curPlItems = items;
  renderPlItems();
  await post("items/reorder", {
    items: items.map((it, idx) => ({ id: it.id, sort_order: idx })),
  });
};

window.previewItem = (i) => {
  const item = S.curPlItems[i];
  if (!item) return;
  const rawUrl = item.media_url || item.url || "";
  const src    = mediaUrl(rawUrl);
  const pv     = q("#preview");

  // Detecta tipo pela extensão (mais confiável que o campo salvo)
  const isVideo = item.type === "video" || /\.(mp4|webm|ogg|mov)$/i.test(src);
  const isPage  = item.type === "page";

  if (isVideo) {
    pv.innerHTML = `<video controls autoplay muted playsinline preload="metadata"
      style="width:100%;height:100%;object-fit:contain;background:#000;">
      <source src="${src}" type="video/mp4">
      <source src="${src}" type="video/webm">
    </video>`;
  } else if (isPage) {
    pv.innerHTML = `<iframe src="${src}" style="width:100%;height:100%;border:none;"
      sandbox="allow-scripts allow-same-origin"></iframe>`;
  } else {
    pv.innerHTML = `<img src="${src}" alt=""
      style="width:100%;height:100%;object-fit:contain;display:block;"
      onerror="this.style.opacity='.3'" />`;
  }
  q("#prev-hint").textContent = `${isVideo ? 'video' : item.type} · ${item.duration}s`;
};

window.openAddPl = () => {
  q("#pl-nome").value = "";
  q("#pl-is-default").checked = false;
  openModal("m-pl");
};

// FIX: doAddPl
// ANTES: após criar, chamava editPlaylist(pl.id) sem garantir que S.playlists
//         já estava atualizado — o item_count podia ficar errado na listagem.
// DEPOIS: insere o novo objeto retornado pelo PHP diretamente em S.playlists[],
//         re-renderiza a lista e só então abre o editor da playlist criada.
window.doAddPl = async () => {
  const name = q("#pl-nome").value.trim();
  if (!name) return toast("Nome é obrigatório", "err");

  const btn = q("#btn-add-pl");
  if (btn) btn.disabled = true;

  try {
    const pl = await post("playlists", {
      name,
      is_default: q("#pl-is-default").checked,
    });
    // Se is_default foi marcado, zera o badge nas outras
    if (pl.is_default) {
      S.playlists = S.playlists.map((p) => ({ ...p, is_default: false }));
    }
    S.playlists = [pl, ...S.playlists];
    renderPlaylists();
    closeModal("m-pl");
    toast("Playlist criada");
    editPlaylist(pl.id);
  } catch (e) {
    toast(e.message, "err");
  } finally {
    if (btn) btn.disabled = false;
  }
};

window.openDupPl = () => {
  populateSelect("dup-src", S.playlists, "name");
  q("#dup-name").value = "";
  openModal("m-dup");
};

// FIX: doDupPl
// ANTES: sem try/catch — erro silencioso; sem await no loadPlaylists.
// DEPOIS: adiciona try/catch; recarrega a lista com await para garantir
//         que a tabela já mostra a duplicata quando o modal fecha.
window.doDupPl = async () => {
  const src = q("#dup-src").value;
  const name = q("#dup-name").value.trim();
  if (!src || !name) return toast("Preencha todos os campos", "err");

  try {
    const pl = await post("playlists", { name, copy_from: src });
    S.playlists = [pl, ...S.playlists];
    renderPlaylists();
    closeModal("m-dup");
    toast("Playlist duplicada");
  } catch (e) {
    toast(e.message, "err");
  }
};

// FIX: deleteCurPl
// ANTES: sem try/catch.
// DEPOIS: remove de S.playlists[] e volta para a lista sem nova requisição.
window.deleteCurPl = async () => {
  if (!S.curPlId || !confirm("Excluir esta playlist?")) return;
  try {
    await del(`playlists/${S.curPlId}`);
    S.playlists = S.playlists.filter((p) => p.id !== S.curPlId);
    S.curPlId = null;
    S.curPlItems = [];
    renderPlaylists();
    q("#pl-list").classList.remove("hidden");
    q("#pl-edit").classList.add("hidden");
    toast("Playlist excluída");
  } catch (e) {
    toast(e.message, "err");
  }
};

// FIX: delPl
// ANTES: sem try/catch — erro silencioso, linha não era removida.
// DEPOIS: remove do array e re-renderiza; erro exibe toast.
window.delPl = async (id) => {
  if (!await confirmDlg("Excluir esta playlist?")) return;
  try {
    await del(`playlists/${id}`);
    S.playlists = S.playlists.filter((p) => p.id !== id);
    renderPlaylists();
    toast("Playlist excluída");
  } catch (e) {
    toast(e.message, "err");
  }
};

// ═══════════════════════════════════════════════════════════════
// ITEMS
// ═══════════════════════════════════════════════════════════════

window.openAddItem = async () => {
  q("#it-tipo").value = "image";
  q("#it-url").value = "";
  q("#it-dur").value = "10";
  togDur();

  try {
    const m = await get("media");
    S.media = Array.isArray(m) ? m : [];
  } catch {}

  renderMpicker();
  openModal("m-item");
};

window.togDur = () => {
  q("#dur-grp").style.display = q("#it-tipo").value === "video" ? "none" : "flex";
  renderMpicker();
};

function renderMpicker() {
  const type = q("#it-tipo").value;
  // Mostra toda a mídia disponível — o tipo do item é definido pelo select acima
  // Vídeos e imagens ficam todos visíveis para o usuário escolher
  let list = type === "page" ? [] : S.media;

  q("#mpicker").innerHTML = list.length
    ? list.map((m) => `
      <div class="mp-item" title="${esc(m.original)}" onclick="pickMedia('${m.url}',this)"
           style="position:relative;">
        ${m.type === "video"
          ? `<video src="${mediaUrl(m.url)}" muted preload="metadata" playsinline
               style="width:100%;height:100%;object-fit:cover;display:block;"
               onloadeddata="this.currentTime=1"></video>
             <div style="position:absolute;inset:0;display:flex;align-items:center;
               justify-content:center;pointer-events:none;">
               <i class="bi bi-play-circle-fill" style="font-size:22px;color:#fff;
                 text-shadow:0 1px 4px rgba(0,0,0,.8);"></i>
             </div>`
          : `<img src="${mediaUrl(m.url)}" alt="" loading="lazy"
               style="width:100%;height:100%;object-fit:cover;display:block;" />`
        }
      </div>`).join("")
    : '<p style="font-size:12px;color:var(--mut);grid-column:1/-1;padding:8px;">Nenhuma mídia na biblioteca. Faça upload em <strong>Mídia</strong> primeiro.</p>';
}

window.pickMedia = (url, el) => {
  qa("#mpicker .mp-item").forEach((c) => c.classList.remove("selected"));
  el.classList.add("selected");
  q("#it-url").value = url;

  // Detecta o tipo pelo arquivo selecionado e atualiza o select
  const isVideo = /\.(mp4|webm|ogg|mov|avi)$/i.test(url);
  const tipoSel = q("#it-tipo");
  if (tipoSel) {
    tipoSel.value = isVideo ? "video" : "image";
    togDur(); // atualiza campo de duração
  }
};

// FIX: doAddItem
// ANTES: sem try/catch — erro na API causava exception não tratada;
//         chamava GET na playlist após POST sem garantir que o item foi inserido.
// DEPOIS: try/catch com toast de erro; insere o item retornado diretamente em
//         S.curPlItems[] e re-renderiza sem nova requisição GET.
window.doAddItem = async () => {
  const url = q("#it-url").value.trim();
  const type = q("#it-tipo").value;
  if (!url || !S.curPlId) return toast("Informe a URL", "err");

  const mediaObj = S.media.find((m) => m.url === url);
  const mediaId  = mediaObj?.id || null;

  const btn = q("#btn-add-item");
  if (btn) btn.disabled = true;

  try {
    const saved = await post("items", {
      playlist_id: S.curPlId,
      type,
      url,
      duration: parseInt(q("#it-dur").value) || 10,
      media_id: mediaId,
    });

    // Monta o objeto completo para o array local sem precisar de GET extra
    S.curPlItems = [...S.curPlItems, {
      id:        saved.id,
      type,
      url,
      duration:  parseInt(q("#it-dur").value) || 10,
      media_id:  mediaId,
      media_url: mediaObj?.url || url,
      sort_order: saved.sort_order,
    }];

    // Incrementa o item_count na lista de playlists
    S.playlists = S.playlists.map((p) =>
      p.id === S.curPlId ? { ...p, item_count: (p.item_count || 0) + 1 } : p
    );

    renderPlItems();
    closeModal("m-item");
    toast("Item adicionado");
  } catch (e) {
    toast(e.message, "err");
  } finally {
    if (btn) btn.disabled = false;
  }
};

// FIX: delItem
// ANTES: sem try/catch; fazia GET extra na playlist após deletar.
// DEPOIS: remove o item de S.curPlItems[] diretamente e re-renderiza; erro exibe toast.
window.delItem = async (id) => {
  if (!await confirmDlg("Remover este item?")) return;
  try {
    await del(`items/${id}`);
    S.curPlItems = S.curPlItems.filter((it) => it.id !== id);

    // Decrementa o item_count na lista de playlists
    S.playlists = S.playlists.map((p) =>
      p.id === S.curPlId ? { ...p, item_count: Math.max(0, (p.item_count || 1) - 1) } : p
    );

    renderPlItems();
    toast("Item removido");
  } catch (e) {
    toast(e.message, "err");
  }
};

// ═══════════════════════════════════════════════════════════════
// PLAYLIST SETTINGS (renomear, padrão)
// ═══════════════════════════════════════════════════════════════

window.savePlSettings = async () => {
  const name      = (q("#pl-edit-rename")?.value || "").trim();
  const isDef     = q("#pl-edit-is-default")?.checked || false;
  const btn       = q("#btn-save-pl");

  if (!name) return toast("Nome não pode ficar vazio", "err");
  if (!S.curPlId) return;

  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...'; }

  try {
    const saved = await put(`playlists/${S.curPlId}`, { name, is_default: isDef });
    // Atualiza header do editor
    q("#pl-edit-name").textContent = name;
    q("#pl-edit-default-badge").classList.toggle("hidden", !isDef);
    // Atualiza cache local
    S.playlists = S.playlists.map(p =>
      p.id === S.curPlId ? { ...p, name, is_default: isDef ? 1 : 0 } : (isDef ? { ...p, is_default: 0 } : p)
    );
    toast("✅ Playlist salva!");
  } catch (e) {
    toast(e.message || "Erro ao salvar", "err");
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg"></i>Salvar'; }
  }
};

window.openBroadcast = () => {
  populateSelect("bc-pl", S.playlists, "name", { emptyLabel: "selecione" });
  const sel = q("#bc-target");
  sel.innerHTML = '<option value="all">Todas as TVs</option>';
  S.groups.forEach((g) =>
    sel.insertAdjacentHTML("beforeend", `<option value="group:${g.id}">${esc(g.name)}</option>`),
  );
  S.devices.forEach((d) =>
    sel.insertAdjacentHTML("beforeend", `<option value="device:${d.id}">${esc(d.name)}</option>`),
  );
  openModal("m-broadcast");
};

// FIX: doBroadcast
// ANTES: sem try/catch; chamava loadDevices() mesmo quando S.devices estava vazio.
// DEPOIS: try/catch com toast; atualiza S.devices[] in-place como quickAssign.
window.doBroadcast = async () => {
  const plId   = q("#bc-pl").value;
  const target = q("#bc-target").value;
  if (!plId) return toast("Selecione a playlist", "err");

  try {
    const res = await post("devices/broadcast", { playlist_id: plId, target });
    closeModal("m-broadcast");
    toast(`Enviado para ${res.affected} TVs`);

    const plObj = S.playlists.find((p) => String(p.id) === String(plId));
    if (plObj) {
      S.devices = S.devices.map((d) => {
        const match =
          target === "all" ||
          (target === `device:${d.id}`) ||
          (target.startsWith("group:") && String(d.group_id) === target.split(":")[1]);
        return match
          ? { ...d, playlist_id: Number(plId), playlist_name: plObj.name }
          : d;
      });
      if (q("#sec-dispositivos")?.classList.contains("active")) renderDevices();
    }
  } catch (e) {
    toast(e.message, "err");
  }
};

// ═══════════════════════════════════════════════════════════════
// MEDIA
// ═══════════════════════════════════════════════════════════════

async function loadMedia() {
  S.media = await get("media");
  renderMedia();
}

function renderMedia() {
  if (!Array.isArray(S.media)) S.media = [];
  const search = q("#media-search")?.value.toLowerCase() ?? "";
  const type   = q("#media-filter")?.value ?? "";
  const list   = S.media.filter(
    (m) =>
      (!search || m.original.toLowerCase().includes(search)) &&
      (!type   || m.type === type),
  );
  q("#media-count").textContent = `${list.length} item${list.length !== 1 ? "s" : ""}`;
  q("#mgrid").innerHTML = list.length
    ? list.map((m) => `
      <div class="mcard">
        <div class="mcard-thumb">
          ${m.type === "video"
            ? `<video src="${mediaUrl(m.url)}" muted preload="metadata" style="width:100%;height:100%;object-fit:cover;"></video>`
            : `<img src="${mediaUrl(m.url)}" alt="${esc(m.original)}" loading="lazy" />`
          }
        </div>
        <div class="mcard-info">
          <div class="mcard-name">${esc(m.original)}</div>
          <div class="mcard-type">${m.type} · ${fmtSize(m.size)}</div>
        </div>
        <button class="mcard-delbtn" onclick="delMedia(${m.id},event)"><i class="bi bi-x"></i></button>
      </div>`).join("")
    : '<div class="empty">Nenhuma mídia</div>';
}

window.renderMedia = renderMedia;

const MAX_UPLOAD_MB = 100;
let _uploading = false;

window.doUpload = async (files) => {
  if (!files?.length) return;
  if (_uploading) { toast("Aguarde o upload atual terminar", "err"); return; }
  _uploading = true;

  const fileArr = Array.from(files);
  const prog = q("#upgprog");
  prog.style.display = "block";
  let ok = 0, fail = 0;

  for (let i = 0; i < fileArr.length; i++) {
    const f = fileArr[i];

    const maxBytes = MAX_UPLOAD_MB * 1024 * 1024;
    if (f.size > maxBytes) {
      fail++;
      toast(`${f.name}: arquivo muito grande (máx ${MAX_UPLOAD_MB} MB, este tem ${(f.size/1024/1024).toFixed(0)} MB)`, "err");
      continue;
    }
    const allowed = ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/webm','video/ogg'];
    if (!allowed.includes(f.type)) {
      fail++;
      toast(`${f.name}: tipo não permitido (${f.type || 'desconhecido'})`, "err");
      continue;
    }

    q("#upname").textContent = `${f.name} (${(f.size/1024/1024).toFixed(1)} MB)`;
    q("#uppct").textContent  = `${i + 1}/${fileArr.length}`;
    q("#upbar").style.width  = `${((i + 1) / fileArr.length) * 100}%`;

    try {
      const t  = token.get();
      const fd = new FormData();
      fd.append("file", f);

      const res = await fetch(`${API}/media`, {
        method:  "POST",
        headers: { Authorization: `Bearer ${t}` },
        body:    fd,
      });

      const raw = await res.text();
      const clean = raw.replace(/^[^{[]*/, '');
      let json;
      try { json = JSON.parse(clean); }
      catch { throw new Error("Resposta inválida: " + raw.slice(0, 120)); }

      if (!res.ok) throw new Error(json?.error || "Erro " + res.status);

      if (json.data && typeof json.data === "object") {
        const exists = S.media.some(m => m.id === json.data.id);
        if (!exists) S.media.unshift(json.data);
      }
      ok++;
    } catch (e) {
      fail++;
      toast(`${f.name}: ${e.message}`, "err");
    }

    if (i < fileArr.length - 1) await new Promise(r => setTimeout(r, 200));
  }

  prog.style.display = "none";
  q("#finput").value  = "";
  _uploading = false;
  renderMedia();
  if (ok > 0)   toast(`${ok} arquivo(s) enviado(s) com sucesso`);
  if (fail > 0) toast(`${fail} arquivo(s) falharam`, "err");
};

// FIX: delMedia
// ANTES: sem try/catch — erro silencioso, card não era removido.
// DEPOIS: try/catch com toast; remove de S.media[] e re-renderiza imediatamente.
window.delMedia = async (id, e) => {
  e.stopPropagation();
  if (!await confirmDlg("Remover esta mídia?")) return;
  try {
    await del(`media/${id}`);
    S.media = S.media.filter((m) => m.id !== id);
    renderMedia();
    toast("Mídia removida");
  } catch (e) {
    toast(e.message, "err");
  }
};

// Drag-and-drop upload
document.addEventListener("DOMContentLoaded", () => {
  const ua = document.getElementById("uparea");
  if (!ua) return;
  ua.addEventListener("dragover",  (e) => { e.preventDefault(); ua.style.borderColor = "var(--primary)"; });
  ua.addEventListener("dragleave", ()  => { ua.style.borderColor = ""; });
  ua.addEventListener("drop",      (e) => { e.preventDefault(); ua.style.borderColor = ""; window.doUpload(e.dataTransfer.files); });
});

// ═══════════════════════════════════════════════════════════════
// PAIRING
// ═══════════════════════════════════════════════════════════════

window.loadPairing = async function loadPairing() {
  const codes = await get("pairing");
  const el = q("#pair-list");
  if (!el) return;
  el.innerHTML = codes.length
    ? codes.map((c) => `
      <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--bord);">
        <span style="font-family:monospace;font-size:22px;letter-spacing:5px;color:var(--primary);font-weight:700;">${c.code}</span>
        <span style="font-size:12px;color:var(--mut);">expira: ${fmtDate(c.expires_at)}</span>
        <button class="btn-p btn-sm" style="margin-left:auto;" onclick="openPair('${c.code}')"><i class="bi bi-link-45deg"></i> Vincular</button>
      </div>`).join("")
    : '<p class="empty">Nenhum código pendente. Abra o player na TV para gerar um.</p>';
};

window.openPair = (code) => {
  q("#pair-code").textContent = code;
  populateSelect("pair-dev-select", S.devices, "name", {
    prependOption: { value: "__new__", label: "+ Criar novo dispositivo" },
  });
  togPairNew();
  openModal("m-pair");
};

window.togPairNew = () => {
  q("#pair-new-fields").style.display =
    q("#pair-dev-select").value === "__new__" ? "block" : "none";
};

window.doPairDevice = async () => {
  const code  = q("#pair-code").textContent;
  const devId = q("#pair-dev-select").value;
  const body  = { code };
  if (devId === "__new__") {
    body.name     = q("#pair-dev-nome").value.trim() || "Nova TV";
    body.location = q("#pair-dev-unit").value.trim();
  } else body.device_id = parseInt(devId);

  try {
    const res = await post("pairing/pair", body);
    closeModal("m-pair");
    toast("TV vinculada! URL: " + res.player_url);
    // Recarrega dispositivos pois o pareamento pode ter criado um novo device
    await loadDevices();
    loadPairing();
  } catch (e) {
    toast(e.message, "err");
  }
};

// ═══════════════════════════════════════════════════════════════
// CONFIG
// ═══════════════════════════════════════════════════════════════

window.doChangePass = async () => {
  const cur = q("#cfg-cur-pass").value;
  const nw  = q("#cfg-new-pass").value;
  if (!cur || !nw) return toast("Preencha os campos", "err");
  try {
    await post("auth/password", { current_password: cur, new_password: nw });
    toast("Senha alterada!");
    q("#cfg-cur-pass").value = "";
    q("#cfg-new-pass").value = "";
  } catch (e) {
    toast(e.message, "err");
  }
};

// ── Helpers globais ───────────────────────────────────────────
window.cm    = closeModal;
window.copyT = copyText;

document.addEventListener("keydown", (e) => {
  // Enter no login é tratado no login.php
});

// ═══════════════════════════════════════════════════════════════
// PAIRING via Dispositivos (modal m-pair-device)
// ═══════════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════════
// PAREAMENTO
// ═══════════════════════════════════════════════════════════════

let _qrStream   = null;
let _qrInterval = null;

window.openPairForDevice = (devId, devName) => {
  S.pairTargetDevId = devId || null;   // guarda o device alvo (se veio de uma TV existente)
  const inp = document.getElementById('pair-input-code');
  const nm  = document.getElementById('pair-tv-name');
  const loc = document.getElementById('pair-tv-location');
  const err = document.getElementById('pair-error');
  if (inp) inp.value = '';
  if (nm)  nm.value  = (devName && devName !== 'Nova TV') ? devName : '';
  if (loc) loc.value = '';
  if (err) { err.style.display = 'none'; err.textContent = ''; }

  // Popula grupo (pré-seleciona o grupo da TV se existir)
  const dev = devId ? S.devices.find(x => x.id === devId) : null;
  populateSelect('pair-tv-group', S.groups, 'name', { emptyLabel: 'sem grupo', selectedVal: dev?.group_id });

  switchPairTab('code');
  openModal('m-pair-device');
  setTimeout(() => inp?.focus(), 300);
};

window.openPairModal = () => window.openPairForDevice(null, '');

window.switchPairTab = (tab) => {
  const isCode = tab === 'code';
  document.getElementById('pair-tab-code')?.classList.toggle('hidden', !isCode);
  document.getElementById('pair-tab-cam')?.classList.toggle('hidden',  isCode);
  const bc = document.getElementById('tab-code');
  const bm = document.getElementById('tab-cam');
  if (bc) bc.className = (isCode ? 'btn-p' : 'btn-g') + ' btn-sm';
  if (bm) bm.className = (isCode ? 'btn-g' : 'btn-p') + ' btn-sm';
  if (!isCode) startCamera(); else stopCamera();
};

window.startCamera = async () => {
  const video  = document.getElementById('qr-video');
  const status = document.getElementById('qr-status');
  try {
    _qrStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    video.srcObject = _qrStream;
    await video.play();
    if (status) status.textContent = 'Aponte a câmera para o QR Code da TV';
    scanQRFrame();
  } catch (e) {
    if (status) status.textContent = 'Câmera não disponível: ' + e.message;
  }
};

window.stopCamera = () => {
  if (_qrInterval) { clearInterval(_qrInterval); _qrInterval = null; }
  if (_qrStream)   { _qrStream.getTracks().forEach(t => t.stop()); _qrStream = null; }
};

function scanQRFrame() {
  const video  = document.getElementById('qr-video');
  const canvas = document.getElementById('qr-canvas');
  const status = document.getElementById('qr-status');
  _qrInterval = setInterval(() => {
    if (!video || video.readyState !== video.HAVE_ENOUGH_DATA) return;
    const ctx = canvas.getContext('2d');
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    if (window.jsQR) {
      const code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
      if (code?.data) {
        const match = code.data.match(/\b(\d{6})\b/);
        if (match) {
          stopCamera();
          const inp = document.getElementById('pair-input-code');
          if (inp) inp.value = match[1];
          if (status) status.textContent = 'QR detectado: ' + match[1];
          switchPairTab('code');
          doPairDeviceNew();
        }
      }
    }
  }, 300);
}

window.doPairDeviceNew = async () => {
  const code     = (document.getElementById('pair-input-code')?.value || '').replace(/\D/g, '');
  const tvName   = (document.getElementById('pair-tv-name')?.value   || '').trim();
  const tvLoc    = (document.getElementById('pair-tv-location')?.value || '').trim();
  const tvGroup  = document.getElementById('pair-tv-group')?.value || null;
  const errEl    = document.getElementById('pair-error');
  const btn      = document.getElementById('btn-do-pair');

  if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }

  if (code.length !== 6) {
    if (errEl) { errEl.textContent = 'Digite os 6 dígitos do código exibido na TV'; errEl.style.display = 'block'; }
    document.getElementById('pair-input-code')?.focus();
    return;
  }
  if (!tvName) {
    if (errEl) { errEl.textContent = 'Dê um nome para esta TV'; errEl.style.display = 'block'; }
    document.getElementById('pair-tv-name')?.focus();
    return;
  }

  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Pareando...'; }

  try {
    if (S.pairTargetDevId) {
      // Pareando uma TV JÁ criada no admin → vincula o código a ela (pair)
      // Isso transfere o token da TV auto-gerada e remove a auto-gerada (sem duplicar)
      await post('pairing/pair', {
        code,
        device_id: S.pairTargetDevId,
      });
      // Atualiza nome/local/grupo da TV alvo
      await put(`devices/${S.pairTargetDevId}`, {
        name:        tvName,
        location:    tvLoc,
        group_id:    tvGroup,
        playlist_id: (S.devices.find(d => d.id === S.pairTargetDevId)?.playlist_id) || null,
      });
    } else {
      // Pareamento avulso (botão genérico) → renomeia a TV auto-gerada (confirm)
      const res = await post('pairing/confirm', {
        code,
        name:     tvName,
        location: tvLoc,
      });
      // Aplica grupo se selecionado
      if (tvGroup && res.device_id) {
        await put(`devices/${res.device_id}`, {
          name:     tvName,
          location: tvLoc,
          group_id: tvGroup,
        });
      }
    }

    closeModal('m-pair-device');
    toast(`✅ TV "${tvName}" pareada com sucesso!`);

    await loadDevices();
    nav('dispositivos');

  } catch (e) {
    const msg = e.message || 'Erro ao parear — verifique o código';
    if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-link-45deg"></i> Parear TV'; }
    S.pairTargetDevId = null;
  }
};
