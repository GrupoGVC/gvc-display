<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class PairingController extends Controller
{
    public function generate()
    {
        $token = trim($_GET['token'] ?? '');

        if ($token === '') {
            return $this->json([
                'success' => false,
                'error' => 'Token ausente'
            ], 422);
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT id FROM devices WHERE token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        $deviceId = (int) ($stmt->fetchColumn() ?: 0);

        if (!$deviceId) {
            return $this->json([
                'success' => false,
                'error' => 'TV não encontrada para este token'
            ], 404);
        }

        // Reaproveita código válido se já existir.
        $stmt = $pdo->prepare("\n            SELECT code, expires_at\n            FROM pairing_codes\n            WHERE device_id = :device_id\n              AND expires_at > NOW()\n            ORDER BY id DESC\n            LIMIT 1\n        ");
        $stmt->execute(['device_id' => $deviceId]);
        $pair = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pair) {
            $code = $this->newCode($pdo);
            $expiresAt = date('Y-m-d H:i:s', time() + 10 * 60);

            $stmt = $pdo->prepare("\n                INSERT INTO pairing_codes (device_id, code, expires_at)\n                VALUES (:device_id, :code, :expires_at)\n            ");
            $stmt->execute([
                'device_id' => $deviceId,
                'code' => $code,
                'expires_at' => $expiresAt,
            ]);

            $pair = [
                'code' => $code,
                'expires_at' => $expiresAt,
            ];
        }

        return $this->json([
            'success' => true,
            'code' => $pair['code'],
            'expires_at' => $pair['expires_at'],
            'qr_payload' => $pair['code'],
            'admin_url' => $this->getBaseUrl() . '/'
        ]);
    }

    public function confirm()
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $data = $_POST ?: [];
        }

        $code = preg_replace('/\D/', '', $data['code'] ?? '');
        $name = trim($data['name'] ?? '');
        $location = trim($data['location'] ?? '');
        $playlistId = !empty($data['playlist_id']) ? (int) $data['playlist_id'] : null;

        if (strlen($code) !== 6) {
            return $this->json([
                'success' => false,
                'error' => 'Código inválido'
            ], 422);
        }

        if ($name === '') {
            return $this->json([
                'success' => false,
                'error' => 'Informe o nome da TV'
            ], 422);
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("\n            SELECT *\n            FROM pairing_codes\n            WHERE code = :code\n              AND expires_at > NOW()\n            LIMIT 1\n        ");
        $stmt->execute(['code' => $code]);
        $pair = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pair) {
            return $this->json([
                'success' => false,
                'error' => 'Código expirado ou inválido'
            ], 404);
        }

        $deviceId = (int) $pair['device_id'];

        $fields = [
            'name' => $name,
            'location' => $location !== '' ? $location : null,
            'status' => 'online',
            'id' => $deviceId,
        ];

        $playlistSql = '';
        if ($playlistId) {
            $playlistSql = ', playlist_id = :playlist_id';
            $fields['playlist_id'] = $playlistId;
        }

        $stmt = $pdo->prepare("\n            UPDATE devices\n            SET name = :name,\n                location = :location,\n                status = :status\n                {$playlistSql}\n            WHERE id = :id\n        ");
        $stmt->execute($fields);

        $stmt = $pdo->prepare('DELETE FROM pairing_codes WHERE code = :code');
        $stmt->execute(['code' => $code]);

        $this->bumpVersion($pdo, 'player_content');
        $this->bumpVersion($pdo, 'devices');

        $stmt = $pdo->prepare('SELECT * FROM devices WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $deviceId]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        return $this->json([
            'success' => true,
            'message' => 'TV pareada com sucesso',
            'device' => $device
        ]);
    }

    private function newCode(PDO $pdo): string
    {
        do {
            $code = (string) random_int(100000, 999999);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM pairing_codes WHERE code = :code AND expires_at > NOW()');
            $stmt->execute(['code' => $code]);
        } while ((int) $stmt->fetchColumn() > 0);

        return $code;
    }

    private function bumpVersion(PDO $pdo, string $name): void
    {
        $stmt = $pdo->prepare("\n            INSERT INTO content_versions (name, version)\n            VALUES (:name, 1)\n            ON DUPLICATE KEY UPDATE\n                version = version + 1,\n                updated_at = NOW()\n        ");
        $stmt->execute(['name' => $name]);
    }

    private function getBaseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';

        $basePath = $_ENV['APP_BASE_PATH'] ?? '';

        if (!$basePath) {
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            $basePath = preg_replace('#/index\.php$#', '', $script);
            $basePath = rtrim($basePath, '/');
        }

        return rtrim($scheme . '://' . $host . $basePath, '/');
    }
}
