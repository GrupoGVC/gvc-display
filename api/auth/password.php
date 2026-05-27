<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método não permitido', 405);

$p = auth();
$b = require_fields(['current_password', 'new_password']);

if (strlen((string)$b['new_password']) < 6)
    json_err('A nova senha precisa ter ao menos 6 caracteres', 422);

$row = db()->prepare("SELECT password FROM users WHERE id = ?")->execute([(int)$p['sub']]);
$row = db()->query("SELECT password FROM users WHERE id=" . (int)$p['sub'])->fetch();

if (!$row || !password_verify((string)$b['current_password'], $row['password']))
    json_err('Senha atual incorreta', 401);

$hash = password_hash((string)$b['new_password'], PASSWORD_BCRYPT, ['cost' => 12]);
db()->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, (int)$p['sub']]);

log_act((int)$p['sub'], 'change_password', 'user', (int)$p['sub']);
json_ok(['message' => 'Senha alterada com sucesso']);
