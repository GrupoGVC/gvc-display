<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

$payload = auth_required();
$db      = db();
$m       = method();

if ($m === 'GET') {
    $rows = $db->query("
        SELECT g.id, g.name, g.description,
               COUNT(d.id) AS device_count
        FROM `groups` g
        LEFT JOIN devices d ON d.group_id = g.id
        GROUP BY g.id
        ORDER BY g.name
    ")->fetchAll();
    json_ok($rows);
}

if ($m === 'POST') {
    $name = req('name');
    $desc = req('description');
    if (!$name) json_err('Nome é obrigatório');
    $db->prepare("INSERT INTO `groups` (name, description) VALUES (?,?)")->execute([$name, $desc ?: null]);
    $id = (int)$db->lastInsertId();
    log_activity('create_group', $payload['sub'], $name);
    json_ok(['id' => $id, 'name' => $name, 'description' => $desc, 'device_count' => 0]);
}

if ($m === 'PUT') {
    $id   = (int)($_GET['id'] ?? 0);
    $name = req('name');
    $desc = req('description');
    if (!$id) json_err('ID inválido');
    $db->prepare("UPDATE `groups` SET name=?, description=? WHERE id=?")->execute([$name, $desc ?: null, $id]);

    // Retorna o registro atualizado com contagem de devices para
    // o frontend poder fazer S.groups[] = S.groups[].map(patch) sem recarregar
    $stmt = $db->prepare("
        SELECT g.id, g.name, g.description, COUNT(d.id) AS device_count
        FROM `groups` g
        LEFT JOIN devices d ON d.group_id = g.id
        WHERE g.id = ?
        GROUP BY g.id");
    $stmt->execute([$id]);
    json_ok($stmt->fetch());
}

if ($m === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_err('ID inválido');
    $db->prepare("UPDATE devices SET group_id=NULL WHERE group_id=?")->execute([$id]);
    $db->prepare("DELETE FROM `groups` WHERE id=?")->execute([$id]);
    log_activity('delete_group', $payload['sub'], "id=$id");
    json_ok(['deleted' => $id]);
}

json_err('Método não permitido', 405);
