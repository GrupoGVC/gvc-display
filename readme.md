# GVC Display

Sistema corporativo de sinalização digital. O administrador organiza apresentações num painel web e cada Smart TV exibe automaticamente o conteúdo configurado para ela, sem nenhuma interação manual.

**Stack:** PHP 8.2 · MariaDB · Vanilla JS · Bootstrap 5.3

---

## Como funciona

1. O admin acessa o painel pelo navegador, faz upload de imagens/vídeos e monta playlists.
2. O admin cadastra as TVs no painel (nome, local, grupo).
3. Cada Smart TV abre o endereço do sistema pelo próprio navegador e exibe um **código de 6 dígitos** na tela.
4. O admin digita esse código no painel para vincular a TV. Pronto.
5. A partir daí, a TV exibe automaticamente a playlist atribuída a ela. Se o admin trocar o conteúdo, a TV atualiza sozinha em até 15 segundos.

---

## Pré-requisitos

- XAMPP com **PHP 8.2+** e **MariaDB na porta 3307**
- Navegador

---

## Instalação local

**1. Clonar o projeto**

```bash
cd C:\xampp\htdocs
git clone https://github.com/GrupoGVC/gvc-display.git
cd gvc-display
git checkout estruturaMVC
```

**2. Criar o arquivo `.env`** (copiar o exemplo e editar)

```bash
copy .env.example .env
```

Conteúdo mínimo do `.env`:

```
DB_HOST=127.0.0.1
DB_PORT=3307
DB_NAME=db_gvc_display
DB_USER=root
DB_PASS=

JWT_SECRET=coloque-aqui-uma-string-aleatoria-longa
APP_BASE_PATH=/gvc-display
```

**3. Criar o banco de dados**

Abra o MySQL Workbench (conecte na porta **3307**) e execute o arquivo `db_gvc_display.sql`. Ou via terminal:

```bash
"C:\xampp\mysql\bin\mysql.exe" -h 127.0.0.1 -P 3307 -u root < db_gvc_display.sql
```

**4. Iniciar o XAMPP** (Apache + MySQL) e acessar:

| Endereço                             | O que abre              |
| ------------------------------------ | ----------------------- |
| `http://localhost/gvc-display/`      | Painel administrativo   |
| `http://localhost/gvc-display/login` | Tela de login           |
| `http://localhost/gvc-display/tv`    | Tela da TV (modo kiosk) |

---

## Primeiro acesso

**Login padrão:**

- E-mail: `admin@gvc.com`
- Senha: `admin123`

> Troque a senha após o primeiro acesso: ícone de usuário → "Alterar senha".

---

## Como cadastrar e parear uma TV

1. No painel, vá em **Dispositivos → Novo Dispositivo**. Preencha nome e local.
2. Na Smart TV, abra `http://display.drc-gvc.tech/tv`. Um código de 6 dígitos aparece na tela.
3. No painel, clique em **Parear** no dispositivo criado e digite o código.
4. A TV atualiza automaticamente e começa a exibir a playlist atribuída.

Para **desparear**, clique no botão verde "Pareado" (fica vermelho no hover) e confirme.

---

## Como criar e enviar uma apresentação

1. Vá em **Mídias** e faça upload das imagens e vídeos.
2. Vá em **Playlists → Nova Playlist**, adicione os itens e defina a duração de cada um.
3. Vá em **Dispositivos**, edite uma TV e selecione a playlist desejada.  
   Ou use o botão **Broadcast** para enviar a mesma playlist para todas as TVs, um grupo inteiro ou uma TV específica.

---

## Testando com várias TVs ao mesmo tempo

Abra uma **aba anônima** do navegador para cada TV simulada (cada aba anônima tem cookies isolados, como se fosse um dispositivo diferente).

```
Aba normal      → Painel admin
Aba anônima 1   → http://localhost/gvc-display/tv  (TV 1)
Aba anônima 2   → http://localhost/gvc-display/tv  (TV 2)
```

---

## Deploy em produção (Nginx + VPS)

**Configuração Nginx** (`/etc/nginx/sites-available/gvc-display`):

```nginx
server {
    listen 443 ssl http2;
    server_name display.drc-gvc.tech;
    root /var/www/gvc-display;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/display.drc-gvc.tech/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/display.drc-gvc.tech/privkey.pem;

    location ~* ^/(uploads|assets|resources)/ {
        expires 7d;
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

server {
    listen 80;
    server_name display.drc-gvc.tech;
    return 301 https://$host$request_uri;
}
```

**Ajuste o `.env` em produção:**

```
APP_BASE_PATH=
DB_PORT=3306
```

**Atualizar via Git:**

```bash
cd /var/www/gvc-display
git fetch origin
git reset --hard origin/main
chmod -R 775 uploads/
chown -R www-data:www-data uploads/
```

---

## Estrutura de pastas

```
gvc-display/
├── index.php                  ← Ponto de entrada (front controller)
├── .env                       ← Configurações locais (não versionar)
├── .htaccess                  ← Regras de rewrite do Apache
├── db_gvc_display.sql         ← Schema do banco + dados iniciais
│
├── app/
│   ├── Core/                  ← Router, Database, JWT, Request, Response
│   ├── Controllers/           ← Lógica de cada funcionalidade
│   └── Models/                ← Acesso ao banco de dados
│
├── resources/
│   ├── views/
│   │   ├── index.html         ← Painel administrativo (SPA)
│   │   ├── login.html         ← Tela de login
│   │   └── player.html        ← Tela da TV (fullscreen)
│   ├── css/                   ← Estilos do painel, login e player
│   └── js/
│       ├── admin.js           ← Toda a lógica do painel
│       └── player.js          ← Toda a lógica da TV
│
├── routes/
│   └── api.php                ← Todas as rotas da API
│
└── uploads/
    ├── images/                ← Imagens enviadas pelo admin
    └── videos/                ← Vídeos enviados pelo admin
```

---

## Banco de dados (tabelas principais)

| Tabela             | O que guarda                                                              |
| ------------------ | ------------------------------------------------------------------------- |
| `users`            | Administradores do sistema                                                |
| `groups`           | Grupos de TVs (por setor, andar etc.)                                     |
| `devices`          | Cada Smart TV cadastrada                                                  |
| `playlists`        | Apresentações criadas pelo admin                                          |
| `playlist_items`   | Cada imagem/vídeo/página dentro de uma playlist                           |
| `media`            | Biblioteca de arquivos enviados                                           |
| `pairing_codes`    | Códigos temporários de pareamento (6 dígitos, válidos 30 min)             |
| `schedules`        | Agendamentos de playlists por data/horário                                |
| `activity_logs`    | Histórico de ações dos administradores                                    |
| `content_versions` | Número de versão do conteúdo — TVs comparam para saber se devem atualizar |

---

## Rotas da API

Rotas com 🔒 exigem o header `Authorization: Bearer {token}`.

**Autenticação**

- `POST /api/auth/login` — Faz login, retorna token JWT
- `POST /api/auth/password` 🔒 — Troca senha

**Dispositivos (TVs)**

- `GET /api/devices` 🔒 — Lista todas as TVs com status online/offline
- `POST /api/devices` 🔒 — Cadastra nova TV
- `PUT /api/devices/:id` 🔒 — Edita TV
- `DELETE /api/devices/:id` 🔒 — Remove TV
- `POST /api/devices/heartbeat` — TV envia sinal de vida a cada 15s
- `GET /api/devices/tv-playlist` — TV busca sua playlist atual
- `POST /api/devices/broadcast` 🔒 — Envia playlist para todas/grupo/TV específica

**Playlists**

- `GET /api/playlists` 🔒 — Lista playlists
- `POST /api/playlists` 🔒 — Cria playlist (suporta `copy_from` para duplicar)
- `GET /api/playlists/:id` 🔒 — Detalhes com todos os itens
- `PUT /api/playlists/:id` 🔒 — Edita playlist
- `DELETE /api/playlists/:id` 🔒 — Remove playlist

**Itens de playlist**

- `POST /api/items` 🔒 — Adiciona item
- `POST /api/items/reorder` 🔒 — Reordena itens
- `PUT /api/items/:id` 🔒 — Edita duração/ordem
- `DELETE /api/items/:id` 🔒 — Remove item

**Mídias**

- `GET /api/media` 🔒 — Lista biblioteca
- `POST /api/media` 🔒 — Upload de arquivo (máx 100 MB)
- `DELETE /api/media/:id` 🔒 — Remove arquivo

**Pareamento**

- `POST /api/pairing/tv-generate` — TV gera código de pareamento
- `GET /api/pairing/tv-status` — TV consulta se já foi pareada
- `POST /api/pairing/pair` 🔒 — Admin vincula código a uma TV
- `POST /api/pairing/unpair` 🔒 — Admin desvincula TV

---

## Problemas conhecidos e soluções

**Vídeo não inicia na TV**  
Smart TVs exigem `muted` para autoplay. O player já usa `<video autoplay muted playsinline>`. Se ainda falhar, avança automaticamente para o próximo slide.

**Status online/offline sempre errado**  
Verifique se PHP e MariaDB estão no mesmo fuso horário. O projeto usa UTC — confirme com `date_default_timezone_set('UTC')` no `index.php`.

**POST vira GET no XAMPP**  
Acontece quando `index.php` delega para `public/index.php`. A solução já está aplicada: o front controller completo fica na raiz.

**Tela de login em loop infinito**  
Service Worker antigo em cache. O `sw.js` do projeto é um kill-switch que se auto-desregistra. Se o loop persistir, abra o DevTools → Application → Service Workers → "Unregister" manual.

**Barra no final da URL quebrando a API**  
O Apache `mod_dir` adiciona `/` em URLs sem extensão. Já resolvido com `DirectorySlash Off` no `.htaccess` e `rtrim` no Router.

---

## Licença

Projeto proprietário - GrupoGVC. Todos os direitos reservados.
