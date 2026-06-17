<?php
namespace App\Models;

use App\Core\Model;

class Media extends Model
{
    protected string $table = 'media';

    public function allOrdered(): array
    {
        return $this->db->query("
            SELECT id, original_name AS original, type, url, size, created_at
            FROM media ORDER BY created_at DESC
        ")->fetchAll();
    }
}
