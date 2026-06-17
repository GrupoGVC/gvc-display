<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

$payload = auth_required();
$db      = db();
$m       = method();

if ($m === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT id, name, is_default FROM playlists WHERE id=?");
        $stmt->execute([$id]);
        $pl = $stmt->fetch();
        if (!$pl) json_err('Playlist não encontrada', 404);

        $items = $db->prepare("
            SELECT i.id, i.type, i.url, i.duration, i.sort_order,
                   m.url AS media_url
            FROM playlist_items i
            LEFT JOIN media m ON m.id = i.media_id
            WHERE i.playlist_id = ?
            ORDER BY i.sort_order
        ");
        $items->execute([$id]);
        $pl['items'] = $items->fetchAll();
        json_ok($pl);
    }

    $rows = $db->query("
        SELECT p.id, p.name, p.is_default,
               COUNT(i.id) AS item_count
        FROM playlists p
        LEFT JOIN playlist_items i ON i.playlist_id = p.id
        GROUP BY p.id
        ORDER BY p.name
    ")->fetchAll();
    json_ok($rows);
}

if ($m === 'POST') {
    $name    = req('name');
    $isDef   = !empty(body()['is_default']);
    $copyFrom = (int)(body()['copy_from'] ?? 0);

    if (!$name) json_err('Nome é obrigatório');

    if ($isDef) $db->query("UPDATE playlists SET is_default=0");

    $db->prepare("INSERT INTO playlists (name, is_default) VALUES (?,?)")->execute([$name, $isDef ? 1 : 0]);
    $newId = (int)$db->lastInsertId();

    if ($copyFrom) {
        $items = $db->prepare("SELECT type, url, duration, media_id, sort_order FROM playlist_items WHERE playlist_id=?");
        $items->execute([$copyFrom]);
        $ins = $db->prepare("INSERT INTO playlist_items (playlist_id, type, url, duration, media_id, sort_order) VALUES (?,?,?,?,?,?)");
        foreach ($items->fetchAll() as $it) {
            $ins->execute([$newId, $it['type'], $it['url'], $it['duration'], $it['media_id'], $it['sort_order']]);
        }
    }

    log_activity('create_playlist', $payload['sub'], $name);
    json_ok(['id' => $newId, 'name' => $name, 'is_default' => $isDef, 'item_count' => 0]);
}

if ($m === 'PUT') {
    $id   = (int)($_GET['id'] ?? 0);
    $name = req('name');
    $isDef = isset(body()['is_default']) ? (bool)body()['is_default'] : null;
    if (!$id) json_err('ID inválido');
    if ($isDef) $db->query("UPDATE playlists SET is_default=0");
    $fields = $isDef !== null ? "name=?, is_default=?" : "name=?";
    $params = $isDef !== null ? [$name, $isDef ? 1 : 0, $id] : [$name, $id];
    $db->prepare("UPDATE playlists SET $fields WHERE id=?")->execute($params);
    json_ok(['id' => $id]);
}

if ($m === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_err('ID inválido');
    $db->prepare("DELETE FROM playlist_items WHERE playlist_id=?")->execute([$id]);
    $db->prepare("DELETE FROM playlists WHERE id=?")->execute([$id]);
    // Desvincula devices que usavam essa playlist
    $db->prepare("UPDATE devices SET playlist_id=NULL WHERE playlist_id=?")->execute([$id]);
    log_activity('delete_playlist', $payload['sub'], "id=$id");
    json_ok(['deleted' => $id]);
}

json_err('Método não permitido', 405);
