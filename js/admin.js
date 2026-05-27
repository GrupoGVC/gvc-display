/* ============================================================
   GVC Signage — Admin SPA  (ES Modules)
   ============================================================ */

import { get, post, put, del, upload, token, BASE, API } from './api.js';
import { q, qa, esc, openModal, closeModal, toast, copyText, fmtDate, fmtSize, populateSelect } from './utils.js';

// ── Estado global ─────────────────────────────────────────────
const S = {
  user:       null,
  devices:    [],
  groups:     [],
  playlists:  [],
  media:      [],
  schedules:  [],
  curPlId:    null,
  curPlItems: [],
  editDevId:  null,
  editGrId:   null,
};

// ── Bootstrap ─────────────────────────────────────────────────
(async () => {
  const saved = token.get();
  if (saved) {
    try {
      const d = await get('dashboard/index.php');
      S.user = { name: 'Admin' };
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
  q('#login-screen').style.display = 'flex';
  q('#app').style.display = 'none';
}

window.doLogin = async () => {
  const email = q('#l-email').value.trim();
  const pass  = q('#l-pass').value;
  const errEl = q('#l-err');
  errEl.style.display = 'none';

  if (!email || !pass) { errEl.textContent = 'Preencha e-mail e senha'; errEl.style.display = 'block'; return; }

  try {
    const res = await fetch(`${API}/auth/login.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password: pass }),
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || 'Erro no login');
    token.set(json.data.token);
    S.user = json.data.user;
    q('#login-screen').style.display = 'none';
    const d = await get('dashboard/index.php');
    initApp(d);
  } catch (e) {
    errEl.textContent = e.message;
    errEl.style.display = 'block';
  }
};

window.doLogout = () => { token.clear(); location.reload(); };

// ── App init ──────────────────────────────────────────────────
async function initApp(dashData) {
  q('#app').style.display = 'block';
  q('#conn-user').textContent = S.user?.name ?? 'Admin';

  renderDash(dashData);
  await Promise.all([loadGroups(), loadPlaylists(), loadMedia()]);

  setInterval(async () => {
    try { renderDash(await get('dashboard/index.php')); } catch {}
  }, 30_000);
}

// ── Navigation ────────────────────────────────────────────────
window.nav = (section) => {
  qa('.ni').forEach(n => n.classList.toggle('active', n.dataset.s === section));
  qa('.sec').forEach(s => s.classList.remove('active'));
  q(`#sec-${section}`)?.classList.add('active');

  const titles = {
    dashboard:'Dashboard', dispositivos:'Dispositivos', grupos:'Grupos',
    playlists:'Playlists', agendamentos:'Agendamentos', midia:'Biblioteca de Mídias',
    pareamento:'Pareamento', config:'Configurações',
  };
  q('#ptitle').textContent = titles[section] ?? section;
  q('#pact').innerHTML = '';

  if (section === 'dispositivos') loadDevices();
  if (section === 'agendamentos') loadSchedules();
  if (section === 'pareamento')   loadPairing();
};

// ── Dashboard ─────────────────────────────────────────────────
function renderDash(d) {
  q('#st-tvs').textContent = d.stats.total_devices;
  q('#st-on').textContent  = d.stats.online_devices;
  q('#st-pl').textContent  = d.stats.total_playlists;
  q('#st-md').textContent  = d.stats.total_media;
  q('#cfg-tv-count').textContent    = d.stats.total_devices;
  q('#cfg-media-count').textContent = d.stats.total_media;
  q('#cfg-pl-count').textContent    = d.stats.total_playlists;

  q('#dash-tv').innerHTML = d.devices.length
    ? d.devices.map(tv => `
      <div class="tv-row">
        <span class="sdot ${tv.status === 'online' ? 'sdot-on' : 'sdot-off'}"></span>
        <div class="tv-info">
          <div class="tv-name">${esc(tv.name)}</div>
          <div class="tv-loc">${esc(tv.location || '—')}${tv.playlist_name ? ' · ' + esc(tv.playlist_name) : ''}</div>
        </div>
        <span class="tag ${tv.status === 'online' ? 'tg-on' : 'tg-off'}">${tv.status}</span>
      </div>`).join('')
    : '<p class="empty">Nenhuma TV cadastrada</p>';

  const icons = { login:'🔑', create_device:'📺', broadcast:'📡', upload_media:'📁', create_playlist:'🎬', pair_device:'🔗', delete_device:'🗑', login_failed:'⚠️' };
  q('#dash-log').innerHTML = d.logs.length
    ? d.logs.map(l => `
      <div class="log-item">
        <span class="log-ic">${icons[l.action] || '📝'}</span>
        <div class="log-body">
          <div class="log-act">${esc(l.action.replace(/_/g,' '))}${l.detail ? ` <em style="color:var(--mut)">${esc(l.detail)}</em>` : ''}</div>
          <div class="log-time">${l.user_name ? esc(l.user_name) + ' · ' : ''}${fmtDate(l.created_at)}</div>
        </div>
      </div>`).join('')
    : '<p class="empty">Sem atividade recente</p>';
}

// ── Devices ───────────────────────────────────────────────────
async function loadDevices() {
  S.devices = await get('devices/index.php');
  renderDevices();
  buildGroupFilter();
}

function renderDevices() {
  const search = q('#dev-search')?.value.toLowerCase() ?? '';
  const gfil   = q('#dev-filter-grupo')?.value ?? '';
  const rows   = S.devices.filter(d =>
    (!search || d.name.toLowerCase().includes(search) || (d.location ?? '').toLowerCase().includes(search)) &&
    (!gfil   || String(d.group_id) === gfil)
  );
  q('#dev-tbody').innerHTML = rows.length
    ? rows.map(d => `
      <tr>
        <td><strong>${esc(d.name)}</strong><div style="font-size:12px;color:var(--mut);">${esc(d.location || '—')}</div></td>
        <td>${d.group_name ? `<span class="tag tg">${esc(d.group_name)}</span>` : '<span style="color:var(--mut);">—</span>'}</td>
        <td><span class="sdot ${d.status === 'online' ? 'sdot-on' : 'sdot-off'}"></span> <span class="tag ${d.status === 'online' ? 'tg-on' : 'tg-off'}">${d.status}</span></td>
        <td>${d.playlist_name ? esc(d.playlist_name) : '<span style="color:var(--mut);">—</span>'}</td>
        <td style="font-size:12px;color:var(--mut);">${d.last_ping ? fmtDate(d.last_ping) : 'nunca'}</td>
        <td>
          <div style="display:flex;gap:6px;">
            <button class="btn btn-g btn-sm" onclick="openEditDev(${d.id})">✏️</button>
            <button class="btn btn-g btn-sm" onclick="openDevInfo(${d.id})">⚙</button>
            <button class="btn btn-d btn-sm" onclick="delDev(${d.id})">🗑</button>
          </div>
        </td>
      </tr>`).join('')
    : `<tr><td colspan="6" class="empty">Nenhum dispositivo encontrado</td></tr>`;
}

function buildGroupFilter() {
  const sel = q('#dev-filter-grupo');
  if (!sel) return;
  sel.innerHTML = '<option value="">Todos os grupos</option>';
  S.groups.forEach(g => sel.insertAdjacentHTML('beforeend', `<option value="${g.id}">${esc(g.name)}</option>`));
}

window.filterDevs = () => renderDevices();

window.openAddDev = () => {
  S.editDevId = null;
  q('#m-dev-title').textContent = '📺 Adicionar TV';
  q('#btn-save-dev').textContent = 'Criar';
  q('#d-nome').value = ''; q('#d-unit').value = '';
  populateSelect('d-grupo',    S.groups,    'name', { emptyLabel: 'sem grupo' });
  populateSelect('d-playlist', S.playlists, 'name', { emptyLabel: 'nenhuma'   });
  openModal('m-dev');
};

window.openEditDev = id => {
  const d = S.devices.find(x => x.id === id); if (!d) return;
  S.editDevId = id;
  q('#m-dev-title').textContent = '✏️ Editar TV';
  q('#btn-save-dev').textContent = 'Salvar';
  q('#d-nome').value = d.name;
  q('#d-unit').value = d.location || '';
  populateSelect('d-grupo',    S.groups,    'name', { emptyLabel: 'sem grupo', selectedVal: d.group_id });
  populateSelect('d-playlist', S.playlists, 'name', { emptyLabel: 'nenhuma',   selectedVal: d.playlist_id });
  openModal('m-dev');
};

window.doSaveDev = async () => {
  const name = q('#d-nome').value.trim();
  if (!name) return toast('Nome é obrigatório', 'err');
  const body = { name, location: q('#d-unit').value.trim(), group_id: q('#d-grupo').value || null, playlist_id: q('#d-playlist').value || null };
  try {
    S.editDevId ? await put(`devices/index.php?id=${S.editDevId}`, body) : await post('devices/index.php', body);
    toast(S.editDevId ? 'TV atualizada' : 'TV criada');
    closeModal('m-dev');
    loadDevices();
  } catch (e) { toast(e.message, 'err'); }
};

window.openDevInfo = id => {
  const d = S.devices.find(x => x.id === id); if (!d) return;
  q('#m-devinfo-title').textContent = `⚙ ${esc(d.name)}`;
  q('#dev-purl').textContent  = d.player_url;
  q('#dev-token').textContent = d.token;
  openModal('m-devinfo');
};

window.delDev = async id => {
  if (!confirm('Remover este dispositivo?')) return;
  await del(`devices/index.php?id=${id}`);
  toast('Dispositivo removido');
  loadDevices();
};

// ── Groups ────────────────────────────────────────────────────
async function loadGroups() {
  S.groups = await get('groups/index.php');
  renderGroups();
}

function renderGroups() {
  const el = q('#gr-list'); if (!el) return;
  el.innerHTML = S.groups.length
    ? `<table><thead><tr><th>Nome</th><th>Descrição</th><th>TVs</th><th>Ações</th></tr></thead><tbody>
       ${S.groups.map(g => `
        <tr>
          <td><strong>${esc(g.name)}</strong></td>
          <td style="color:var(--mut);">${esc(g.description || '—')}</td>
          <td><span class="tag tg">${g.device_count}</span></td>
          <td><div style="display:flex;gap:6px;">
            <button class="btn btn-g btn-sm" onclick="openEditGrupo(${g.id})">✏️</button>
            <button class="btn btn-d btn-sm" onclick="delGrupo(${g.id})">🗑</button>
          </div></td>
        </tr>`).join('')}
       </tbody></table>`
    : '<p class="empty">Nenhum grupo criado</p>';
}

window.openAddGrupo = () => {
  S.editGrId = null;
  q('#m-grupo-title').textContent = '🗂️ Novo Grupo';
  q('#btn-save-grupo').textContent = 'Criar';
  q('#gr-nome').value = ''; q('#gr-desc').value = '';
  openModal('m-grupo');
};

window.openEditGrupo = id => {
  const g = S.groups.find(x => x.id === id); if (!g) return;
  S.editGrId = id;
  q('#m-grupo-title').textContent = '✏️ Editar Grupo';
  q('#btn-save-grupo').textContent = 'Salvar';
  q('#gr-nome').value = g.name;
  q('#gr-desc').value = g.description || '';
  openModal('m-grupo');
};

window.doSaveGrupo = async () => {
  const name = q('#gr-nome').value.trim();
  if (!name) return toast('Nome é obrigatório', 'err');
  const body = { name, description: q('#gr-desc').value.trim() };
  try {
    S.editGrId ? await put(`groups/index.php?id=${S.editGrId}`, body) : await post('groups/index.php', body);
    toast(S.editGrId ? 'Grupo atualizado' : 'Grupo criado');
    closeModal('m-grupo');
    await loadGroups();
  } catch (e) { toast(e.message, 'err'); }
};

window.delGrupo = async id => {
  if (!confirm('Remover este grupo?')) return;
  await del(`groups/index.php?id=${id}`);
  toast('Grupo removido');
  loadGroups();
};

// ── Playlists ─────────────────────────────────────────────────
async function loadPlaylists() {
  S.playlists = await get('playlists/index.php');
  renderPlaylists();
}

function renderPlaylists() {
  const el = q('#pl-tbody'); if (!el) return;
  el.innerHTML = S.playlists.length
    ? `<table><thead><tr><th>Nome</th><th>Itens</th><th>Tipo</th><th>Ações</th></tr></thead><tbody>
       ${S.playlists.map(p => `
        <tr>
          <td><strong>${esc(p.name)}</strong></td>
          <td><span class="tag tg">${p.item_count} itens</span></td>
          <td>${p.is_default ? '<span class="tag tg-def">⭐ Padrão</span>' : ''}</td>
          <td><div style="display:flex;gap:6px;">
            <button class="btn btn-p btn-sm" onclick="editPlaylist(${p.id})">✏️ Editar</button>
            <button class="btn btn-d btn-sm" onclick="delPl(${p.id})">🗑</button>
          </div></td>
        </tr>`).join('')}
       </tbody></table>`
    : '<p class="empty">Nenhuma playlist criada</p>';
}

window.showPlList = () => {
  q('#pl-list').classList.remove('hidden');
  q('#pl-edit').classList.add('hidden');
  loadPlaylists();
};

window.editPlaylist = async id => {
  S.curPlId = id;
  const pl = await get(`playlists/index.php?id=${id}`);
  S.curPlItems = pl.items || [];
  q('#pl-edit-name').textContent = pl.name;
  q('#pl-edit-default-badge').classList.toggle('hidden', !pl.is_default);
  q('#pl-list').classList.add('hidden');
  q('#pl-edit').classList.remove('hidden');
  renderPlItems();
  buildAssignSelect();
};

function renderPlItems() {
  const el = q('#pl-items');
  if (!S.curPlItems.length) {
    el.innerHTML = '<p class="empty">Nenhum item. Clique em + Adicionar</p>';
    q('#pl-hint').style.display = 'none';
    return;
  }
  q('#pl-hint').style.display = 'block';
  el.innerHTML = S.curPlItems.map((item, i) => {
    const src   = item.media_url || item.url || '';
    const thumb = item.type === 'video' ? `<video src="${src}" style="width:100%;height:100%;object-fit:cover;" muted></video>`
      : item.type === 'page' ? `<div style="font-size:20px;">🌐</div>`
      : `<img src="${src}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;" />`;
    return `
      <div class="pl-item" draggable="true" data-i="${i}"
           ondragstart="onDragStart(event,${i})" ondragover="onDragOver(event)" ondrop="onDrop(event,${i})">
        <span class="pl-drag">⠿</span>
        <div class="pl-thumb">${thumb}</div>
        <div class="pl-info" onclick="previewItem(${i})" style="cursor:pointer;">
          <div class="pl-iname">${esc(src || '(sem URL)')}</div>
          <div class="pl-imeta">${item.type} · ${item.duration}s</div>
        </div>
        <span class="pl-dur">${item.duration}s</span>
        <div><button class="btn btn-d btn-sm" onclick="delItem(${item.id})">🗑</button></div>
      </div>`;
  }).join('');
}

let _dragSrc = null;
window.onDragStart = (e, i) => { _dragSrc = i; e.dataTransfer.effectAllowed = 'move'; };
window.onDragOver  = e => { e.preventDefault(); };
window.onDrop      = async (e, i) => {
  e.preventDefault();
  if (_dragSrc === null || _dragSrc === i) return;
  const items = [...S.curPlItems];
  const [m] = items.splice(_dragSrc, 1);
  items.splice(i, 0, m);
  S.curPlItems = items;
  renderPlItems();
  await post('items/index.php?action=reorder', { items: items.map((it, idx) => ({ id: it.id, sort_order: idx })) });
};

window.previewItem = i => {
  const item = S.curPlItems[i]; if (!item) return;
  const src  = item.media_url || item.url || '';
  const pv   = q('#preview');
  if (item.type === 'video')       pv.innerHTML = `<video src="${src}" controls autoplay style="width:100%;height:100%;object-fit:contain;"></video>`;
  else if (item.type === 'page')   pv.innerHTML = `<iframe src="${src}" style="width:100%;height:100%;border:none;"></iframe>`;
  else                              pv.innerHTML = `<img src="${src}" style="width:100%;height:100%;object-fit:contain;" />`;
  q('#prev-hint').textContent = `${item.type} · ${item.duration}s`;
};

window.openAddPl = () => {
  q('#pl-nome').value = ''; q('#pl-is-default').checked = false;
  openModal('m-pl');
};

window.doAddPl = async () => {
  const name = q('#pl-nome').value.trim();
  if (!name) return toast('Nome é obrigatório', 'err');
  try {
    const pl = await post('playlists/index.php', { name, is_default: q('#pl-is-default').checked });
    closeModal('m-pl');
    toast('Playlist criada');
    await loadPlaylists();
    editPlaylist(pl.id);
  } catch (e) { toast(e.message, 'err'); }
};

window.openDupPl = () => {
  populateSelect('dup-src', S.playlists, 'name');
  q('#dup-name').value = '';
  openModal('m-dup');
};

window.doDupPl = async () => {
  const src  = q('#dup-src').value;
  const name = q('#dup-name').value.trim();
  if (!src || !name) return toast('Preencha todos os campos', 'err');
  await post('playlists/index.php', { name, copy_from: src });
  closeModal('m-dup');
  toast('Playlist duplicada');
  loadPlaylists();
};

window.deleteCurPl = async () => {
  if (!S.curPlId || !confirm('Excluir esta playlist?')) return;
  await del(`playlists/index.php?id=${S.curPlId}`);
  toast('Playlist excluída');
  window.showPlList();
};

window.delPl = async id => {
  if (!confirm('Excluir esta playlist?')) return;
  await del(`playlists/index.php?id=${id}`);
  toast('Playlist excluída');
  loadPlaylists();
};

// ── Items ─────────────────────────────────────────────────────
window.openAddItem = () => {
  q('#it-tipo').value = 'image'; q('#it-url').value = ''; q('#it-dur').value = '10';
  togDur();
  renderMpicker();
  openModal('m-item');
};

window.togDur = () => { q('#dur-grp').style.display = q('#it-tipo').value === 'video' ? 'none' : 'flex'; };

function renderMpicker() {
  const type = q('#it-tipo').value;
  const list = type === 'page' ? [] : S.media.filter(m => m.type === type);
  q('#mpicker').innerHTML = list.length
    ? list.map(m => `
      <div class="mp-item" onclick="pickMedia('${m.url}',this)">
        ${m.type === 'video' ? `<video src="${m.url}" muted style="width:100%;height:100%;object-fit:cover;"></video>`
                             : `<img src="${m.url}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;" />`}
      </div>`).join('')
    : '<p style="font-size:12px;color:var(--mut);grid-column:1/-1;">Nenhuma mídia disponível</p>';
}

window.pickMedia = (url, el) => {
  qa('#mpicker .mp-item').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  q('#it-url').value = url;
};

window.doAddItem = async () => {
  const url  = q('#it-url').value.trim();
  const type = q('#it-tipo').value;
  if (!url || !S.curPlId) return toast('Informe a URL', 'err');
  const mediaId = S.media.find(m => m.url === url)?.id || null;
  await post('items/index.php', { playlist_id: S.curPlId, type, url, duration: parseInt(q('#it-dur').value) || 10, media_id: mediaId });
  closeModal('m-item');
  toast('Item adicionado');
  const pl = await get(`playlists/index.php?id=${S.curPlId}`);
  S.curPlItems = pl.items || [];
  renderPlItems();
};

window.delItem = async id => {
  if (!confirm('Remover este item?')) return;
  await del(`items/index.php?id=${id}`);
  toast('Item removido');
  const pl = await get(`playlists/index.php?id=${S.curPlId}`);
  S.curPlItems = pl.items || [];
  renderPlItems();
};

// ── Assign ────────────────────────────────────────────────────
function buildAssignSelect() {
  const sel = q('#pl-assign-target'); if (!sel) return;
  sel.innerHTML = '<option value="">— selecione —</option><option value="all">📺 Todas as TVs</option>';
  S.groups.forEach(g  => sel.insertAdjacentHTML('beforeend', `<option value="group:${g.id}">🗂️ ${esc(g.name)}</option>`));
  S.devices.forEach(d => sel.insertAdjacentHTML('beforeend', `<option value="device:${d.id}">📺 ${esc(d.name)}</option>`));
}

window.quickAssign = async () => {
  const target = q('#pl-assign-target').value;
  if (!target || !S.curPlId) return toast('Selecione o destino', 'err');
  const res = await post('devices/broadcast.php', { playlist_id: S.curPlId, target });
  toast(`Playlist enviada para ${res.affected} TV(s)!`);
};

// ── Broadcast ─────────────────────────────────────────────────
window.openBroadcast = () => {
  populateSelect('bc-pl', S.playlists, 'name', { emptyLabel: 'selecione' });
  const sel = q('#bc-target');
  sel.innerHTML = '<option value="all">📺 Todas as TVs</option>';
  S.groups.forEach(g  => sel.insertAdjacentHTML('beforeend', `<option value="group:${g.id}">🗂️ ${esc(g.name)}</option>`));
  S.devices.forEach(d => sel.insertAdjacentHTML('beforeend', `<option value="device:${d.id}">📺 ${esc(d.name)}</option>`));
  openModal('m-broadcast');
};

window.doBroadcast = async () => {
  const plId   = q('#bc-pl').value;
  const target = q('#bc-target').value;
  if (!plId) return toast('Selecione a playlist', 'err');
  const res = await post('devices/broadcast.php', { playlist_id: plId, target });
  closeModal('m-broadcast');
  toast(`Enviado para ${res.affected} TVs`);
  if (S.devices.length) loadDevices();
};

// ── Media ─────────────────────────────────────────────────────
async function loadMedia() {
  S.media = await get('media/index.php');
  renderMedia();
}

function renderMedia() {
  const search = q('#media-search')?.value.toLowerCase() ?? '';
  const type   = q('#media-filter')?.value ?? '';
  const list   = S.media.filter(m =>
    (!search || m.original.toLowerCase().includes(search)) &&
    (!type   || m.type === type)
  );
  q('#media-count').textContent = `${list.length} item${list.length !== 1 ? 's' : ''}`;
  q('#mgrid').innerHTML = list.length
    ? list.map(m => `
      <div class="mcard">
        <div class="mcard-thumb">
          ${m.type === 'video' ? `<video src="${m.url}" muted style="width:100%;height:100%;object-fit:cover;"></video>`
                               : `<img src="${m.url}" alt="${esc(m.original)}" loading="lazy" />`}
        </div>
        <div class="mcard-info">
          <div class="mcard-name">${esc(m.original)}</div>
          <div class="mcard-type">${m.type} · ${fmtSize(m.size)}</div>
        </div>
        <button class="mcard-delbtn" onclick="delMedia(${m.id},event)">✕</button>
      </div>`).join('')
    : '<div class="empty">Nenhuma mídia</div>';
}

window.renderMedia = renderMedia;

window.doUpload = async files => {
  if (!files?.length) return;
  const prog = q('#upgprog');
  prog.style.display = 'block';
  for (let i = 0; i < files.length; i++) {
    const f  = files[i];
    const fd = new FormData();
    fd.append('file', f);
    q('#upname').textContent = f.name;
    q('#uppct').textContent  = `${i + 1}/${files.length}`;
    q('#upbar').style.width  = `${((i + 1) / files.length) * 100}%`;
    try {
      const res = await fetch(`${API}/media/index.php`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token.get()}` },
        body: fd,
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'Erro');
      S.media.unshift(json.data);
    } catch (e) { toast(`${f.name}: ${e.message}`, 'err'); }
  }
  prog.style.display = 'none';
  q('#finput').value = '';
  renderMedia();
  toast(`${files.length} arquivo(s) enviado(s)`);
};

window.delMedia = async (id, e) => {
  e.stopPropagation();
  if (!confirm('Remover esta mídia?')) return;
  await del(`media/index.php?id=${id}`);
  S.media = S.media.filter(m => m.id !== id);
  renderMedia();
  toast('Mídia removida');
};

// Drag-and-drop upload
document.addEventListener('DOMContentLoaded', () => {
  const ua = document.getElementById('uparea');
  if (!ua) return;
  ua.addEventListener('dragover', e => { e.preventDefault(); ua.style.borderColor = 'var(--primary)'; });
  ua.addEventListener('dragleave', () => { ua.style.borderColor = ''; });
  ua.addEventListener('drop', e => { e.preventDefault(); ua.style.borderColor = ''; window.doUpload(e.dataTransfer.files); });
});

// ── Schedules ─────────────────────────────────────────────────
async function loadSchedules() {
  S.schedules = await get('schedules/index.php');
  renderSchedules();
}

function renderSchedules() {
  const el = q('#sch-list'); if (!el) return;
  const days = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
  el.innerHTML = S.schedules.length
    ? `<table><thead><tr><th>Playlist</th><th>Destino</th><th>Início</th><th>Fim</th><th>Repetição</th><th>Status</th><th>Ações</th></tr></thead><tbody>
       ${S.schedules.map(s => `
        <tr>
          <td>${esc(s.playlist_name)}</td>
          <td>${esc(s.target_type)}${s.target_id ? ' #' + s.target_id : ''}</td>
          <td style="font-size:12px;">${fmtDate(s.starts_at)}</td>
          <td style="font-size:12px;">${fmtDate(s.ends_at)}</td>
          <td style="font-size:12px;">${s.repeat_weekly ? (s.weekdays || []).map(d => days[d]).join(', ') : 'Único'}</td>
          <td><span class="tag ${s.active ? 'tg-on' : 'tg-off'}">${s.active ? 'Ativo' : 'Inativo'}</span></td>
          <td><div style="display:flex;gap:6px;">
            <button class="btn btn-g btn-sm" onclick="toggleSched(${s.id},${s.active})">${s.active ? '⏸' : '▶'}</button>
            <button class="btn btn-d btn-sm" onclick="delSched(${s.id})">🗑</button>
          </div></td>
        </tr>`).join('')}
       </tbody></table>`
    : '<p class="empty">Nenhum agendamento criado</p>';
}

window.openAddAgenda = () => {
  populateSelect('ag-pl', S.playlists, 'name', { emptyLabel: 'selecione' });
  const sel = q('#ag-target');
  sel.innerHTML = '<option value="all">📺 Todas as TVs</option>';
  S.groups.forEach(g  => sel.insertAdjacentHTML('beforeend', `<option value="group:${g.id}">🗂️ ${esc(g.name)}</option>`));
  S.devices.forEach(d => sel.insertAdjacentHTML('beforeend', `<option value="device:${d.id}">📺 ${esc(d.name)}</option>`));
  q('#ag-repeat').checked = false;
  q('#ag-days-wrap').classList.add('hidden');
  openModal('m-agenda');
};

window.togRepeat = () => { q('#ag-days-wrap').classList.toggle('hidden', !q('#ag-repeat').checked); };

window.doSaveAgenda = async () => {
  const pl     = q('#ag-pl').value;
  const target = q('#ag-target').value;
  const starts = q('#ag-inicio').value;
  const ends   = q('#ag-fim').value;
  const repeat = q('#ag-repeat').checked;
  if (!pl || !starts || !ends) return toast('Preencha todos os campos', 'err');

  const weekdays = repeat ? [...qa('#ag-days-wrap input:checked')].map(c => parseInt(c.value)) : [];
  let ttype = 'all', tid = null;
  if (target.startsWith('group:'))  { ttype = 'group';  tid = parseInt(target.split(':')[1]); }
  if (target.startsWith('device:')) { ttype = 'device'; tid = parseInt(target.split(':')[1]); }

  await post('schedules/index.php', {
    playlist_id: pl, target_type: ttype, target_id: tid,
    starts_at: starts.replace('T', ' '), ends_at: ends.replace('T', ' '),
    repeat_weekly: repeat, weekdays,
  });
  closeModal('m-agenda');
  toast('Agendamento criado');
  loadSchedules();
};

window.toggleSched = async (id, active) => {
  await put(`schedules/index.php?id=${id}`, { active: !active });
  loadSchedules();
};

window.delSched = async id => {
  if (!confirm('Remover agendamento?')) return;
  await del(`schedules/index.php?id=${id}`);
  toast('Agendamento removido');
  loadSchedules();
};

// ── Pairing ───────────────────────────────────────────────────
async function loadPairing() {
  const codes = await get('pairing/index.php');
  const el    = q('#pair-list'); if (!el) return;
  el.innerHTML = codes.length
    ? codes.map(c => `
      <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--bord);">
        <span style="font-family:monospace;font-size:22px;letter-spacing:5px;color:var(--primary);font-weight:700;">${c.code}</span>
        <span style="font-size:12px;color:var(--mut);">expira: ${fmtDate(c.expires_at)}</span>
        <button class="btn btn-p btn-sm" style="margin-left:auto;" onclick="openPair('${c.code}')">🔗 Vincular</button>
      </div>`).join('')
    : '<p class="empty">Nenhum código pendente. Abra o player na TV para gerar um.</p>';
}

window.openPair = code => {
  q('#pair-code').textContent = code;
  populateSelect('pair-dev-select', S.devices, 'name', {
    prependOption: { value: '__new__', label: '+ Criar novo dispositivo' },
  });
  togPairNew();
  openModal('m-pair');
};

window.togPairNew = () => {
  q('#pair-new-fields').style.display = q('#pair-dev-select').value === '__new__' ? 'block' : 'none';
};

window.doPairDevice = async () => {
  const code  = q('#pair-code').textContent;
  const devId = q('#pair-dev-select').value;
  const body  = { code };
  if (devId === '__new__') { body.name = q('#pair-dev-nome').value.trim() || 'Nova TV'; body.location = q('#pair-dev-unit').value.trim(); }
  else body.device_id = parseInt(devId);

  try {
    const res = await post('pairing/index.php?action=link', body);
    closeModal('m-pair');
    toast('TV vinculada! URL: ' + res.player_url);
    loadPairing();
    loadDevices();
  } catch (e) { toast(e.message, 'err'); }
};

// ── Config ────────────────────────────────────────────────────
window.doChangePass = async () => {
  const cur = q('#cfg-cur-pass').value;
  const nw  = q('#cfg-new-pass').value;
  if (!cur || !nw) return toast('Preencha os campos', 'err');
  try {
    await post('auth/password.php', { current_password: cur, new_password: nw });
    toast('Senha alterada!');
    q('#cfg-cur-pass').value = ''; q('#cfg-new-pass').value = '';
  } catch (e) { toast(e.message, 'err'); }
};

// ── Close modal helpers (chamados do HTML) ────────────────────
window.cm = closeModal;

// ── Copy ──────────────────────────────────────────────────────
window.copyT = copyText;

// ── Enter no login ────────────────────────────────────────────
document.addEventListener('keydown', e => {
  if (e.key === 'Enter' && q('#login-screen')?.style.display !== 'none') window.doLogin();
});
