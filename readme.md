# GVC Display

> Sistema corporativo de sinalização digital para gerenciamento centralizado de Smart TVs via navegador.

**GrupoGVC** · PHP 8.2 · MariaDB · Vanilla JS ES Modules · Bootstrap 5.3

---

## Sumário

- [Visão geral](#visão-geral)
- [Arquitetura do sistema](#arquitetura-do-sistema)
- [Estrutura de arquivos](#estrutura-de-arquivos)
- [Banco de dados](#banco-de-dados)
- [Fluxo de dados](#fluxo-de-dados)
- [API Reference](#api-reference)
- [Como rodar localmente](#como-rodar-localmente)
- [Deploy em produção (VPS)](#deploy-em-produção-vps)
- [Fluxo de pareamento de TVs](#fluxo-de-pareamento-de-tvs)
- [Player da TV](#player-da-tv)
- [Painel administrativo](#painel-administrativo)
- [Testando com múltiplas TVs](#testando-com-múltiplas-tvs)
- [Problemas comuns em Smart TVs](#problemas-comuns-em-smart-tvs)
- [Variáveis de ambiente](#variáveis-de-ambiente)
- [Credenciais padrão](#credenciais-padrão)

---

## Visão geral

O GVC Display permite que uma empresa cadastre Smart TVs distribuídas em diferentes ambientes e exiba apresentações (playlists de imagens, vídeos e páginas web) de forma centralizada. O administrador organiza o conteúdo num painel web; cada TV acessa uma URL simples pelo próprio navegador e exibe automaticamente o que foi configurado para ela.

```
Admin (navegador) ──── POST/PUT/GET ───→ API PHP (MariaDB)
                                              │
                                              │ polling a cada 15s
                                              ↓
Smart TV (navegador) ──── /tv/{slug} ──→ Player HTML (fullscreen)
```

---

## Arquitetura do sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                        GVC Display                              │
│                                                                 │
│  ┌──────────────────┐        ┌──────────────────────────────┐   │
│  │  Painel Admin    │        │       Player da TV           │   │
│  │  /               │        │       /tv/{slug}             │   │
│  │  login.html      │        │       player.html            │   │
│  │  index.html      │        │       player.js              │   │
│  │  admin.js        │        │       player.css             │   │
│  └────────┬─────────┘        └──────────────┬───────────────┘   │
│           │ JWT Bearer                       │ token query       │
│           │                                  │ polling 15s       │
│  ─────────┼──────────────────────────────────┼───────────────    │
│           ↓                                  ↓                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                   Backend PHP 8.2 MVC                   │    │
│  │                                                         │    │
│  │  index.php (Front Controller)                           │    │
│  │  ├── Router (PSR-style, sem Composer)                   │    │
│  │  ├── routes/api.php                                     │    │
│  │  └── Controllers/                                       │    │
│  │       ├── AuthController      → /api/auth/*             │    │
│  │       ├── DashboardController → /api/dashboard          │    │
│  │       ├── DeviceController    → /api/devices/*          │    │
│  │       ├── GroupController     → /api/groups/*           │    │
│  │       ├── PlaylistController  → /api/playlists/*        │    │
│  │       ├── ItemController      → /api/items/*            │    │
│  │       ├── MediaController     → /api/media/*            │    │
│  │       ├── PairingController   → /api/pairing/*          │    │
│  │       └── TvController        → /tv/:slug               │    │
│  └─────────────────────────────────────────────────────────┘    │
│                              │                                   │
│                              ↓                                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                MariaDB (porta 3307)                     │    │
│  │  users · groups · devices · playlists · playlist_items  │    │
│  │  media · schedules · pairing_codes · activity_logs      │    │
│  │  content_versions                                       │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
```

### Camadas

| Camada | Tecnologia | Responsabilidade |
|---|---|---|
| **Front Controller** | `index.php` | Carrega `.env`, define `BASE_PATH`, registra autoloader PSR-4, injeta headers CORS, despacha rotas |
| **Router** | `app/Core/Router.php` | Compila padrões `:param` em regex, despacha para controller/action |
| **Controllers** | `app/Controllers/*.php` | Recebe Request, valida JWT, chama Models, retorna JSON ou HTML |
| **Models** | `app/Models/*.php` | Wrappers PDO com queries SQL puras, sem ORM |
| **Views** | `resources/views/*.html` | Templates HTML estáticos; caminhos de assets resolvidos pelo PHP em runtime |
| **Admin JS** | `resources/js/admin.js` | SPA em Vanilla JS ES Modules — renderiza todas as telas do painel |
| **Player JS** | `resources/js/player.js` | Máquina de estados da TV: PAIRING → WAITING → PLAYING |
| **Banco** | MariaDB 10.x | Dados, triggers de versionamento, views, procedure `bump_content_version` |

---

## Estrutura de arquivos

```
gvc-display/
│
├── index.php                   ← Front Controller (raiz — NÃO em /public)
├── .env                        ← Variáveis de ambiente (não versionar)
├── .htaccess                   ← Rewrite rules do Apache
├── db_gvc_display.sql          ← Schema completo + seeds
│
├── app/
│   ├── Core/
│   │   ├── Controller.php      ← Base: auth() JWT, log()
│   │   ├── Database.php        ← Singleton PDO (MariaDB)
│   │   ├── JWT.php             ← HS256 encode/decode
│   │   ├── Model.php           ← CRUD base (find, create, update, delete)
│   │   ├── Request.php         ← input(), query(), file(), bearerToken()
│   │   └── Router.php          ← Registro e dispatch de rotas
│   │
│   ├── Controllers/
│   │   ├── AuthController.php       ← login, changePassword
│   │   ├── DashboardController.php  ← stats, últimos devices, logs
│   │   ├── DeviceController.php     ← CRUD TVs, heartbeat, broadcast, tvPlaylist
│   │   ├── GroupController.php      ← CRUD grupos
│   │   ├── ItemController.php       ← CRUD itens de playlist, reorder
│   │   ├── MediaController.php      ← upload, listagem, exclusão de mídia
│   │   ├── PairingController.php    ← tv-generate, tv-status, pair, unpair
│   │   ├── PlaylistController.php   ← CRUD playlists, duplicar
│   │   └── TvController.php         ← Serve player.html com vars JS injetadas
│   │
│   └── Models/
│       ├── Device.php          ← allWithRelations, findByToken, updateStatus
│       ├── Group.php
│       ├── Media.php
│       ├── PairingCode.php     ← generateForClient, consume, findPendingByClient
│       ├── Playlist.php        ← allWithCount, findWithItems (com hash)
│       └── PlaylistItem.php
│
├── config/
│   ├── app.php                 ← env, base_path
│   └── database.php            ← host, port, name, user, pass
│
├── resources/
│   ├── views/
│   │   ├── index.html          ← Painel administrativo (SPA)
│   │   ├── login.html          ← Tela de login
│   │   └── player.html         ← Tela da TV (fullscreen)
│   │
│   ├── css/
│   │   ├── main.css            ← Variáveis CSS, reset
│   │   ├── admin.css           ← Sidebar, tabelas, modais do painel
│   │   ├── login.css           ← Tela de login
│   │   └── player.css          ← Fullscreen player, tela de pareamento
│   │
│   └── js/
│       ├── admin.js            ← SPA do painel (todas as telas, ~54 KB)
│       ├── player.js           ← Máquina de estados da TV (~12 KB)
│       ├── api.js              ← Helpers fetch para o painel
│       ├── utils.js            ← Formatação, datas, helpers gerais
│       ├── login.js            ← Lógica inline da tela de login
│       ├── pwa.js              ← Registra o Service Worker kill-switch
│       └── sw.js               ← SW kill-switch (auto-desregistra)
│
├── routes/
│   └── api.php                 ← Todas as rotas da API centralizadas
│
├── uploads/
│   ├── images/                 ← Imagens enviadas (hash + extensão original)
│   └── videos/                 ← Vídeos enviados (hash + extensão original)
│
└── assets/
    ├── icons/                  ← icon-192.png, icon-512.png, icon.svg
    ├── logos/                  ← Logos GVC Display e GrupoGVC
    ├── manifest.admin.json     ← PWA manifest do painel
    └── manifest.tv.json        ← PWA manifest da TV
```

---

## Banco de dados

### Diagrama entidade-relacionamento

```
users
  id, name, email, password_hash, active

groups
  id, name, description

                        ┌─────────────┐
                        │  playlists  │
                        │  id, name   │
                        │  is_default │
                        └──────┬──────┘
                               │ 1:N
                        ┌──────┴──────────┐
                        │ playlist_items  │
                        │ id, type        │
                        │ url, duration   │
                        │ sort_order      │
                        │ media_id ───────┼──→ media
                        └─────────────────┘    (id, original_name,
                                                type, url, size)

devices
  id, name, location
  slug          → /tv/{slug} (gerado no pareamento)
  token         → autenticação da TV (64 chars hex)
  client_id     → fingerprint do navegador da TV
  group_id ─────────────────────────────────────→ groups
  playlist_id ──────────────────────────────────→ playlists
  last_ping     → atualizado no heartbeat (a cada 15s)
  status        → calculado: last_ping >= NOW()-30s

pairing_codes
  id, client_id, code (CHAR 6), expires_at

schedules
  id, playlist_id, target_type (all|group|device)
  target_id, starts_at, ends_at
  repeat_weekly, weekdays (JSON)

activity_logs
  id, user_id, action, detail, created_at

content_versions
  name, version, updated_at
  ← bumped por triggers em cada mudança estrutural
```

### Tabela `content_versions`

Cada alteração em devices, playlists, playlist_items, media ou schedules dispara automaticamente um trigger que incrementa a versão via `CALL bump_content_version(nome)`. O heartbeat da TV retorna o hash da playlist; se mudar, o player recarrega o conteúdo sem precisar recarregar a página.

### Views SQL

| View | Uso |
|---|---|
| `vw_devices_full` | Devices com nome do grupo e da playlist |
| `vw_playlist_items_full` | Itens com dados da mídia vinculada |

---

## Fluxo de dados

### 1. Autenticação do admin

```
POST /api/auth/login  { email, password }
     ↓
AuthController verifica password_hash com password_verify()
     ↓
Gera JWT HS256  { sub: user_id, name, email, exp: now+86400 }
     ↓
Frontend armazena token no localStorage
     ↓
Todas as chamadas admin enviam: Authorization: Bearer {token}
```

### 2. Ciclo completo de uma TV

```
TV abre /tv/{slug}  (ou /tv/ sem slug)
        ↓
TvController::show()
  ├── Tem slug? → busca device por slug → seta cookie gvc_tv_token
  ├── Tem cookie gvc_tv_token? → busca device por token
  └── Nenhum → retorna device = null
        ↓
renderPlayer($device)
  ├── Injeta vars JS: __DEVICE_TOKEN__, __CLIENT_ID__, __PAIRED__, __BASE_URL__
  └── Serve player.html com os caminhos de assets resolvidos
        ↓
player.js carrega no navegador da TV
  ├── IS_PAIRED = true  → startPlayer()
  └── IS_PAIRED = false → startPairing()
```

### 3. Fluxo de pareamento

```
TV (não pareada)                          Admin (painel)
─────────────────                         ──────────────
POST /api/pairing/tv-generate             Acessa Painel → aba Dispositivos
  { client_id }                           Clica "Parear" no device criado
  ↓                                       Digita o código de 6 dígitos
Exibe código XXXXXX na tela              POST /api/pairing/pair
  ↓                                         { code, device_id }
GET /api/pairing/tv-status                  ↓
  ?client_id=...  (a cada 3s)           Servidor:
  ↓                                       - Valida código
{ paired: false, code, expires_at }       - Gera slug + token únicos
  ↓  (aguardando...)                      - UPDATE devices SET slug,token,client_id
                                           ↓
                                        { success, slug, player_url }

TV (próximo poll):
GET /api/pairing/tv-status
  ↓
{ paired: true, token, slug, name }
  ↓
Seta cookie gvc_tv_token = token
Recarrega: window.location.href = BASE_URL + '/tv/' + slug
  ↓
TvController lê cookie → renderiza player autenticado
```

### 4. Loop do player (TV pareada)

```
startPlayer()
  ↓
fetchPlaylist()  → GET /api/devices/tv-playlist?token=...
  ├── 204 No Content → setPhase('waiting', '📋', 'Nenhuma playlist atribuída')
  └── 200 { items: [...] } → loadSlide(0)
        ↓
loadSlide(idx)
  ├── type = 'image' → renderImage()  → setTimeout(nextSlide, duration*1000)
  ├── type = 'video' → renderVideo()  → video.onended = nextSlide
  └── type = 'page'  → renderPage()   → setTimeout(nextSlide, duration*1000)
        ↓
nextSlide() → idx++ → loadSlide(idx % items.length)

Paralelamente, a cada 15s:
POST /api/devices/heartbeat  { token }
  ↓
{ playlist_id, playlist_hash }
  ├── hash igual → continua normalmente
  └── hash diferente → fetchPlaylist() → reinicia do slide 0
```

---

## API Reference

Todas as rotas da API retornam JSON no formato:

```json
{ "success": true, "data": { ... } }
// ou em erro:
{ "success": false, "message": "Descrição do erro" }
```

Rotas com 🔒 exigem `Authorization: Bearer {jwt}` no header.

### Auth

| Método | Rota | Corpo | Descrição |
|--------|------|-------|-----------|
| POST | `/api/auth/login` | `{ email, password }` | Retorna JWT |
| POST | `/api/auth/password` 🔒 | `{ current_password, new_password }` | Troca senha |

### Dashboard

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/dashboard` 🔒 | Stats, últimos 20 devices, últimos 20 logs |

### Devices (TVs)

| Método | Rota | Corpo/Params | Descrição |
|--------|------|-------------|-----------|
| GET | `/api/devices` 🔒 | — | Lista todas as TVs com status online/offline |
| POST | `/api/devices` 🔒 | `{ name, location?, group_id?, playlist_id? }` | Cria TV sem parear |
| GET | `/api/devices/:id` 🔒 | — | Detalhe de uma TV |
| PUT | `/api/devices/:id` 🔒 | `{ name, location, group_id, playlist_id }` | Atualiza TV |
| DELETE | `/api/devices/:id` 🔒 | — | Remove TV |
| POST | `/api/devices/heartbeat` | `{ token }` | TV envia sinal de vida; retorna hash da playlist |
| POST | `/api/devices/broadcast` 🔒 | `{ playlist_id, target }` | Envia playlist para `all`, `group:N` ou `device:N` |
| GET | `/api/devices/tv-playlist` | `?token=...` | TV busca sua playlist completa |

> **Status online/offline** é calculado em tempo real pela query SQL:
> `CASE WHEN last_ping >= DATE_SUB(NOW(), INTERVAL 30 SECOND) THEN 'online' ELSE 'offline' END`
> Não usa a coluna `status` (stale).

### Groups

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/groups` 🔒 | Lista grupos |
| POST | `/api/groups` 🔒 | Cria grupo |
| PUT | `/api/groups/:id` 🔒 | Atualiza grupo |
| DELETE | `/api/groups/:id` 🔒 | Remove grupo |

### Playlists

| Método | Rota | Corpo | Descrição |
|--------|------|-------|-----------|
| GET | `/api/playlists` 🔒 | — | Lista com contagem de itens |
| POST | `/api/playlists` 🔒 | `{ name, is_default?, copy_from? }` | Cria (ou duplica com `copy_from`) |
| GET | `/api/playlists/:id` 🔒 | — | Playlist com todos os itens |
| PUT | `/api/playlists/:id` 🔒 | `{ name, is_default }` | Atualiza |
| DELETE | `/api/playlists/:id` 🔒 | — | Remove (cascade nos itens) |

### Playlist Items

| Método | Rota | Corpo | Descrição |
|--------|------|-------|-----------|
| POST | `/api/items` 🔒 | `{ playlist_id, type, url, duration, media_id?, sort_order }` | Adiciona item |
| POST | `/api/items/reorder` 🔒 | `{ items: [{id, sort_order}] }` | Reordena |
| PUT | `/api/items/:id` 🔒 | `{ duration, sort_order }` | Atualiza item |
| DELETE | `/api/items/:id` 🔒 | — | Remove item |

### Media

| Método | Rota | Corpo | Descrição |
|--------|------|-------|-----------|
| GET | `/api/media` 🔒 | — | Lista biblioteca de mídia |
| POST | `/api/media` 🔒 | `multipart: file` | Upload (imagens: jpg/png/gif/webp; vídeos: mp4/webm) |
| DELETE | `/api/media/:id` 🔒 | — | Remove arquivo e registro |

> Arquivos salvos em `/uploads/images/` ou `/uploads/videos/` com nome `{hex32}.{ext}`. Limite: 100 MB.

### Pairing

| Método | Rota | Corpo/Params | Descrição |
|--------|------|-------------|-----------|
| GET | `/api/pairing` 🔒 | — | Lista códigos pendentes |
| POST | `/api/pairing/tv-generate` | `{ client_id }` | TV gera código de 6 dígitos (30 min de validade) |
| GET | `/api/pairing/tv-status` | `?client_id=...` | TV consulta se foi pareada |
| POST | `/api/pairing/pair` 🔒 | `{ code, device_id }` | Admin vincula código a uma TV |
| POST | `/api/pairing/unpair` 🔒 | `{ device_id }` | Admin desvincula TV (volta à tela de pareamento) |

---

## Como rodar localmente

### Pré-requisitos

- **XAMPP** com PHP 8.2+ e MariaDB na porta 3307
- MySQL Workbench (opcional, mas recomendado)

### 1. Clonar o repositório

```bash
# No terminal do Windows (Git Bash ou PowerShell)
cd C:\xampp\htdocs
git clone https://github.com/GrupoGVC/gvc-display.git
cd gvc-display
git checkout estruturaMVC
```

### 2. Configurar o ambiente

```bash
# Copie e edite o arquivo de ambiente
copy .env.example .env   # Windows
# cp .env.example .env   # Linux/Mac
```

Edite o `.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=3307
DB_NAME=db_gvc_display
DB_USER=root
DB_PASS=

JWT_SECRET=troque-por-uma-string-aleatoria-longa
JWT_TTL=86400

APP_BASE_PATH=/gvc-display
```

### 3. Criar o banco de dados

Abra o **MySQL Workbench** ou o phpMyAdmin, conecte na porta **3307** e execute:

```sql
-- Importar o schema completo
SOURCE C:/xampp/htdocs/gvc-display/db_gvc_display.sql;
```

Ou via linha de comando:

```bash
"C:\xampp\mysql\bin\mysql.exe" -h 127.0.0.1 -P 3307 -u root < db_gvc_display.sql
```

O script cria o banco, todas as tabelas, triggers, views, a procedure `bump_content_version` e o usuário admin padrão.

### 4. Verificar o .htaccess

O arquivo `.htaccess` já está configurado para `RewriteBase /gvc-display/`. Se o projeto estiver em outra pasta, ajuste esta linha.

### 5. Iniciar o XAMPP

- Apache: porta 80 (ou 8080)
- MySQL: porta **3307** (não 3306)

### 6. Acessar

| URL | O que abre |
|-----|-----------|
| `http://localhost/gvc-display/` | Painel administrativo |
| `http://localhost/gvc-display/login` | Tela de login |
| `http://localhost/gvc-display/tv` | Player da TV (tela de pareamento) |
| `http://localhost/gvc-display/tv/{slug}` | Player de uma TV específica |

---

## Deploy em produção (VPS)

O servidor de produção usa **Nginx** com PHP-FPM e o domínio `display.drc-gvc.tech`.

### 1. Configuração Nginx

```nginx
server {
    listen 80;
    server_name display.drc-gvc.tech;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name display.drc-gvc.tech;

    root /var/www/gvc-display;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/display.drc-gvc.tech/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/display.drc-gvc.tech/privkey.pem;

    # Assets estáticos servidos diretamente
    location ~* ^/(uploads|assets|resources)/ {
        expires 7d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Tudo mais vai pro front controller
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 2. Ajustar .env em produção

```env
APP_BASE_PATH=
# Em produção na raiz do domínio, APP_BASE_PATH fica vazio
# O auto-detect do index.php cuida do caso

DB_PORT=3306
# MariaDB em produção normalmente usa 3306
```

### 3. Deploy via Git

```bash
# No servidor (VPS)
cd /var/www/gvc-display
git fetch origin
git reset --hard origin/main

# Permissões dos uploads
chmod -R 775 uploads/
chown -R www-data:www-data uploads/
```

### 4. Importar o banco em produção

```bash
mysql -u usuario -p db_gvc_display < db_gvc_display.sql
```

---

## Fluxo de pareamento de TVs

O pareamento funciona em 5 passos simples:

```
Passo 1 — Admin cria a TV no painel
  Painel → Dispositivos → "+ Novo Dispositivo"
  Preenche nome, local, grupo e playlist (opcional)
  A TV é criada SEM slug/token (não pareada)

Passo 2 — TV abre o sistema
  Smart TV abre: http://display.drc-gvc.tech/tv
  O player.js detecta que não está pareado (IS_PAIRED = false)
  Chama POST /api/pairing/tv-generate com o client_id (cookie único)
  Exibe na tela um código de 6 dígitos: ex. "847 291"
  O código expira em 30 minutos

Passo 3 — Admin digita o código
  Painel → Dispositivos → botão "Parear" na TV desejada
  Modal abre pedindo o código de 6 dígitos
  Admin digita o código exibido na TV
  POST /api/pairing/pair { code: "847291", device_id: 5 }

Passo 4 — Servidor vincula
  Valida o código e pega o client_id associado
  Gera slug único baseado no nome da TV (ex: "tv-recepcao")
  Gera token hex de 64 chars
  UPDATE devices SET slug, token, client_id WHERE id = 5

Passo 5 — TV detecta e atualiza
  A cada 3s, player.js chama GET /api/pairing/tv-status?client_id=...
  Servidor responde: { paired: true, token, slug, name }
  TV salva o token no cookie gvc_tv_token (validade 10 anos)
  Redireciona para /tv/tv-recepcao
  TvController injeta token nas vars JS
  Player inicia em modo PLAYING
```

Para **desparear** uma TV: Painel → Dispositivos → botão "Pareado" (fica vermelho no hover) → confirma → TV volta à tela de código no próximo heartbeat (resposta 401).

---

## Player da TV

### Máquina de estados (`player.js`)

```
         startPairing()              startPlayer()
              ↓                           ↓
┌─────────────────────┐      ┌────────────────────────┐
│      PAIRING        │      │        PLAYING         │
│                     │      │                        │
│  Exibe código 6d    │      │  Alterna slides:       │
│  Polling 3s         │─────→│  • image → object-fit  │
│  /pairing/tv-status │      │  • video → autoplay    │
│  Countdown 30min    │      │  • page  → iframe      │
└─────────────────────┘      └────────────┬───────────┘
                                          │
                              Heartbeat 15s
                              /devices/heartbeat
                                          │
                              hash mudou? → fetchPlaylist()
                              token inválido? → startPairing()
                              sem playlist? ↓
                                          │
                             ┌────────────┴───────────┐
                             │        WAITING         │
                             │  "Nenhuma apresentação"│
                             │  Polling 30s           │
                             └────────────────────────┘
```

### Variáveis injetadas pelo PHP

O `TvController` injeta um bloco `<script>` antes do `</head>`:

```javascript
window.__DEVICE_TOKEN__ = "abc123...";  // null se não pareada
window.__DEVICE_SLUG__  = "tv-recepcao";
window.__CLIENT_ID__    = "a1b2c3...";  // cookie persistente
window.__BASE_URL__     = "https://display.drc-gvc.tech";
window.__PAIRED__       = true;
```

### Reconexão automática

- **Internet cai:** o `fetch()` do heartbeat falha silenciosamente; o slide atual continua exibindo
- **Internet volta:** o próximo heartbeat (15s) funciona normalmente
- **Token invalidado (despareamento):** heartbeat retorna 401 → `startPairing()` automaticamente
- **Playlist atualizada:** hash muda → `fetchPlaylist()` → reinicia do slide 0

### Compatibilidade com Smart TVs

O player foi escrito para máxima compatibilidade:

- **Sem frameworks**, sem transpilação (ES2020 nativo)
- **Autoplay de vídeo:** usa `muted + playsinline` (obrigatório em navegadores modernos)
- **Fallback de vídeo:** se `play()` falhar, avança para o próximo slide em 3s
- **Fallback de imagem:** se a imagem falhar, avança em 2s
- **Sem Service Worker no player:** `/uploads/` é servido via `fetch()` direto para evitar `ERR_CACHE_OPERATION_NOT_SUPPORTED` com arquivos MP4

---

## Painel administrativo

O painel é uma **SPA (Single Page Application)** em Vanilla JS. Toda a lógica está em `resources/js/admin.js` (~54 KB).

### Telas disponíveis

| Seção | Funcionalidades |
|---|---|
| **Dashboard** | Cards com totais (TVs, playlists, mídias), lista de dispositivos com status online/offline em tempo real, log de atividades recentes |
| **Dispositivos** | Cadastro de TVs (nome, local, grupo, playlist), ação de parear/desparear com modal de código, botão de broadcast por TV |
| **Grupos** | Organização de TVs por setor/andar/ambiente; broadcast para o grupo inteiro |
| **Mídias** | Upload de imagens (jpg/png/gif/webp) e vídeos (mp4/webm); visualização em grid; exclusão |
| **Playlists** | Criar, editar, duplicar, definir como padrão; editor de itens com duração individual; reordenação por drag and drop |
| **Broadcast** | Enviar playlist para todas as TVs, um grupo ou uma TV específica |

### Autenticação

O token JWT é armazenado em `localStorage` com chave `gvc_token`. Toda requisição ao painel adiciona automaticamente o header `Authorization: Bearer {token}`. Expiração padrão: 24 horas (`JWT_TTL=86400`).

### Sidebar

Sidebar com collapse/expand — largura expandida ~220px, colapsada 76px. Tooltips CSS puros via `data-tip` (sem JavaScript adicional). Em mobile, a sidebar colapsa automaticamente.

---

## Testando com múltiplas TVs

Você pode simular várias TVs em abas do navegador usando o modo anônimo ou perfis diferentes:

### Método 1 — Abas anônimas

Cada aba anônima tem cookies isolados, simulando TVs independentes:

```
Aba normal     → Painel admin  (http://localhost/gvc-display/)
Aba anônima 1  → TV 1          (http://localhost/gvc-display/tv)
Aba anônima 2  → TV 2          (http://localhost/gvc-display/tv)
```

### Método 2 — Perfis do Chrome

Crie perfis separados no Chrome (cada perfil tem cookies próprios).

### Método 3 — Slug direto

Se uma TV já foi pareada, acesse diretamente pelo slug sem precisar do cookie:

```
http://localhost/gvc-display/tv/tv-recepcao
http://localhost/gvc-display/tv/sala-reunioes
http://localhost/gvc-display/tv/corredor-2
```

### Passo a passo para testar

```bash
# 1. Acesse o painel e faça login
http://localhost/gvc-display/login
# admin@gvc.com / admin123

# 2. Crie 2 dispositivos:
#    → "TV Recepção" (Recepção, Térreo)
#    → "TV Sala de Reuniões" (Sala 201, 2º Andar)

# 3. Faça upload de 2 ou 3 imagens em Mídias

# 4. Crie uma playlist "Corporativo" com as imagens, duração 5s cada

# 5. Abra uma aba anônima em /tv — anote o código exibido

# 6. No painel, clique "Parear" na "TV Recepção" e digita o código

# 7. A aba anônima deve redirecionar e começar a apresentação

# 8. Troque a playlist pelo broadcast e veja a TV atualizar em até 15s
```

---

## Problemas comuns em Smart TVs

### Autoplay de vídeo bloqueado

**Problema:** Samsung Tizen, LG WebOS e outros bloqueiam autoplay com som.

**Solução:** O player usa `muted` em todos os vídeos:
```html
<video autoplay muted playsinline>
```

Se o autoplay ainda falhar, o player captura o erro e avança para o próximo slide automaticamente.

### Vídeos não carregam (HTTP Range Requests)

**Problema:** Alguns navegadores de Smart TV exigem suporte a `Range` para vídeos. Apache e Nginx suportam por padrão, mas é necessário verificar.

**Diagnóstico:**
```bash
curl -I -H "Range: bytes=0-1023" http://display.drc-gvc.tech/uploads/videos/arquivo.mp4
# Deve retornar: HTTP/1.1 206 Partial Content
```

### Service Worker interferindo

**Problema:** SW de versões antigas intercepta requisições e retorna conteúdo em cache.

**Solução:** O projeto usa um SW kill-switch (`sw.js`) que se auto-desregistra e limpa todos os caches ao ativar. O login não registra SW (`$injectSW = false`).

### Trailing slash no Apache

**Problema:** `mod_dir` adiciona `/` no final de URLs sem extensão, quebrando as rotas da API.

**Solução já aplicada:**
```apache
# .htaccess
DirectorySlash Off
```
```php
// Router.php
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $uri = rtrim($uri, '/');
}
```

### Tizen (Samsung) — cookies de terceiros

**Problema:** TVs Samsung podem bloquear cookies de domínios diferentes.

**Solução:** O sistema usa cookies do próprio domínio. Certifique-se de que o `APP_BASE_PATH` está correto no `.env` e que o cookie é setado com `path=/` e `samesite=Lax`.

### POST vira GET no XAMPP

**Problema:** Quando `index.php` usa `require 'public/index.php'` para delegar, o Apache converte o método POST em GET no redirect interno.

**Solução já aplicada:** O front controller completo fica em `index.php` na raiz — não delega para `public/index.php`.

### MariaDB — fuso horário

**Problema:** Se PHP e MySQL estão em fusos diferentes, comparações com `NOW()` ficam erradas (status online/offline sempre errado, JWT expira antes da hora).

**Solução já aplicada:**
```php
// index.php
date_default_timezone_set('UTC');
```
O banco usa `CURRENT_TIMESTAMP` que segue o timezone do servidor MariaDB. Em produção, alinhe ambos para UTC.

---

## Variáveis de ambiente

| Variável | Padrão | Descrição |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Host do MariaDB |
| `DB_PORT` | `3307` | Porta (XAMPP usa 3307; produção tipicamente 3306) |
| `DB_NAME` | `db_gvc_display` | Nome do banco |
| `DB_USER` | `root` | Usuário do banco |
| `DB_PASS` | _(vazio)_ | Senha do banco |
| `JWT_SECRET` | _(obrigatório)_ | Segredo HS256 — use string aleatória com 64+ chars |
| `JWT_TTL` | `86400` | Expiração do token em segundos (padrão: 24h) |
| `APP_BASE_PATH` | `/gvc-display` | Prefixo da URL. Deixar vazio se o projeto estiver na raiz do domínio |

---

## Credenciais padrão

| Campo | Valor |
|---|---|
| **E-mail** | `admin@gvc.com` |
| **Senha** | `admin123` |

> ⚠️ Troque a senha imediatamente após o primeiro acesso em produção: Painel → ícone de usuário → "Alterar senha".

---

## Segurança

- **Painel:** protegido por JWT HS256 em todas as rotas `/api/*` (exceto `/api/auth/login` e rotas da TV)
- **TVs:** autenticadas por token único de 64 chars hexadecimais armazenado no cookie `httponly=false` (necessário para o JS do player ler) e `samesite=Lax`
- **Uploads:** MIME type validado no PHP; extensão derivada do nome original; arquivos salvos com nome hash aleatório
- **SQL Injection:** todas as queries usam PDO com prepared statements
- **Rota da TV:** `/tv/:slug` não expõe nenhuma função administrativa; o token só permite operações de leitura (heartbeat, tv-playlist)
- **Logs:** todas as ações administrativas são gravadas em `activity_logs` com user_id, action e detalhe

---

## Tecnologias utilizadas

| Tecnologia | Versão | Uso |
|---|---|---|
| PHP | 8.2 | Backend MVC, JWT, PDO |
| MariaDB | 10.x | Banco de dados principal |
| Apache | 2.4 | Servidor web local (XAMPP) |
| Nginx | 1.24 | Servidor web em produção |
| Bootstrap | 5.3 | Grid e componentes do painel |
| Bootstrap Icons | 1.11 | Ícones do painel |
| Vanilla JS | ES2020 | Admin SPA + Player TV |
| Google Fonts | — | Inter (player), Lato (login) |
| QR Server API | — | Geração de QR Code no pareamento |

---

## Licença

Projeto proprietário — GrupoGVC. Todos os direitos reservados.