<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = sint($_GET['id'] ?? 0);

if ($method === 'GET') {
    auth();
    $rows = db()->query(
        "SELECT s.*, p.name AS playlist_name FROM schedules s
         JOIN playlists p ON p.id = s.playlist_id ORDER BY s.starts_at"
    )->fetchAll();
    foreach ($rows as &$r) {
        $r['id']       = (int)$r['id'];
        $r['weekdays'] = json_decode($r['weekdays'] ?? '[]', true);
    }
    json_ok($rows);
}

if ($method === 'POST') {
    auth_admin();
    $b = require_fields(['playlist_id', 'target_type', 'starts_at', 'ends_at']);

    if (strtotime($b['ends_at']) <= strtotime($b['starts_at']))
        json_err('Data fim deve ser posterior à data início', 422);

    $weekdays = is_array($b['weekdays'] ?? null) ? array_map('intval', $b['weekdays']) : [];

    db()->prepare(
        "INSERT INTO schedules (playlist_id,target_type,target_id,starts_at,ends_at,repeat_weekly,weekdays)
         VALUES (?,?,?,?,?,?,?)"
    )->execute([
        sint($b['playlist_id']),
        in_array($b['target_type'], ['all','group','device']) ? $b['target_type'] : 'all',
        !empty($b['target_id']) ? sint($b['target_id']) : null,
        s($b['starts_at'], 20),
        s($b['ends_at'],   20),
        !empty($b['repeat_weekly']) ? 1 : 0,
        json_encode($weekdays),
    ]);
    json_ok(['id' => (int)db()->lastInsertId()], 201);
}

if ($method === 'PUT') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 422);
    $b = body();
    db()->prepare("UPDATE schedules SET active=? WHERE id=?")
        ->execute([!empty($b['active']) ? 1 : 0, $id]);
    json_ok(['id' => $id]);
}

if ($method === 'DELETE') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 422);
    db()->prepare("DELETE FROM schedules WHERE id=?")->execute([$id]);
    json_ok(['deleted' => $id]);
}

json_err('Método não suportado', 405);
