<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Método não permitido', 405);

$b     = require_fields(['email', 'password']);
$email = s($b['email'], 180);
$pass  = (string)$b['password'];

$st = db()->prepare("SELECT id, name, email, password, role, active FROM users WHERE email = ? LIMIT 1");
$st->execute([$email]);
$user = $st->fetch();

$hash  = $user['password'] ?? '$2y$12$invalidpaddingthatnevermatch0000000000000';
$valid = password_verify($pass, $hash);

if (!$user || !$valid || !$user['active']) {
    log_act(0, 'login_failed', 'user', 0, $email);
    json_err('E-mail ou senha inválidos', 401);
}

$token = JWT::encode([
    'sub'   => (int)$user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $user['role'],
    'iat'   => time(),
    'exp'   => time() + JWT_EXPIRY,
], JWT_SECRET);

log_act((int)$user['id'], 'login', 'user', (int)$user['id']);

json_ok([
    'token'      => $token,
    'expires_in' => JWT_EXPIRY,
    'user'       => [
        'id'    => (int)$user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ],
]);
