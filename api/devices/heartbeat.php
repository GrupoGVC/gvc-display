<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método não permitido', 405);

$b     = body();
$token = s($b['token'] ?? '', 128);
if (!$token) json_err('Token obrigatório', 401);

$st = db()->prepare("SELECT * FROM devices WHERE token = ? LIMIT 1");
$st->execute([$token]);
$device = $st->fetch();
if (!$device) json_err('Dispositivo não encontrado', 404);

db()->prepare("UPDATE devices SET status='online', last_ping=NOW() WHERE id=?")
    ->execute([$device['id']]);

$playlist = resolve_playlist(
    (int)$device['id'],
    $device['playlist_id'] ? (int)$device['playlist_id'] : null,
    $device['group_id']    ? (int)$device['group_id']    : null
);

json_ok([
    'device' => [
        'id'       => (int)$device['id'],
        'name'     => $device['name'],
        'location' => $device['location'],
    ],
    'playlist' => $playlist,
]);
