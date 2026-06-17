<?php
require_once __DIR__ . '/../config.php';

if (method() !== 'POST') json_err('POST esperado', 405);

$token = req('token') ?: ($_GET['token'] ?? '');
if (!$token) json_err('Token ausente', 401);

$db   = db();
$stmt = $db->prepare("SELECT id, name, playlist_id FROM devices WHERE token = ?");
$stmt->execute([$token]);
$dev  = $stmt->fetch();
if (!$dev) json_err('Token inválido', 401);

// Atualiza status e last_ping
$db->prepare("UPDATE devices SET status='online', last_ping=NOW() WHERE id=?")
   ->execute([$dev['id']]);

// Um device está "configurado" quando tem nome real (não foi gerado automaticamente pelo tv.php)
// Devices auto-gerados pelo tv.php têm nome no formato "TV XXXXXX" (6 chars hex)
// Após o pareamento o admin dá um nome real
$isConfigured = !preg_match('/^TV [A-F0-9]{6}$/i', $dev['name']);

// Se não está configurado (nunca pareado pelo admin) → TV mostra tela de pareamento
if (!$isConfigured) {
    json_ok([
        'playlist_id'   => null,
        'playlist_hash' => null,
        'configured'    => false,
    ]);
}

// Device configurado — busca playlist ativa
$agenda = $db->prepare("
    SELECT s.playlist_id
    FROM schedules s
    WHERE s.active = 1
      AND s.starts_at <= NOW()
      AND s.ends_at   >= NOW()
      AND (
        s.target_type = 'all'
        OR (s.target_type = 'device' AND s.target_id = ?)
        OR (s.target_type = 'group'  AND s.target_id = (
              SELECT group_id FROM devices WHERE id = ?
            ))
      )
    ORDER BY s.id DESC
    LIMIT 1
");
$agenda->execute([$dev['id'], $dev['id']]);
$sched = $agenda->fetch();

$playlistId = $sched ? $sched['playlist_id'] : $dev['playlist_id'];

if (!$playlistId) {
    $default    = $db->query("SELECT id FROM playlists WHERE is_default=1 LIMIT 1")->fetch();
    $playlistId = $default['id'] ?? null;
}

$hash = null;
if ($playlistId) {
    $stmt2 = $db->prepare("
        SELECT GROUP_CONCAT(i.id ORDER BY i.sort_order) AS h
        FROM playlist_items i WHERE i.playlist_id = ?
    ");
    $stmt2->execute([$playlistId]);
    $hash = md5($stmt2->fetchColumn() ?? '');
}

json_ok([
    'playlist_id'   => $playlistId,
    'playlist_hash' => $hash,
    'configured'    => true,
]);
