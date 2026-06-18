<?php
namespace App\Controllers;

use App\Core\{Controller, JWT, Response};
use App\Core\Database;

class AuthController extends Controller
{
    public function login(): void
    {
        if ($this->request->method() !== 'POST') Response::error('POST esperado', 405);

        $email = $this->request->input('email');
        $pass  = $this->request->input('password');
        if (!$email || !$pass) Response::error('E-mail e senha são obrigatórios');

        $db   = Database::connection();
        $stmt = $db->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Diagnóstico detalhado — retorna mensagem específica para debug
        if (!$user) {
            $this->log('login_failed', 0, $email);
            Response::unauthorized('Usuário não encontrado: ' . $email);
        }

        if (!password_verify($pass, $user['password_hash'])) {
            $this->log('login_failed', 0, $email);
            // Dica: remove após resolver o problema
            $hint = strlen($pass) . ' chars, hash len=' . strlen($user['password_hash']);
            Response::unauthorized('Senha incorreta (' . $hint . ')');
        }

        $jwt   = new JWT();
        $token = $jwt->encode(['sub' => $user['id'], 'email' => $user['email'], 'name' => $user['name']]);
        $this->log('login', $user['id'], $user['email']);

        Response::json(['token' => $token, 'user' => ['id' => $user['id'], 'name' => $user['name']]]);
    }

    public function changePassword(): void
    {
        $payload = $this->auth();
        $current = $this->request->input('current_password');
        $new     = $this->request->input('new_password');
        if (!$current || !$new) Response::error('Preencha os dois campos');

        $db   = Database::connection();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id=?");
        $stmt->execute([$payload['sub']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password_hash'])) {
            Response::error('Senha atual incorreta');
        }

        $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
           ->execute([password_hash($new, PASSWORD_BCRYPT), $payload['sub']]);
        Response::json(['message' => 'Senha alterada com sucesso']);
    }
}
