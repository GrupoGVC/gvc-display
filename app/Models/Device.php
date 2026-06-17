<?php
namespace App\Models;

use App\Core\Model;

class Device extends Model
{
    protected string $table = 'devices';

    public function allWithRelations(): array
    {
        return $this->db->query("
            SELECT d.id, d.name, d.location, d.slug, d.status, d.last_ping,
                   d.playlist_id, d.group_id, d.token,
                   g.name AS group_name, p.name AS playlist_name,
                   CONCAT('/tv/', d.slug) AS player_url
            FROM devices d
            LEFT JOIN `groups` g ON g.id = d.group_id
            LEFT JOIN playlists p ON p.id = d.playlist_id
            ORDER BY d.name
        ")->fetchAll();
    }

    public function findWithRelations(int $id): ?array
    {
        $st = $this->db->prepare("
            SELECT d.*, g.name AS group_name, p.name AS playlist_name,
                   CONCAT('/tv/', d.slug) AS player_url
            FROM devices d
            LEFT JOIN `groups` g ON g.id = d.group_id
            LEFT JOIN playlists p ON p.id = d.playlist_id
            WHERE d.id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $st = $this->db->prepare("SELECT * FROM devices WHERE slug = ?");
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $st = $this->db->prepare("SELECT id, name, playlist_id, group_id FROM devices WHERE token = ?");
        $st->execute([$token]);
        return $st->fetch() ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->prepare("UPDATE devices SET status=?, last_ping=NOW() WHERE id=?")
                 ->execute([$status, $id]);
    }

    public function createWithSlug(array $data): int
    {
        $data['slug']  = $data['slug']  ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $data['name'])) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $data['token'] = $data['token'] ?? bin2hex(random_bytes(16));
        return $this->create($data);
    }

    public function isConfigured(string $name): bool
    {
        // TVs auto-geradas têm nome "TV XXXXXX" (6 hex chars)
        return !preg_match('/^TV [A-F0-9]{6}$/i', $name);
    }
}
