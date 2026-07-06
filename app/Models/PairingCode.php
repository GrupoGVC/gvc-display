<?php
namespace App\Models;

use App\Core\Model;

class PairingCode extends Model
{
    protected string $table = 'pairing_codes';

    /**
     * Gera código para uma TV que está pedindo pareamento.
     * $clientId é um fingerprint persistente do navegador da TV.
     * Ainda NÃO existe device_id — a TV ainda não é conhecida.
     */
    public function generateForClient(string $clientId): string
    {
        $this->cleanExpired();

        // Reutiliza código válido para o mesmo client_id
        $st = $this->db->prepare("
            SELECT code FROM pairing_codes
            WHERE client_id = ? AND device_id IS NULL AND expires_at > NOW()
            LIMIT 1
        ");
        $st->execute([$clientId]);
        $row = $st->fetch();
        if ($row) return $row['code'];

        // Gera novo código único
        do {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $ck   = $this->db->prepare("SELECT 1 FROM pairing_codes WHERE code = ?");
            $ck->execute([$code]);
        } while ($ck->fetchColumn());

        $this->db->prepare("
            INSERT INTO pairing_codes (device_id, client_id, code, expires_at)
            VALUES (NULL, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
        ")->execute([$clientId, $code]);

        return $code;
    }

    /**
     * Consome um código (admin vinculou a uma TV). Retorna o registro.
     */
    public function consume(string $code): ?array
    {
        $st = $this->db->prepare("
            SELECT id, device_id, client_id
            FROM pairing_codes
            WHERE code = ? AND expires_at > NOW()
        ");
        $st->execute([$code]);
        $row = $st->fetch();
        if (!$row) return null;
        $this->delete($row['id']);
        return $row;
    }

    /**
     * TV consulta: já foi pareada (código consumido pelo admin)?
     * Como o consume() apaga o registro, a ausência = pareada.
     * Precisamos de um outro sinal: buscar em devices via client_id.
     */
    public function findPendingByClient(string $clientId): ?array
    {
        $st = $this->db->prepare("
            SELECT code, expires_at
            FROM pairing_codes
            WHERE client_id = ? AND device_id IS NULL AND expires_at > NOW()
            LIMIT 1
        ");
        $st->execute([$clientId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function pending(): array
    {
        $this->cleanExpired();
        return $this->db->query("
            SELECT pc.code, pc.expires_at, pc.client_id, d.id AS device_id, d.name AS device_name
            FROM pairing_codes pc
            LEFT JOIN devices d ON d.id = pc.device_id
            ORDER BY pc.created_at DESC
        ")->fetchAll();
    }

    private function cleanExpired(): void
    {
        $this->db->query("DELETE FROM pairing_codes WHERE expires_at < NOW()");
    }
}
