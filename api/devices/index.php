<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = sint($_GET['id'] ?? 0);

// ── GET ───────────────────────────────────────────────────────
if ($method === 'GET') {
    auth();
    $rows = db()->query(
        "SELECT d.*, g.name AS group_name, p.name AS playlist_name
         FROM devices d
         LEFT JOIN `groups` g    ON g.id = d.group_id
         LEFT JOIN playlists p ON p.id = d.playlist_id
         ORDER BY d.name"
    )->fetchAll();

    foreach ($rows as &$d) {
        $d['id']          = (int)$d['id'];
        $d['group_id']    = $d['group_id']    ? (int)$d['group_id']    : null;
        $d['playlist_id'] = $d['playlist_id'] ? (int)$d['playlist_id'] : null;
        if ($d['last_ping']) {
            $d['status'] = (time() - strtotime($d['last_ping'])) < 120 ? 'online' : 'offline';
        }
        $d['player_url'] = APP_URL . '/html/player.html?token=' . $d['token'];
        $d['tv_url']     = $d['slug'] ? (APP_URL . '/tv/' . $d['slug']) : null;
    }
    json_ok($rows);
}

// ── POST ──────────────────────────────────────────────────────
if ($method === 'POST') {
    $a = auth_admin();
    $b = require_fields(['name']);

    $token = rand_token(32);
    db()->prepare("INSERT INTO devices (name,location,group_id,playlist_id,token) VALUES (?,?,?,?,?)")
        ->execute([
            s($b['name'], 120),
            s($b['location'] ?? '', 180),
            $b['group_id']    ? sint($b['group_id'])    : null,
            $b['playlist_id'] ? sint($b['playlist_id']) : null,
            $token,
        ]);
    $new_id = (int)db()->lastInsertId();
    log_act((int)$a['sub'], 'create_device', 'device', $new_id, s($b['name']));

    $dev = db()->query("SELECT * FROM devices WHERE id=$new_id")->fetch();
    $dev['player_url'] = APP_URL . '/html/player.html?token=' . $dev['token'];
    json_ok($dev, 201);
}

// ── PUT ───────────────────────────────────────────────────────
if ($method === 'PUT') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 422);
    $b = body();

    $fields = []; $vals = [];
    if (isset($b['name']))     { $fields[] = 'name=?';     $vals[] = s($b['name'], 120); }
    if (isset($b['location'])) { $fields[] = 'location=?'; $vals[] = s($b['location'], 180); }
    if (array_key_exists('group_id',    $b)) { $fields[] = 'group_id=?';    $vals[] = $b['group_id']    ? sint($b['group_id'])    : null; }
    if (array_key_exists('playlist_id', $b)) { $fields[] = 'playlist_id=?'; $vals[] = $b['playlist_id'] ? sint($b['playlist_id']) : null; }
    if (empty($fields)) json_err('Nenhum campo para atualizar', 422);

    $vals[] = $id;
    db()->prepare("UPDATE devices SET " . implode(',', $fields) . " WHERE id=?")->execute($vals);
    log_act(0, 'edit_device', 'device', $id);
    json_ok(['id' => $id]);
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 422);
    db()->prepare("DELETE FROM devices WHERE id=?")->execute([$id]);
    log_act(0, 'delete_device', 'device', $id);
    json_ok(['deleted' => $id]);
}

json_err('Método não suportado', 405);
