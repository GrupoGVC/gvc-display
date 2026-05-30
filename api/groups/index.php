<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = sint($_GET['id'] ?? 0);

if ($method === 'GET') {
    auth();
    $rows = db()->query(
        "SELECT g.*, COUNT(d.id) AS device_count
         FROM `groups` g LEFT JOIN devices d ON d.group_id = g.id
         GROUP BY g.id ORDER BY g.name"
    )->fetchAll();
    foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['device_count'] = (int)$r['device_count']; }
    json_ok($rows);
}

if ($method === 'POST') {
    $a = auth_admin();
    $b = require_fields(['name']);
    db()->prepare("INSERT INTO `groups` (name,description) VALUES (?,?)")
        ->execute([s($b['name'], 120), s($b['description'] ?? '', 255)]);
    $new_id = (int)db()->lastInsertId();
    log_act((int)$a['sub'], 'create_group', 'group', $new_id, s($b['name']));
    json_ok(['id' => $new_id], 201);
}

if ($method === 'PUT') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 422);
    $b = body();
    db()->prepare("UPDATE `groups` SET name=?,description=? WHERE id=?")
        ->execute([s($b['name'] ?? '', 120), s($b['description'] ?? '', 255), $id]);
    json_ok(['id' => $id]);
}

if ($method === 'DELETE') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 422);
    db()->prepare("DELETE FROM `groups` WHERE id=?")->execute([$id]);
    json_ok(['deleted' => $id]);
}

json_err('Método não suportado', 405);
