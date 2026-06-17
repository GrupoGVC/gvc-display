<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

if (method() !== 'POST') json_err('Método não permitido', 405);
$payload = auth_required();

$cur = req('current_password');
$new = req('new_password');
if (!$cur || !$new) json_err('Preencha todos os campos');
if (strlen($new) < 6) json_err('Nova senha deve ter pelo menos 6 caracteres');

$stmt = db()->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->execute([$payload['sub']]);
$user = $stmt->fetch();
if (!$user || !password_verify($cur, $user['password_hash'])) json_err('Senha atual incorreta');

$hash = password_hash($new, PASSWORD_DEFAULT);
db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $payload['sub']]);

json_ok(['message' => 'Senha alterada com sucesso']);
