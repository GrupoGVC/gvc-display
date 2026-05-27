<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método não permitido', 405);
$a = auth_admin();
$b = require_fields(['playlist_id', 'target']);

$pl_id  = sint($b['playlist_id']);
$target = s($b['target'], 60);

$chk = db()->prepare("SELECT id FROM playlists WHERE id=?");
$chk->execute([$pl_id]);
if (!$chk->fetch()) json_err('Playlist não encontrada', 404);

if ($target === 'all') {
    $st = db()->prepare("UPDATE devices SET playlist_id=?");
    $st->execute([$pl_id]);
} elseif (str_starts_with($target, 'group:')) {
    $gid = sint(substr($target, 6));
    $st  = db()->prepare("UPDATE devices SET playlist_id=? WHERE group_id=?");
    $st->execute([$pl_id, $gid]);
} elseif (str_starts_with($target, 'device:')) {
    $did = sint(substr($target, 7));
    $st  = db()->prepare("UPDATE devices SET playlist_id=? WHERE id=?");
    $st->execute([$pl_id, $did]);
} else {
    json_err('Target inválido', 422);
}

$count = $st->rowCount();
log_act((int)$a['sub'], 'broadcast', 'playlist', $pl_id, "target=$target affected=$count");
json_ok(['affected' => $count, 'playlist_id' => $pl_id, 'target' => $target]);
