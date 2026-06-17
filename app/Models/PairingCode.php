<?php
namespace App\Models;

use App\Core\Model;

class PairingCode extends Model
{
    protected string $table = 'pairing_codes';

    public function generate(int $deviceId): string
    {
        $this->db->query("DELETE FROM pairing_codes WHERE expires_at < NOW()");

        // Reutiliza código válido
        $st = $this->db->prepare("SELECT code FROM pairing_codes WHERE device_id=? AND expires_at > NOW()");
        $st->execute([$deviceId]);
        $row = $st->fetch();
        if ($row) return $row['code'];

        do {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $ck   = $this->db->prepare("SELECT 1 FROM pairing_codes WHERE code=?");
            $ck->execute([$code]);
        } while ($ck->fetchColumn());

        $this->db->prepare("INSERT INTO pairing_codes (device_id, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))")
                 ->execute([$deviceId, $code]);
        return $code;
    }

    public function consume(string $code): ?array
    {
        $st = $this->db->prepare("SELECT id, device_id FROM pairing_codes WHERE code=? AND expires_at > NOW()");
        $st->execute([$code]);
        $row = $st->fetch();
        if (!$row) return null;
        $this->delete($row['id']);
        return $row;
    }

    public function pending(): array
    {
        $this->db->query("DELETE FROM pairing_codes WHERE expires_at < NOW()");
        return $this->db->query("
            SELECT pc.code, pc.expires_at, d.id AS device_id, d.name AS device_name
            FROM pairing_codes pc LEFT JOIN devices d ON d.id=pc.device_id
            ORDER BY pc.created_at DESC
        ")->fetchAll();
    }
}
