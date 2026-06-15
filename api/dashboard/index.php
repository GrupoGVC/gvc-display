<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_err('Método não permitido', 405);
auth();

$db = db();
// Marca offline: sem ping há 30s OU sem token (não pareada)
$db->exec("UPDATE devices SET status='offline' WHERE last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND) OR last_ping IS NULL OR token='' OR token IS NULL");

$stats = [
    'total_devices'   => (int)$db->query("SELECT COUNT(*) FROM devices")->fetchColumn(),
    'online_devices'  => (int)$db->query("SELECT COUNT(*) FROM devices WHERE status='online'")->fetchColumn(),
    'total_playlists' => (int)$db->query("SELECT COUNT(*) FROM playlists")->fetchColumn(),
    'total_groups'    => (int)$db->query("SELECT COUNT(*) FROM `groups`")->fetchColumn(),
    'total_media'     => (int)$db->query("SELECT COUNT(*) FROM media")->fetchColumn(),
];

$devices = $db->query(
    "SELECT d.id, d.name, d.location, d.status, d.last_ping, d.token,
            g.name AS group_name, p.name AS playlist_name
     FROM devices d
     LEFT JOIN `groups` g    ON g.id = d.group_id
     LEFT JOIN playlists p ON p.id = d.playlist_id
     ORDER BY d.status DESC, d.name"
)->fetchAll();

foreach ($devices as &$d) {
    $d['id']         = (int)$d['id'];
    $d['player_url'] = APP_URL . '/html/player.html?token=' . $d['token'];
}

$logs = $db->query(
    "SELECT l.*, u.name AS user_name FROM activity_logs l
     LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC LIMIT 20"
)->fetchAll();

json_ok(['stats' => $stats, 'devices' => $devices, 'logs' => $logs]);
