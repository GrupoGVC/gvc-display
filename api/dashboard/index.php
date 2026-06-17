<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

auth_required();

$db = db();

// Stats
$stats = [
    'total_devices'   => (int)$db->query("SELECT COUNT(*) FROM devices")->fetchColumn(),
    'online_devices'  => (int)$db->query("SELECT COUNT(*) FROM devices WHERE status='online'")->fetchColumn(),
    'total_playlists' => (int)$db->query("SELECT COUNT(*) FROM playlists")->fetchColumn(),
    'total_media'     => (int)$db->query("SELECT COUNT(*) FROM media")->fetchColumn(),
];

// Dispositivos com playlist
$devices = $db->query("
    SELECT d.id, d.name, d.location, d.status, d.last_ping,
           p.name AS playlist_name
    FROM devices d
    LEFT JOIN playlists p ON p.id = d.playlist_id
    ORDER BY d.status DESC, d.name
    LIMIT 20
")->fetchAll();

// Logs recentes
$logs = $db->query("
    SELECT l.id, l.action, l.detail, l.created_at,
           u.name AS user_name
    FROM activity_logs l
    LEFT JOIN users u ON u.id = l.user_id
    ORDER BY l.created_at DESC
    LIMIT 30
")->fetchAll();

json_ok(['stats' => $stats, 'devices' => $devices, 'logs' => $logs]);
