<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

if (method() !== 'POST') json_err('Método não permitido', 405);

$email = req('email');
$pass  = req('password');

if (!$email || !$pass) json_err('E-mail e senha são obrigatórios');

$stmt = db()->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($pass, $user['password_hash'])) {
    log_activity('login_failed', null, $email);
    json_err('E-mail ou senha inválidos', 401);
}

$token = jwt_create(['sub' => $user['id'], 'email' => $user['email'], 'name' => $user['name']]);
log_activity('login', $user['id'], $user['email']);

json_ok(['token' => $token, 'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']]]);
