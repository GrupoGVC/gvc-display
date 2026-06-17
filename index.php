<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once __DIR__ . '/api/meta_injector.php';
?><!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>GVC Display | Painel Adm</title>
  <link rel="icon" href="assets/logos/logo_grupogvc_white.ico" type="image/x-icon"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>
  <link rel="stylesheet" href="css/main.css?v=1781709886"/>
  <link rel="stylesheet" href="css/admin.css?v=1781709886"/>
</head>
<body>

<!-- APP -->
<div id="app" style="display:none;">

  <!-- SIDEBAR -->
  <div id="sb">
    <div class="sb-logo">
      <svg width="32" height="32" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="2" y="8" width="44" height="28" rx="4" fill="#1a1f2e" stroke="#4f8cff" stroke-width="2.5"/>
        <rect x="8" y="14" width="32" height="16" rx="2" fill="#0d1117"/>
        <rect x="18" y="38" width="12" height="3" rx="1.5" fill="#4f8cff"/>
        <rect x="14" y="41" width="20" height="2" rx="1" fill="#4f8cff" opacity=".5"/>
        <circle cx="24" cy="22" r="4" fill="#4f8cff" opacity=".8"/>
        <path d="M21 22l2 2 4-4" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
      <div>
        <div class="sb-brand">GVC <span style="color:var(--primary)">Display</span></div>
        <div class="sb-sub">Sinalização Digital</div>
      </div>
    </div>
    <nav class="sb-nav">
      <button class="ni active" data-s="dashboard"    onclick="nav('dashboard')">
        <i class="bi bi-speedometer2"></i> Dashboard
      </button>
      <button class="ni" data-s="dispositivos" onclick="nav('dispositivos')">
        <i class="bi bi-display"></i> Dispositivos
      </button>
      <button class="ni" data-s="grupos"       onclick="nav('grupos')">
        <i class="bi bi-collection"></i> Grupos
      </button>
      <button class="ni" data-s="playlists"    onclick="nav('playlists')">
        <i class="bi bi-play-circle"></i> Playlists
      </button>
      <button class="ni" data-s="agendamentos" onclick="nav('agendamentos')">
        <i class="bi bi-calendar3"></i> Agendamentos
      </button>
      <button class="ni" data-s="midia"        onclick="nav('midia')">
        <i class="bi bi-images"></i> Mídia
      </button>
      <button class="ni" data-s="config"       onclick="nav('config')">
        <i class="bi bi-gear"></i> Config
      </button>
    </nav>
    <div class="sb-foot">
      <span class="dot-on"></span>
      <span id="conn-user">—</span>
    </div>
  </div>
  <!-- Overlay mobile -->
  <div id="sb-overlay" onclick="toggleSb()"></div>

  <!-- MAIN -->
  <div id="main">
    <div class="topbar">
      <div class="d-flex align-items-center gap-3">
        <button id="btn-sb" onclick="toggleSb()">
          <i class="bi bi-list" style="font-size:22px"></i>
        </button>
        <h1 id="ptitle">Dashboard</h1>
      </div>
      <div id="pact" class="d-flex gap-2 align-items-center"></div>
    </div>

    <div class="content">

      <!-- ══ DASHBOARD ══════════════════════════════════════════ -->
      <div id="sec-dashboard" class="sec active">
        <div class="row g-3 mb-3">
          <div class="col-6 col-lg-3">
            <div class="stat-card">
              <div class="d-flex justify-content-between align-items-start">
                <div class="sl">Total de TVs</div>
                <div class="si" style="background:rgba(91,127,255,.12);">
                  <i class="bi bi-display" style="color:var(--primary)"></i>
                </div>
              </div>
              <div class="sv" id="st-tvs">—</div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="stat-card">
              <div class="d-flex justify-content-between align-items-start">
                <div class="sl">Online agora</div>
                <div class="si" style="background:rgba(34,197,94,.12);">
                  <i class="bi bi-wifi" style="color:var(--green)"></i>
                </div>
              </div>
              <div class="sv" id="st-on" style="color:var(--green)">—</div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="stat-card">
              <div class="d-flex justify-content-between align-items-start">
                <div class="sl">Playlists</div>
                <div class="si" style="background:rgba(234,179,8,.12);">
                  <i class="bi bi-play-circle" style="color:var(--yellow)"></i>
                </div>
              </div>
              <div class="sv" id="st-pl">—</div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="stat-card">
              <div class="d-flex justify-content-between align-items-start">
                <div class="sl">Mídias</div>
                <div class="si" style="background:rgba(249,115,22,.12);">
                  <i class="bi bi-images" style="color:var(--orange)"></i>
                </div>
              </div>
              <div class="sv" id="st-md">—</div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12 col-xl-5">
            <div class="gvc-card h-100">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <div class="gvc-card-title">Status das TVs</div>
                  <div style="font-size:11px;color:var(--mut);margin-top:2px;">Atualiza a cada 10s</div>
                </div>
                <button class="btn-p btn-sm" onclick="openBroadcast()">
                  <i class="bi bi-broadcast" style="font-size:14px"></i>Broadcast
                </button>
              </div>
              <div id="dash-tv"></div>
            </div>
          </div>
          <div class="col-12 col-xl-7">
            <div class="gvc-card h-100">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                  <div class="gvc-card-title">Atividade Recente</div>
                  <div style="font-size:11px;color:var(--mut);margin-top:2px;">Últimas ações no sistema</div>
                </div>
                <button class="btn-g btn-sm" onclick="openLogModal()">
                  <i class="bi bi-arrows-fullscreen" style="font-size:13px"></i>Ver tudo
                </button>
              </div>
              <div id="dash-log"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ DISPOSITIVOS ═══════════════════════════════════════ -->
      <div id="sec-dispositivos" class="sec">
        <div class="gvc-card">
          <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
              <div class="gvc-card-title">Dispositivos</div>
              <div style="font-size:12px;color:var(--mut);margin-top:3px;">Gerencie as TVs conectadas</div>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
              <input type="text" id="dev-search" placeholder="Buscar TV..."
                oninput="filterDevs()"
                style="width:180px;padding:7px 12px;background:var(--bg3);border:1px solid var(--bord2);border-radius:8px;color:var(--txt);font-size:13px;outline:none;"/>
              <select id="dev-filter-grupo" onchange="filterDevs()"
                style="padding:7px 12px;background:var(--bg3);border:1px solid var(--bord2);border-radius:8px;color:var(--txt);font-size:13px;min-width:150px;">
                <option value="">Todos os grupos</option>
              </select>
              <button class="btn-p" onclick="openAddDev()">
                <i class="bi bi-plus-lg"></i>Adicionar TV
              </button>
            </div>
          </div>
          <div class="table-responsive">
            <table class="gvc-table">
              <thead>
                <tr>
                  <th>Nome / Local</th>
                  <th>Grupo</th>
                  <th>Status</th>
                  <th>Playlist</th>
                  <th>Último ping</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody id="dev-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ══ GRUPOS ════════════════════════════════════════════ -->
      <div id="sec-grupos" class="sec">
        <div class="gvc-card">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <div class="gvc-card-title">Grupos de TVs</div>
              <div style="font-size:12px;color:var(--mut);margin-top:3px;">Organize TVs por local ou setor</div>
            </div>
            <button class="btn-p" onclick="openAddGrupo()">
              <i class="bi bi-plus-lg"></i>Novo Grupo
            </button>
          </div>
          <div id="gr-list"></div>
        </div>
      </div>

      <!-- ══ PLAYLISTS ══════════════════════════════════════════ -->
      <div id="sec-playlists" class="sec">
        <!-- Lista de playlists -->
        <div id="pl-list">
          <div class="gvc-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <div>
                <div class="gvc-card-title">Playlists</div>
                <div style="font-size:12px;color:var(--mut);margin-top:3px;">Crie e organize apresentações</div>
              </div>
              <div class="d-flex gap-2">
                <button class="btn-g" onclick="openDupPl()">
                  <i class="bi bi-copy"></i>Duplicar
                </button>
                <button class="btn-p" onclick="openAddPl()">
                  <i class="bi bi-plus-lg"></i>Nova Playlist
                </button>
              </div>
            </div>
            <div id="pl-tbody"></div>
          </div>
        </div>

        <!-- Editor de playlist -->
        <div id="pl-edit" class="hidden">
          <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
            <button class="btn-g btn-sm" onclick="showPlList()">
              <i class="bi bi-arrow-left"></i>Voltar
            </button>
            <h2 id="pl-edit-name" style="font-size:15px;font-weight:700;margin:0;"></h2>
            <span id="pl-edit-default-badge" class="tag tg-def hidden">⭐ Padrão</span>
            <button class="btn-d btn-sm ms-auto" onclick="deleteCurPl()">
              <i class="bi bi-trash3"></i>Excluir
            </button>
          </div>
          <div class="row g-3">
            <div class="col-12 col-lg-7">
              <div class="gvc-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <span class="gvc-card-title">Itens da Playlist</span>
                  <button class="btn-p btn-sm" onclick="openAddItem()">
                    <i class="bi bi-plus-lg"></i>Adicionar
                  </button>
                </div>
                <div id="pl-items" class="sortable-list"></div>
                <p id="pl-hint" style="font-size:12px;color:var(--mut);text-align:center;margin-top:8px;display:none;">
                  Arraste para reordenar
                </p>
              </div>
            </div>
            <div class="col-12 col-lg-5">
              <div class="gvc-card mb-3">
                <div class="gvc-card-title mb-2">Pré-visualização</div>
                <div id="preview" style="background:#000;aspect-ratio:16/9;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                  <span style="color:rgba(255,255,255,.2);font-size:12px;">Clique em um item</span>
                </div>
                <div id="prev-hint" style="font-size:11px;color:var(--mut);text-align:center;margin-top:6px;"></div>
              </div>
              <div class="gvc-card">
                <div class="gvc-card-title mb-3">Distribuição rápida</div>
                <div class="fg">
                  <label>Atribuir a</label>
                  <select id="pl-assign-target">
                    <option value="">— selecione —</option>
                    <option value="all">Todas as TVs</option>
                  </select>
                </div>
                <button class="btn-p w-100 justify-content-center" onclick="quickAssign()" id="btn-quick-assign">
                  Aplicar <i class="bi bi-arrow-right"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ AGENDAMENTOS ═══════════════════════════════════════ -->
      <div id="sec-agendamentos" class="sec">
        <div class="gvc-card">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <div class="gvc-card-title">Agendamentos</div>
              <div style="font-size:12px;color:var(--mut);margin-top:3px;">Programe apresentações por data e horário</div>
            </div>
            <button class="btn-p" onclick="openAddAgenda()">
              <i class="bi bi-plus-lg"></i>Novo Agendamento
            </button>
          </div>
          <div id="sch-list"></div>
        </div>
      </div>

      <!-- ══ MÍDIA ══════════════════════════════════════════════ -->
      <div id="sec-midia" class="sec">
        <div class="uparea mb-3" id="uparea" onclick="document.getElementById('finput').click()">
          <div style="display:flex;align-items:center;justify-content:center;gap:14px;">
            <i class="bi bi-cloud-upload" style="font-size:30px;color:var(--primary)"></i>
            <div>
              <p style="font-weight:700;font-size:14px;margin:0;">Clique ou arraste arquivos aqui</p>
              <p style="font-size:12px;color:var(--mut);margin:3px 0 0;">JPG, PNG, GIF, WebP, MP4, WebM — máx. 100 MB</p>
            </div>
          </div>
          <input type="file" id="finput" multiple accept="image/*,video/*" style="display:none" onchange="doUpload(this.files)"/>
        </div>
        <div class="progwrap" id="upgprog">
          <div class="d-flex justify-content-between" style="font-size:13px;">
            <span id="upname">Enviando...</span><span id="uppct"></span>
          </div>
          <div class="progbar"><div class="progfill" id="upbar" style="width:0%"></div></div>
        </div>
        <div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
          <input type="text" id="media-search" placeholder="Buscar mídia..." oninput="renderMedia()"
            style="width:200px;padding:7px 12px;background:var(--bg3);border:1px solid var(--bord2);border-radius:8px;color:var(--txt);font-size:13px;outline:none;"/>
          <select id="media-filter" onchange="renderMedia()"
            style="padding:7px 12px;background:var(--bg3);border:1px solid var(--bord2);border-radius:8px;color:var(--txt);font-size:13px;">
            <option value="">Todos os tipos</option>
            <option value="image">Imagens</option>
            <option value="video">Vídeos</option>
          </select>
          <span id="media-count" style="font-size:12px;color:var(--mut);margin-left:auto;font-weight:500;"></span>
        </div>
        <div class="mgrid" id="mgrid">
          <div class="empty">Carregando...</div>
        </div>
      </div>

      <!-- ══ CONFIG ═════════════════════════════════════════════ -->
      <div id="sec-config" class="sec">
        <div class="row g-3">
          <div class="col-12 col-md-5">
            <div class="gvc-card">
              <div class="gvc-card-title mb-1">Alterar Senha</div>
              <p style="font-size:12px;color:var(--mut);margin-bottom:16px;">Atualize suas credenciais</p>
              <form onsubmit="doChangePass();return false;" autocomplete="on">
                <div class="fg">
                  <label>Senha atual</label>
                  <input type="password" id="cfg-cur-pass" autocomplete="current-password" placeholder="••••••••"/>
                </div>
                <div class="fg">
                  <label>Nova senha</label>
                  <input type="password" id="cfg-new-pass" autocomplete="new-password" placeholder="••••••••"/>
                </div>
                <button type="submit" class="btn-p">
                  <i class="bi bi-lock"></i>Salvar senha
                </button>
              </form>
            </div>
          </div>
          <div class="col-12 col-md-7">
            <div class="gvc-card">
              <div class="gvc-card-title mb-1">Informações do sistema</div>
              <p style="font-size:12px;color:var(--mut);margin-bottom:16px;">Status atual do GVC Display</p>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div class="cfg-stat-box">
                  <div class="cfg-stat-label">TVs cadastradas</div>
                  <div class="cfg-stat-val" id="cfg-tv-count">—</div>
                </div>
                <div class="cfg-stat-box">
                  <div class="cfg-stat-label">Mídias</div>
                  <div class="cfg-stat-val" id="cfg-media-count">—</div>
                </div>
                <div class="cfg-stat-box">
                  <div class="cfg-stat-label">Playlists</div>
                  <div class="cfg-stat-val" id="cfg-pl-count">—</div>
                </div>
                <div class="cfg-stat-box">
                  <div class="cfg-stat-label">Versão</div>
                  <div style="font-size:16px;font-weight:700;">3.0.0</div>
                </div>
              </div>
              <div style="display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid var(--bord);">
                <span style="font-size:12px;color:var(--mut);">PHP 8 · MySQL · JWT</span>
                <button class="btn-d btn-sm" onclick="doLogout()">
                  <i class="bi bi-box-arrow-right"></i>Sair
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /app -->

<!-- ══════════════ MODAIS ══════════════════════════════════════ -->

<!-- Modal: Log completo -->
<div class="modal fade" id="m-log" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="bi bi-clock-history"></i>Atividade Recente</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="max-height:60vh;overflow-y:auto;">
        <div id="log-modal-body"></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Dispositivo (criar/editar) -->
<div class="modal fade" id="m-dev" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title" id="m-dev-title"><i class="bi bi-display"></i>Adicionar TV</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="fg"><label>Nome</label><input type="text" id="d-nome" placeholder="TV Recepção..."/></div>
        <div class="fg"><label>Local / Unidade</label><input type="text" id="d-unit" placeholder="Andar 2, Unidade Norte..."/></div>
        <div class="fg"><label>Grupo</label><select id="d-grupo"></select></div>
        <div class="fg"><label>Playlist padrão</label><select id="d-playlist"></select></div>
      </div>
      <div class="modal-footer">
        <button class="btn-g" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn-p" id="btn-save-dev" onclick="doSaveDev()">Criar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Detalhes TV -->
<div class="modal fade" id="m-devinfo" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title" id="m-devinfo-title"><i class="bi bi-gear"></i>Configurar TV</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="fg">
          <label>Status de Pareamento</label>
          <div id="dev-pair-status" style="font-size:13px;padding:4px 0;"></div>
        </div>
        <div class="fg">
          <label>URL da TV</label>
          <div class="tokdisp">
            <code id="dev-purl">—</code>
            <button class="copybtn" onclick="copyT(document.getElementById('dev-purl').textContent)">Copiar</button>
          </div>
        </div>
        <div class="fg">
          <label>Token</label>
          <div class="tokdisp">
            <code id="dev-token">—</code>
            <button class="copybtn" onclick="copyT(document.getElementById('dev-token').textContent)">Copiar</button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button id="btn-cancel-pair" class="btn-d btn-sm" onclick="cancelDevPairing()" style="display:none;">
          <i class="bi bi-link-45deg"></i>Encerrar Pareamento
        </button>
        <button class="btn-p" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Grupo -->
<div class="modal fade" id="m-grupo" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title" id="m-grupo-title"><i class="bi bi-folder-plus"></i>Novo Grupo</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="fg"><label>Nome</label><input type="text" id="gr-nome" placeholder="Unidade Norte..."/></div>
        <div class="fg"><label>Descrição</label><input type="text" id="gr-desc" placeholder="Opcional..."/></div>
      </div>
      <div class="modal-footer">
        <button class="btn-g" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn-p" id="btn-save-grupo" onclick="doSaveGrupo()">Criar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Nova Playlist -->
<div class="modal fade" id="m-pl" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="bi bi-collection-play"></i>Nova Playlist</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="fg"><label>Nome</label><input type="text" id="pl-nome" placeholder="Comunicados, Campanha Abril..."/></div>
        <div style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" id="pl-is-default" style="width:auto;accent-color:var(--primary)"/>
          <label for="pl-is-default" style="font-size:13px;cursor:pointer;">
            Usar como <strong>playlist padrão global</strong>
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-g" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn-p" onclick="doAddPl()">Criar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Duplicar Playlist -->
<div class="modal fade" id="m-dup" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="bi bi-copy"></i>Duplicar Playlist</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="fg"><label>Origem</label><select id="dup-src"></select></div>
        <div class="fg"><label>Nome da cópia</label><input type="text" id="dup-name" placeholder="Cópia de..."/></div>
      </div>
      <div class="modal-footer">
        <button class="btn-g" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn-p" onclick="doDupPl()">Duplicar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Adicionar Item -->
<div class="modal fade" id="m-item" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="bi bi-plus-circle"></i>Adicionar Item</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="fg">
          <label>Tipo</label>
          <select id="it-tipo" onchange="togDur();renderMpicker()">
            <option value="image">Imagem</option>
            <option value="video">Vídeo</option>
            <option value="page">Página Web (URL)</option>
          </select>
        </div>
        <div class="fg">
          <label>URL</label>
          <input type="text" id="it-url" placeholder="https:// ou /uploads/..."/>
          <p class="hint">Cole a URL ou clique em uma mídia da biblioteca abaixo</p>
        </div>
        <div class="fg" id="dur-grp">
          <label>Duração (segundos)</label>
          <input type="number" id="it-dur" value="10" min="1" max="600"/>
        </div>
        <div style="font-size:12px;color:var(--txt2);font-weight:500;margin-bottom:8px;">Selecionar da biblioteca:</div>
        <div class="mpicker" id="mpicker"></div>
      </div>
      <div class="modal-footer">
        <button class="btn-g" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn-p" id="btn-add-item" onclick="doAddItem()">
          <i class="bi bi-plus-lg"></i>Adicionar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Broadcast -->
<div class="modal fade" id="m-broadcast" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="bi bi-broadcast"></i>Broadcast</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p style="font-size:13px;color:var(--mut);margin-bottom:16px;">
          Envia uma playlist imediatamente para TVs selecionadas.
        </p>
        <div class="fg"><label>Playlist</label><select id="bc-pl"><option value="">— selecione —</option></select></div>
        <div class="fg"><label>Destino</label><select id="bc-target"><option value="all">Todas as TVs</option></select></div>
      </div>
      <div class="modal-footer">
        <button class="btn-g" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn-p" id="btn-do-broadcast" onclick="doBroadcast()">
          <i class="bi bi-send"></i>Enviar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Agendamento -->
<div class="modal fade" id="m-agenda" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="bi bi-calendar-check"></i>Novo Agendamento</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="fg"><label>Playlist</label><select id="ag-pl"><option value="">— selecione —</option></select></div>
        <div class="fg"><label>Destino</label><select id="ag-target"><option value="all">Todas as TVs</option></select></div>
        <div class="row g-2">
          <div class="col-6"><div class="fg"><label>Início</label><input type="datetime-local" id="ag-inicio"/></div></div>
          <div class="col-6"><div class="fg"><label>Fim</label><input type="datetime-local" id="ag-fim"/></div></div>
        </div>
        <div class="d-flex align-items-center gap-2 mb-2">
          <input type="checkbox" id="ag-repeat" style="width:auto;accent-color:var(--primary)" onchange="togRepeat()"/>
          <label for="ag-repeat" style="font-size:13px;cursor:pointer;">Repetir semanalmente</label>
        </div>
        <div id="ag-days-wrap" class="hidden">
          <label style="display:block;font-size:12px;color:var(--txt2);margin-bottom:8px;">Dias da semana</label>
          <div class="d-flex gap-2 flex-wrap">
            <label class="day-check"><input type="checkbox" value="0"/>Dom</label>
            <label class="day-check"><input type="checkbox" value="1"/>Seg</label>
            <label class="day-check"><input type="checkbox" value="2"/>Ter</label>
            <label class="day-check"><input type="checkbox" value="3"/>Qua</label>
            <label class="day-check"><input type="checkbox" value="4"/>Qui</label>
            <label class="day-check"><input type="checkbox" value="5"/>Sex</label>
            <label class="day-check"><input type="checkbox" value="6"/>Sáb</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-g" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn-p" id="btn-save-agenda" onclick="doSaveAgenda()">
          <i class="bi bi-check-lg"></i>Salvar
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Parear TV (abre de Dispositivos) -->
<div class="modal fade" id="m-pair-device" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">
          <i class="bi bi-display"></i>Parear:
          <span id="pair-dev-name" style="color:var(--primary);margin-left:4px;"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="stopCamera()"></button>
      </div>
      <div class="modal-body">
        <p style="font-size:13px;color:var(--mut);margin-bottom:16px;">
          Digite o código exibido na TV ou escaneie o QR Code com a câmera.
        </p>
        <div style="display:flex;gap:8px;margin-bottom:16px;">
          <button id="tab-code" class="btn-p btn-sm" onclick="switchPairTab('code')" style="flex:1;justify-content:center;">
            <i class="bi bi-123"></i>Código
          </button>
          <button id="tab-cam" class="btn-g btn-sm" onclick="switchPairTab('cam')" style="flex:1;justify-content:center;">
            <i class="bi bi-qr-code-scan"></i>Câmera QR
          </button>
        </div>
        <div id="pair-tab-code">
          <div class="fg">
            <label>Código da TV (6 dígitos)</label>
            <input type="text" id="pair-input-code" placeholder="000000" maxlength="6"
              style="font-size:28px;letter-spacing:10px;text-align:center;font-family:monospace;padding:12px;"
              oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)"/>
          </div>
        </div>
        <div id="pair-tab-cam" class="hidden">
          <div style="position:relative;border-radius:10px;overflow:hidden;background:#000;aspect-ratio:4/3;">
            <video id="qr-video" playsinline style="width:100%;height:100%;object-fit:cover;"></video>
            <canvas id="qr-canvas" style="display:none;"></canvas>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
              <div style="width:55%;aspect-ratio:1;border:3px solid #4f8cff;border-radius:8px;box-shadow:0 0 0 9999px rgba(0,0,0,.45);"></div>
            </div>
          </div>
          <p id="qr-status" style="font-size:12px;color:var(--mut);text-align:center;margin-top:8px;">
            Aponte a câmera para o QR Code da TV
          </p>
        </div>
        <div id="pair-error" style="display:none;color:var(--red);font-size:13px;margin-top:10px;text-align:center;"></div>
      </div>
      <div class="modal-footer">
        <button class="btn-g" data-bs-dismiss="modal" onclick="stopCamera()">Cancelar</button>
        <button class="btn-p" onclick="doPairDeviceNew()">
          Parear <i class="bi bi-link-45deg"></i>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Vincular TV (seção Pareamento) -->
<div class="modal fade" id="m-pair" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title"><i class="bi bi-link-45deg"></i>Vincular TV</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p style="font-size:13px;color:var(--mut);margin-bottom:14px;">
          Código: <span class="pair-code-box" id="pair-code"></span>
        </p>
        <div class="fg"><label>Dispositivo</label><select id="pair-dev-select" onchange="togPairNew()"></select></div>
        <div id="pair-new-fields">
          <div class="fg"><label>Nome</label><input type="text" id="pair-dev-nome" placeholder="TV Recepção..."/></div>
          <div class="fg"><label>Local</label><input type="text" id="pair-dev-unit" placeholder="Andar, setor..."/></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-g" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn-p" onclick="doPairDevice()">
          Vincular <i class="bi bi-link-45deg"></i>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Toast container -->
<div class="toast-wrap" id="toasts"></div>

<script>
  // Guard de funções (módulo carrega assíncrono)
  [
    "doLogin","doLogout","nav","loadPairing","openAddDev","openEditDev","doSaveDev",
    "openDevInfo","delDev","openAddGrupo","openEditGrupo","doSaveGrupo","delGrupo",
    "showPlList","editPlaylist","openDupPl","openAddPl","deleteCurPl","openAddItem",
    "delItem","openAddAgenda","delAgenda","doSaveAgenda","quickAssign","openBroadcast",
    "doBroadcast","filterDevs","onDragStart","onDragOver","onDrop","previewItem",
    "doChangePass","doUpload","renderMedia","togDur","renderMpicker","togRepeat","doAddPl",
    "doDupPl","doAddItem","togPairNew","doPairDevice","copyT","openLogModal","delSched",
    "toggleSched","openPair","pickMedia","delPl","cancelDevPairing","openPairForDevice",
    "switchPairTab","startCamera","stopCamera","doPairDeviceNew",
  ].forEach(fn => {
    window[fn] = window[fn] || function() {
      const a = arguments;
      setTimeout(() => { if (typeof window[fn] === "function") window[fn].apply(null, a); }, 150);
    };
  });

  function toggleSb() {
    const sb  = document.getElementById("sb");
    const ov  = document.getElementById("sb-overlay");
    const main = document.getElementById("main");
    sb.classList.toggle("sb-hidden");
    if (window.innerWidth <= 992) {
      ov.classList.toggle("visible");
    } else {
      main.classList.toggle("full");
    }
  }
</script>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script type="module" src="js/admin.js?v=1781709886"></script>
</body>
</html>
