<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = sint($_GET['id'] ?? 0);

// ── GET ───────────────────────────────────────────────────────
if ($method === 'GET') {
    auth_admin();
    $rows = db()->query(
        "SELECT d.id, d.name, d.location, d.status, d.last_ping, d.token,
                g.name AS group_name, p.name AS playlist_name
         FROM devices d
         LEFT JOIN `groups` g ON g.id = d.group_id
         LEFT JOIN playlists p ON p.id = d.playlist_id
         ORDER BY d.name"
    )->fetchAll();

    foreach ($rows as &$d) {
        $d['player_url'] = APP_URL . '/html/player.html?token=' . $d['token'];
        // Slug pode não existir em bancos antigos — usa null com segurança
        $slug = $d['slug'] ?? null;
        $d['tv_url'] = $slug ? (APP_URL . '/tv/' . $slug) : null;
    }
    json_ok($rows);
}

// ── POST ──────────────────────────────────────────────────────
if ($method === 'POST') {
    $a = auth_admin();
    $b = require_fields(['name']);

    $token = rand_token(32);

    // Gera slug único a partir do nome
    $slugBase = strtolower(preg_replace('/[^a-z0-9]+/i', '-', s($b['name'], 80)));
    $slugBase = trim($slugBase, '-') ?: 'tv';
    $slug = $slugBase;
    $i = 1;

    // Verifica se coluna slug existe antes de checar unicidade
    $hasSlug = false;
    try {
        db()->query("SELECT slug FROM devices LIMIT 1");
        $hasSlug = true;
    } catch (\Exception $e) {}

    if ($hasSlug) {
        while (db()->prepare("SELECT id FROM devices WHERE slug=?")->execute([$slug]) &&
               db()->prepare("SELECT id FROM devices WHERE slug=?")->execute([$slug])->fetchColumn()) {
            $slug = $slugBase . '-' . $i++;
        }
        db()->prepare("INSERT INTO devices (name,slug,location,group_id,playlist_id,token) VALUES (?,?,?,?,?,?)")
            ->execute([
                s($b['name'], 120),
                $slug,
                s($b['location'] ?? '', 180),
                $b['group_id']    ? sint($b['group_id'])    : null,
                $b['playlist_id'] ? sint($b['playlist_id']) : null,
                $token,
            ]);
    } else {
        // Banco antigo sem coluna slug
        db()->prepare("INSERT INTO devices (name,location,group_id,playlist_id,token) VALUES (?,?,?,?,?)")
            ->execute([
                s($b['name'], 120),
                s($b['location'] ?? '', 180),
                $b['group_id']    ? sint($b['group_id'])    : null,
                $b['playlist_id'] ? sint($b['playlist_id']) : null,
                $token,
            ]);
    }

    $new_id = (int)db()->lastInsertId();
    log_act((int)$a['sub'], 'create_device', 'device', $new_id, s($b['name']));

    $dev = db()->query("SELECT * FROM devices WHERE id=$new_id")->fetch();
    $dev['player_url'] = APP_URL . '/html/player.html?token=' . $dev['token'];
    $dev['tv_url']     = ($dev['slug'] ?? null) ? (APP_URL . '/tv/' . $dev['slug']) : null;
    json_ok($dev, 201);
}

// ── PUT ───────────────────────────────────────────────────────
if ($method === 'PUT') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 400);
    $b = body();

    // Reset de pareamento
    if (!empty($b['reset_token'])) {
        $newToken = rand_token(32);
        db()->prepare("UPDATE devices SET token=?, status='offline', last_ping=NULL WHERE id=?")->execute([$newToken, $id]);
        db()->prepare("DELETE FROM pairing_codes WHERE device_id=?")->execute([$id]);
        log_act(0, 'unpair_device', 'device', $id, 'token reset');
        json_ok(['id' => $id, 'token' => $newToken]);
    }

    $fields = [];
    $vals   = [];
    if (isset($b['name']))     { $fields[] = 'name=?';     $vals[] = s($b['name'], 120); }
    if (isset($b['location'])) { $fields[] = 'location=?'; $vals[] = s($b['location'], 180); }
    if (array_key_exists('group_id',    $b)) { $fields[] = 'group_id=?';    $vals[] = $b['group_id']    ? sint($b['group_id'])    : null; }
    if (array_key_exists('playlist_id', $b)) { $fields[] = 'playlist_id=?'; $vals[] = $b['playlist_id'] ? sint($b['playlist_id']) : null; }

    if (!$fields) json_err('Nada para atualizar', 400);
    $vals[] = $id;
    db()->prepare("UPDATE devices SET " . implode(',', $fields) . " WHERE id=?")->execute($vals);
    log_act(0, 'update_device', 'device', $id, '');
    json_ok(['id' => $id]);
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 400);
    db()->prepare("DELETE FROM devices WHERE id=?")->execute([$id]);
    log_act(0, 'delete_device', 'device', $id, '');
    json_ok(['deleted' => $id]);
}

json_err('Método não permitido', 405);
