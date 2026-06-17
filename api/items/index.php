<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

$payload = auth_required();
$db      = db();
$m       = method();
$act     = action();

if ($m === 'POST' && $act === 'reorder') {
    $items = body()['items'] ?? [];
    $stmt  = $db->prepare("UPDATE playlist_items SET sort_order=? WHERE id=?");
    foreach ($items as $it) {
        $stmt->execute([(int)$it['sort_order'], (int)$it['id']]);
    }
    json_ok(['reordered' => count($items)]);
}

if ($m === 'POST') {
    $plId    = (int)(body()['playlist_id'] ?? 0);
    $type    = body()['type'] ?? 'image';
    $url     = req('url');
    $dur     = (int)(body()['duration'] ?? 10);
    $mediaId = body()['media_id'] ? (int)body()['media_id'] : null;

    if (!$plId || !$url) json_err('playlist_id e url são obrigatórios');
    if (!in_array($type, ['image','video','page'])) json_err('Tipo inválido');

    // Próxima ordem
    $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order),0)+1 AS next FROM playlist_items WHERE playlist_id=?");
    $stmt->execute([$plId]);
    $next = (int)$stmt->fetchColumn();

    $ins = $db->prepare("INSERT INTO playlist_items (playlist_id, type, url, duration, media_id, sort_order)
                         VALUES (?,?,?,?,?,?)");
    $ins->execute([$plId, $type, $url, $dur, $mediaId, $next]);
    $id = (int)$db->lastInsertId();
    json_ok(['id' => $id, 'sort_order' => $next]);
}

if ($m === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_err('ID inválido');
    $db->prepare("DELETE FROM playlist_items WHERE id=?")->execute([$id]);
    json_ok(['deleted' => $id]);
}

json_err('Método não permitido', 405);
