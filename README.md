# 📺 GVC Display

Sistema de Apresentação Digital Corporativa para Smart TVs.

**Stack:** PHP 8 · MySQL · JWT · Vanilla JS (ES Modules) · sem dependências externas

---

## 🗂️ Estrutura do projeto

```
gvc-display/
├── api/                    ← Backend PHP
│   ├── config.php          ← Carrega .env, PDO singleton
│   ├── helpers.php         ← Middleware, auth, sanitização, resolve_playlist
│   ├── jwt.php             ← JWT puro HMAC-SHA256
│   ├── auth/
│   │   ├── login.php       POST  → JWT
│   │   └── password.php    POST  → trocar senha
│   ├── devices/
│   │   ├── index.php       GET/POST/PUT/DELETE → CRUD TVs
│   │   ├── heartbeat.php   POST  → TV reporta status + recebe playlist
│   │   └── broadcast.php   POST  → envia playlist para N TVs
│   ├── groups/index.php    GET/POST/PUT/DELETE → CRUD grupos
│   ├── playlists/index.php GET/POST/PUT/DELETE → CRUD playlists
│   ├── items/index.php     POST/PUT/DELETE + ?action=reorder
│   ├── media/index.php     GET/POST/DELETE → upload e listagem
│   ├── schedules/index.php GET/POST/PUT/DELETE → agendamentos
│   ├── pairing/index.php   POST generate/check/link → pareamento
│   └── dashboard/index.php GET → stats + logs + status TVs
├── assets/
│   ├── icons/
│   └── logos/
├── css/
│   ├── main.css            ← Variáveis e reset compartilhado
│   ├── admin.css           ← Estilos do painel
│   └── player.css          ← Estilos do player TV
├── html/
│   └── player.html         ← Player fullscreen para Smart TVs
├── js/
│   ├── api.js              ← Cliente HTTP compartilhado (ES Module)
│   ├── utils.js            ← Helpers DOM, toast, formatação
│   ├── admin.js            ← SPA do painel (ES Module)
│   └── player.js           ← Lógica do player TV
├── uploads/
│   ├── .htaccess           ← Bloqueia execução de scripts
│   ├── images/
│   └── videos/
├── .env.example            ← Template de configuração
├── .gitignore
├── .htaccess               ← URL rewriting + segurança
├── database.sql            ← Schema completo (execute 1 vez)
├── index.html              ← Painel admin (SPA)
├── login.html              ← Página de login separada
└── README.md
```

---

## 🚀 Deploy na VPS

### 1. Banco de dados
```
hPanel → Banco de dados MySQL → Criar banco db_gvc_display
Abrir phpMyAdmin → executar database.sql
```

### 2. Configurar .env
```bash
cp .env.example .env
# Editar .env com credenciais do banco e JWT_SECRET
```

**Gerar JWT_SECRET:**
```php
php -r "echo bin2hex(random_bytes(32));"
```

### 3. Upload
- Enviar tudo para `public_html/gvc-display/`
- Permissões da pasta `uploads/` → **755**

### 4. Primeiro acesso
```
https://seudominio.com/gvc-display/login.html
admin@gvc.com / admin123   ← troque imediatamente!
```

---

## 🧪 Testar localmente

```bash
# XAMPP/WAMP/Laragon
# Copiar para htdocs/gvc-display/
# Criar banco e executar database.sql
# Configurar .env
# Acessar: http://localhost/gvc-display/

# Simular TVs (abrir em abas diferentes):
http://localhost/gvc-display/html/player.html?token=TOKEN_1
http://localhost/gvc-display/html/player.html?token=TOKEN_2
http://localhost/gvc-display/html/player.html            ← pareamento
http://localhost/gvc-display/html/player.html?debug=1    ← modo debug
```

---

## 🔄 Fluxo completo

```
1. Admin faz login → login.html → index.html
2. Admin cria grupos, TVs, faz upload, cria playlists
3. Admin atribui playlist à TV (direto ou broadcast ou agendamento)

4. TV abre html/player.html?token=TOKEN
5. Player faz POST /api/devices/heartbeat.php a cada 30s
6. Heartbeat retorna playlist ativa (agendamento > direta > padrão global)
7. Player detecta hash diferente → reconstrói slides automaticamente

8. TV sem token → exibe código de 6 dígitos → aguarda vinculação
9. Admin vincula código no painel → TV redireciona sozinha
```

---

## 📡 API Endpoints

| Método | Endpoint | Descrição |
|---|---|---|
| POST | `api/auth/login.php` | Login → JWT |
| POST | `api/auth/password.php` | Trocar senha |
| GET/POST/PUT/DELETE | `api/devices/index.php` | CRUD TVs |
| POST | `api/devices/heartbeat.php` | Heartbeat + playlist |
| POST | `api/devices/broadcast.php` | Broadcast playlist |
| GET/POST/PUT/DELETE | `api/groups/index.php` | CRUD grupos |
| GET/POST/PUT/DELETE | `api/playlists/index.php` | CRUD playlists |
| POST/PUT/DELETE | `api/items/index.php` | CRUD itens |
| POST | `api/items/index.php?action=reorder` | Reordenar |
| GET/POST/DELETE | `api/media/index.php` | Mídias + upload |
| GET/POST/PUT/DELETE | `api/schedules/index.php` | Agendamentos |
| GET/POST | `api/pairing/index.php?action=*` | Pareamento |
| GET | `api/dashboard/index.php` | Stats + logs |

---

## 🔐 Segurança

| Vetor | Proteção |
|---|---|
| SQL Injection | `PDO::prepare()` em todas as queries |
| Upload malicioso | MIME via `finfo`, nome aleatório, `.htaccess` |
| Timing attack login | `hash_equals()` + `password_verify()` |
| JWT forjado | HMAC-SHA256 com secret longo |
| Senhas | `password_hash()` bcrypt custo 12 |
| XSS | `strip_tags()` + `mb_substr()` no PHP; `esc()` no JS |
| PHP nos uploads | `php_flag engine off` + `Deny from all` |
| Directory listing | `Options -Indexes` |