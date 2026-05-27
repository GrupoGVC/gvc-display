<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = sint($_GET['id'] ?? 0);

if ($method === 'GET') {
    auth();
    if ($id) {
        $pl = playlist_full($id);
        if (!$pl) json_err('Não encontrada', 404);
        json_ok($pl);
    }
    $rows = db()->query(
        "SELECT p.*, COUNT(i.id) AS item_count
         FROM playlists p LEFT JOIN playlist_items i ON i.playlist_id = p.id
         GROUP BY p.id ORDER BY p.name"
    )->fetchAll();
    foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['item_count'] = (int)$r['item_count']; }
    json_ok($rows);
}

if ($method === 'POST') {
    $a    = auth_admin();
    $b    = require_fields(['name']);
    $name = s($b['name'], 180);
    $def  = !empty($b['is_default']) ? 1 : 0;
    if ($def) db()->exec("UPDATE playlists SET is_default=0");

    db()->prepare("INSERT INTO playlists (name,is_default,created_by) VALUES (?,?,?)")
        ->execute([$name, $def, (int)$a['sub']]);
    $new_id = (int)db()->lastInsertId();

    // Duplicar?
    if (!empty($b['copy_from'])) {
        $src   = sint($b['copy_from']);
        $items = db()->prepare("SELECT * FROM playlist_items WHERE playlist_id=? ORDER BY sort_order");
        $items->execute([$src]);
        foreach ($items->fetchAll() as $item) {
            db()->prepare("INSERT INTO playlist_items (playlist_id,media_id,type,url,duration,sort_order) VALUES (?,?,?,?,?,?)")
                ->execute([$new_id, $item['media_id'], $item['type'], $item['url'], $item['duration'], $item['sort_order']]);
        }
    }

    log_act((int)$a['sub'], 'create_playlist', 'playlist', $new_id, $name);
    json_ok(playlist_full($new_id), 201);
}

if ($method === 'PUT') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 422);
    $b   = body();
    $def = !empty($b['is_default']) ? 1 : 0;
    if ($def) db()->exec("UPDATE playlists SET is_default=0");
    db()->prepare("UPDATE playlists SET name=?,is_default=?,updated_at=NOW() WHERE id=?")
        ->execute([s($b['name'] ?? '', 180), $def, $id]);
    json_ok(playlist_full($id));
}

if ($method === 'DELETE') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 422);
    db()->prepare("DELETE FROM playlists WHERE id=?")->execute([$id]);
    json_ok(['deleted' => $id]);
}

json_err('Método não suportado', 405);
