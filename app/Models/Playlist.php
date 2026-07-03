<?php
namespace App\Models;

use App\Core\Model;

class Playlist extends Model
{
    protected string $table = 'playlists';

    public function allWithCount(): array
    {
        return $this->db->query("
            SELECT p.id, p.name, p.is_default, COUNT(i.id) AS item_count
            FROM playlists p
            LEFT JOIN playlist_items i ON i.playlist_id = p.id
            GROUP BY p.id ORDER BY p.name
        ")->fetchAll();
    }

    public function findWithItems(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM playlists WHERE id = ?");
        $st->execute([$id]);
        $pl = $st->fetch();
        if (!$pl) return null;

        $it = $this->db->prepare("
            SELECT i.id, i.type, i.url, i.duration, i.sort_order,
                   m.url AS media_url
            FROM playlist_items i
            LEFT JOIN media m ON m.id = i.media_id
            WHERE i.playlist_id = ? ORDER BY i.sort_order");
        $it->execute([$id]);
        $pl['items'] = $it->fetchAll();
        $pl['hash']  = md5(json_encode($pl['items']));
        return $pl;
    }
}
