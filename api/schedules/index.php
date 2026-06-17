<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

$payload = auth_required();
$db      = db();
$m       = method();

if ($m === 'GET') {
    $rows = $db->query("
        SELECT s.*, p.name AS playlist_name
        FROM schedules s
        LEFT JOIN playlists p ON p.id = s.playlist_id
        ORDER BY s.starts_at DESC
    ")->fetchAll();
    foreach ($rows as &$r) {
        $r['weekdays'] = $r['weekdays'] ? json_decode($r['weekdays'], true) : [];
        $r['active']   = (bool)$r['active'];
        $r['repeat_weekly'] = (bool)$r['repeat_weekly'];
    }
    json_ok($rows);
}

if ($m === 'POST') {
    $plId    = (int)(body()['playlist_id'] ?? 0);
    $ttype   = body()['target_type'] ?? 'all';
    $tid     = body()['target_id'] ?: null;
    $starts  = body()['starts_at'] ?? '';
    $ends    = body()['ends_at'] ?? '';
    $repeat  = !empty(body()['repeat_weekly']);
    $wdays   = json_encode(body()['weekdays'] ?? []);

    if (!$plId || !$starts || !$ends) json_err('Campos obrigatórios ausentes');

    $db->prepare("INSERT INTO schedules (playlist_id, target_type, target_id, starts_at, ends_at, repeat_weekly, weekdays)
                  VALUES (?,?,?,?,?,?,?)")
       ->execute([$plId, $ttype, $tid, $starts, $ends, $repeat ? 1 : 0, $wdays]);

    log_activity('create_schedule', $payload['sub'], "playlist=$plId");
    json_ok(['id' => (int)$db->lastInsertId()]);
}

if ($m === 'PUT') {
    $id     = (int)($_GET['id'] ?? 0);
    $b      = body();
    $active = isset($b['active']) ? ((bool)$b['active'] ? 1 : 0) : null;
    if (!$id) json_err('ID inválido');

    if ($active !== null) {
        $db->prepare("UPDATE schedules SET active=? WHERE id=?")->execute([$active, $id]);
    } else {
        $db->prepare("UPDATE schedules SET starts_at=?, ends_at=?, repeat_weekly=?, weekdays=? WHERE id=?")
           ->execute([$b['starts_at'], $b['ends_at'], $b['repeat_weekly'] ? 1 : 0, json_encode($b['weekdays'] ?? []), $id]);
    }
    json_ok(['id' => $id]);
}

if ($m === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_err('ID inválido');
    $db->prepare("DELETE FROM schedules WHERE id=?")->execute([$id]);
    log_activity('delete_schedule', $payload['sub'], "id=$id");
    json_ok(['deleted' => $id]);
}

json_err('Método não permitido', 405);
