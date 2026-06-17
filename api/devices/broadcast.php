<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

if (method() !== 'POST') json_err('POST esperado', 405);
$payload = auth_required();
$db      = db();

$plId   = (int)(body()['playlist_id'] ?? 0);
$target = body()['target'] ?? 'all';

if (!$plId) json_err('playlist_id ausente');

// Verifica que playlist existe
$pl = $db->prepare("SELECT id, name FROM playlists WHERE id=?");
$pl->execute([$plId]);
if (!$pl->fetch()) json_err('Playlist não encontrada', 404);

$affected = 0;

if ($target === 'all') {
    $r = $db->prepare("UPDATE devices SET playlist_id = ?");
    $r->execute([$plId]);
    $affected = $r->rowCount();
} elseif (str_starts_with($target, 'group:')) {
    $gid = (int)substr($target, 6);
    $r   = $db->prepare("UPDATE devices SET playlist_id = ? WHERE group_id = ?");
    $r->execute([$plId, $gid]);
    $affected = $r->rowCount();
} elseif (str_starts_with($target, 'device:')) {
    $did = (int)substr($target, 7);
    $r   = $db->prepare("UPDATE devices SET playlist_id = ? WHERE id = ?");
    $r->execute([$plId, $did]);
    $affected = $r->rowCount();
} else {
    json_err('Target inválido');
}

log_activity('broadcast', $payload['sub'], "playlist=$plId target=$target affected=$affected");
json_ok(['affected' => $affected]);
