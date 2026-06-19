<?php
/*
Cole este método dentro de app/Controllers/DeviceController.php.
Ele substitui o heartbeat atual.
*/

public function heartbeat()
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        $data = $_POST ?: [];
    }

    $token = trim($data['token'] ?? '');
    $slug = trim(strtolower($data['slug'] ?? ''));

    if ($token === '' && $slug === '') {
        return $this->json([
            'success' => false,
            'error' => 'Token ou slug ausente'
        ], 401);
    }

    $pdo = \App\Core\Database::getConnection();

    if ($token !== '') {
        $stmt = $pdo->prepare('SELECT * FROM devices WHERE token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM devices WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
    }

    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device && $slug !== '') {
        $stmt = $pdo->prepare('SELECT * FROM devices WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$device) {
        return $this->json([
            'success' => false,
            'error' => 'TV não encontrada'
        ], 404);
    }

    $stmt = $pdo->prepare("\n        UPDATE devices\n        SET status = 'online', last_ping = NOW()\n        WHERE id = :id\n    ");
    $stmt->execute(['id' => (int) $device['id']]);

    $name = trim($device['name'] ?? '');

    // TVs temporárias criadas por /tv/ têm nome TV ABC123.
    // Quando o admin pareia e informa nome real, configured vira true.
    $configured = !preg_match('/^TV\s+[A-Z0-9]{6,12}$/', $name);

    $stmt = $pdo->prepare("\n        SELECT version\n        FROM content_versions\n        WHERE name = 'player_content'\n        LIMIT 1\n    ");
    $stmt->execute();
    $version = (int) ($stmt->fetchColumn() ?: 1);

    return $this->json([
        'success' => true,
        'device_id' => (int) $device['id'],
        'name' => $device['name'],
        'slug' => $device['slug'],
        'configured' => $configured,
        'playlist_id' => !empty($device['playlist_id']) ? (int) $device['playlist_id'] : null,
        'status' => 'online',
        'content_version' => $version
    ]);
}
