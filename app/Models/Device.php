<?php
namespace App\Models;

use App\Core\Model;

class Device extends Model
{
    protected string $table = 'devices';

    /**
     * Lista todas as TVs cadastradas com dados relacionais.
     * Cada uma vem com paired = true/false baseado em ter token ou não.
     */
    public function allWithRelations(): array
    {
        $rows = $this->db->query("
            SELECT d.id, d.name, d.location, d.slug, d.token, d.client_id,
                   d.last_ping, d.playlist_id, d.group_id,
                   g.name AS group_name, p.name AS playlist_name,
                   CASE WHEN d.slug IS NOT NULL
                     THEN CONCAT('/tv/', d.slug)
                     ELSE NULL
                   END AS player_url,
                   CASE
                     WHEN d.last_ping >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
                     THEN 'online' ELSE 'offline'
                   END AS status
            FROM devices d
            LEFT JOIN `groups` g ON g.id = d.group_id
            LEFT JOIN playlists p ON p.id = d.playlist_id
            ORDER BY d.name
        ")->fetchAll();

        foreach ($rows as &$r) {
            $r['paired'] = !empty($r['token']);
            // Não expõe token para o frontend admin
            unset($r['token']);
        }
        return $rows;
    }

    public function findWithRelations(int $id): ?array
    {
        $st = $this->db->prepare("
            SELECT d.*, g.name AS group_name, p.name AS playlist_name,
                   CASE WHEN d.slug IS NOT NULL
                     THEN CONCAT('/tv/', d.slug)
                     ELSE NULL
                   END AS player_url
            FROM devices d
            LEFT JOIN `groups` g ON g.id = d.group_id
            LEFT JOIN playlists p ON p.id = d.playlist_id
            WHERE d.id = ?
        ");
        $st->execute([$id]);
        $row = $st->fetch() ?: null;
        if ($row) {
            $row['paired'] = !empty($row['token']);
            unset($row['token']);
        }
        return $row;
    }

    public function findBySlug(string $slug): ?array
    {
        $st = $this->db->prepare("SELECT * FROM devices WHERE slug = ?");
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $st = $this->db->prepare("SELECT * FROM devices WHERE token = ? LIMIT 1");
        $st->execute([$token]);
        return $st->fetch() ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->prepare("UPDATE devices SET last_ping = NOW() WHERE id = ?")
                 ->execute([$id]);
    }
}
