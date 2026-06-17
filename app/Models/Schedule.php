<?php
namespace App\Models;

use App\Core\Model;

class Schedule extends Model
{
    protected string $table = 'schedules';

    public function allWithPlaylist(): array
    {
        return $this->db->query("
            SELECT s.*, p.name AS playlist_name
            FROM schedules s
            LEFT JOIN playlists p ON p.id = s.playlist_id
            ORDER BY s.starts_at DESC
        ")->fetchAll();
    }
}
