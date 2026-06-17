<?php
namespace App\Core;

use PDO;

abstract class Model
{
    protected PDO $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function all(string $orderBy = 'id', string $dir = 'ASC'): array
    {
        return $this->db->query("SELECT * FROM {$this->table} ORDER BY $orderBy $dir")->fetchAll();
    }

    public function create(array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $vals = implode(', ', array_fill(0, count($data), '?'));
        $st = $this->db->prepare("INSERT INTO {$this->table} ($cols) VALUES ($vals)");
        $st->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $st = $this->db->prepare("UPDATE {$this->table} SET $set WHERE {$this->primaryKey} = ?");
        return $st->execute([...array_values($data), $id]);
    }

    public function delete(int $id): bool
    {
        return $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?")
                        ->execute([$id]);
    }
}
