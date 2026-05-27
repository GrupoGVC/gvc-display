<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = sint($_GET['id'] ?? 0);
$action = s($_GET['action'] ?? '', 20);

// ── Reordenar em batch ────────────────────────────────────────
if ($method === 'POST' && $action === 'reorder') {
    auth_admin();
    $items = body()['items'] ?? [];
    if (!is_array($items)) json_err('items deve ser um array', 422);

    $st = db()->prepare("UPDATE playlist_items SET sort_order=? WHERE id=?");
    foreach ($items as $item) {
        $st->execute([sint($item['sort_order']), sint($item['id'])]);
    }
    // Atualizar hash da playlist
    if (!empty($items)) {
        $pl = db()->prepare("SELECT playlist_id FROM playlist_items WHERE id=? LIMIT 1");
        $pl->execute([sint($items[0]['id'])]);
        if ($pl_id = $pl->fetchColumn()) {
            db()->prepare("UPDATE playlists SET updated_at=NOW() WHERE id=?")->execute([$pl_id]);
        }
    }
    json_ok(['reordered' => count($items)]);
}

// ── Criar item ────────────────────────────────────────────────
if ($method === 'POST') {
    auth_admin();
    $b = require_fields(['playlist_id', 'type']);

    $pl_id    = sint($b['playlist_id']);
    $type     = in_array($b['type'], ['image', 'video', 'page']) ? $b['type'] : 'image';
    $media_id = !empty($b['media_id']) ? sint($b['media_id']) : null;
    $url      = s($b['url'] ?? '', 600);
    $duration = max(1, sint($b['duration'] ?? 10));

    $max = db()->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM playlist_items WHERE playlist_id=?");
    $max->execute([$pl_id]);
    $sort = (int)$max->fetchColumn();

    db()->prepare("INSERT INTO playlist_items (playlist_id,media_id,type,url,duration,sort_order) VALUES (?,?,?,?,?,?)")
        ->execute([$pl_id, $media_id, $type, $url ?: null, $duration, $sort]);
    $new_id = (int)db()->lastInsertId();

    db()->prepare("UPDATE playlists SET updated_at=NOW() WHERE id=?")->execute([$pl_id]);
    json_ok(['id' => $new_id], 201);
}

// ── Editar item ───────────────────────────────────────────────
if ($method === 'PUT') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 422);
    $b = body(); $fields = []; $vals = [];

    if (isset($b['duration']))   { $fields[] = 'duration=?';   $vals[] = max(1, sint($b['duration'])); }
    if (isset($b['sort_order'])) { $fields[] = 'sort_order=?'; $vals[] = sint($b['sort_order']); }
    if (isset($b['url']))        { $fields[] = 'url=?';        $vals[] = s($b['url'], 600); }
    if (empty($fields)) json_err('Nada para atualizar', 422);

    $vals[] = $id;
    db()->prepare("UPDATE playlist_items SET " . implode(',', $fields) . " WHERE id=?")->execute($vals);

    $pl_id = db()->query("SELECT playlist_id FROM playlist_items WHERE id=$id")->fetchColumn();
    if ($pl_id) db()->prepare("UPDATE playlists SET updated_at=NOW() WHERE id=?")->execute([$pl_id]);

    json_ok(['id' => $id]);
}

// ── Deletar item ──────────────────────────────────────────────
if ($method === 'DELETE') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 422);
    $pl_id = db()->query("SELECT playlist_id FROM playlist_items WHERE id=$id")->fetchColumn();
    db()->prepare("DELETE FROM playlist_items WHERE id=?")->execute([$id]);
    if ($pl_id) db()->prepare("UPDATE playlists SET updated_at=NOW() WHERE id=?")->execute([$pl_id]);
    json_ok(['deleted' => $id]);
}

json_err('Método não suportado', 405);
