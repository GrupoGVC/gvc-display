<?php
namespace App\Models;

use App\Core\Model;

class Group extends Model
{
    protected string $table = 'groups';

    public function allWithCount(): array
    {
        return $this->db->query("
            SELECT g.id, g.name, g.description, COUNT(d.id) AS device_count
            FROM `groups` g
            LEFT JOIN devices d ON d.group_id = g.id
            GROUP BY g.id ORDER BY g.name
        ")->fetchAll();
    }

    public function findWithCount(int $id): ?array
    {
        $st = $this->db->prepare("
            SELECT g.id, g.name, g.description, COUNT(d.id) AS device_count
            FROM `groups` g
            LEFT JOIN devices d ON d.group_id = g.id
            WHERE g.id = ? GROUP BY g.id");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }
}
