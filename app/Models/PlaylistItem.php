<?php
namespace App\Models;

use App\Core\Model;

class PlaylistItem extends Model
{
    protected string $table = 'playlist_items';

    public function reorder(array $items): void
    {
        $st = $this->db->prepare("UPDATE playlist_items SET sort_order=? WHERE id=?");
        foreach ($items as $item) {
            $st->execute([(int)$item['sort_order'], (int)$item['id']]);
        }
    }
}
