<?php
namespace App\Controllers;

use App\Core\{Controller, Response, Database};

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->auth();
        $db = Database::connection();

        $stats = [
            'total_devices'    => (int)$db->query("SELECT COUNT(*) FROM devices")->fetchColumn(),
            'online_devices'   => (int)$db->query("SELECT COUNT(*) FROM devices WHERE status='online' AND last_ping >= DATE_SUB(NOW(), INTERVAL 30 SECOND)")->fetchColumn(),
            'total_playlists'  => (int)$db->query("SELECT COUNT(*) FROM playlists")->fetchColumn(),
            'total_media'      => (int)$db->query("SELECT COUNT(*) FROM media")->fetchColumn(),
        ];

        $devices = $db->query("
            SELECT d.id, d.name, d.location, d.status, p.name AS playlist_name
            FROM devices d LEFT JOIN playlists p ON p.id=d.playlist_id
            ORDER BY d.status DESC, d.name LIMIT 20
        ")->fetchAll();

        $logs = $db->query("
            SELECT l.action, l.detail, l.created_at, u.name AS user_name
            FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id
            ORDER BY l.created_at DESC LIMIT 20
        ")->fetchAll();

        Response::json(compact('stats', 'devices', 'logs'));
    }
}
