<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

$payload = auth_required();
$db      = db();
$m       = method();

// ── GET — lista ou detalhe ─────────────────────────────────────
if ($m === 'GET') {
    if (isset($_GET['id'])) {
        $stmt = $db->prepare("
            SELECT d.*, g.name AS group_name, p.name AS playlist_name,
                   CONCAT('/tv/', d.slug) AS player_url
            FROM devices d
            LEFT JOIN `groups` g ON g.id = d.group_id
            LEFT JOIN playlists p ON p.id = d.playlist_id
            WHERE d.id = ?");
        $stmt->execute([$_GET['id']]);
        $dev = $stmt->fetch();
        if (!$dev) json_err('Dispositivo não encontrado', 404);
        json_ok($dev);
    }

    $rows = $db->query("
        SELECT d.id, d.name, d.location, d.slug, d.status, d.last_ping,
               d.playlist_id, d.group_id, d.token,
               g.name AS group_name, p.name AS playlist_name,
               CONCAT('/tv/', d.slug) AS player_url
        FROM devices d
        LEFT JOIN `groups` g ON g.id = d.group_id
        LEFT JOIN playlists p ON p.id = d.playlist_id
        ORDER BY d.name
    ")->fetchAll();
    json_ok($rows);
}

// ── POST — criar ───────────────────────────────────────────────
if ($m === 'POST') {
    $name     = req('name');
    $location = req('location');
    $groupId  = body()['group_id'] ?: null;
    $plId     = body()['playlist_id'] ?: null;

    if (!$name) json_err('Nome é obrigatório');

    $token = bin2hex(random_bytes(16));
    $slug  = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

    $stmt = $db->prepare("INSERT INTO devices (name, location, group_id, playlist_id, token, slug)
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $location ?: null, $groupId, $plId, $token, $slug]);
    $id = (int)$db->lastInsertId();

    log_activity('create_device', $payload['sub'], $name);
    $stmt = $db->prepare("SELECT d.*, CONCAT('/tv/', d.slug) AS player_url FROM devices d WHERE d.id = ?");
    $stmt->execute([$id]);
    json_ok($stmt->fetch());
}

// ── PUT — editar ───────────────────────────────────────────────
if ($m === 'PUT') {
    $id       = (int)($_GET['id'] ?? 0);
    $name     = req('name');
    $location = req('location');
    $b        = body();
    $groupId  = isset($b['group_id']) ? ($b['group_id'] ?: null) : null;
    $plId     = isset($b['playlist_id']) ? ($b['playlist_id'] ?: null) : null;

    if (!$id) json_err('ID inválido');

    $db->prepare("UPDATE devices SET name=?, location=?, group_id=?, playlist_id=? WHERE id=?")
       ->execute([$name ?: null, $location ?: null, $groupId, $plId, $id]);

    log_activity('update_device', $payload['sub'], $name);

    // Retorna o registro completo com JOINs para o frontend atualizar
    // o S.devices[] diretamente, sem recarregar a lista inteira
    $stmt = $db->prepare("
        SELECT d.id, d.name, d.location, d.slug, d.status, d.last_ping,
               d.playlist_id, d.group_id, d.token,
               g.name AS group_name, p.name AS playlist_name,
               CONCAT('/tv/', d.slug) AS player_url
        FROM devices d
        LEFT JOIN `groups` g ON g.id = d.group_id
        LEFT JOIN playlists p ON p.id = d.playlist_id
        WHERE d.id = ?");
    $stmt->execute([$id]);
    json_ok($stmt->fetch());
}

// ── DELETE ─────────────────────────────────────────────────────
if ($m === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_err('ID inválido');
    $stmt = $db->prepare("SELECT name FROM devices WHERE id=?");
    $stmt->execute([$id]);
    $dev = $stmt->fetch();
    $db->prepare("DELETE FROM devices WHERE id=?")->execute([$id]);
    log_activity('delete_device', $payload['sub'], $dev['name'] ?? '');
    json_ok(['deleted' => $id]);
}

json_err('Método não permitido', 405);
