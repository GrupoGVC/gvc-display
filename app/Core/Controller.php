<?php
namespace App\Core;

abstract class Controller
{
    protected Request  $request;
    protected Response $response; // classe estática, mas mantemos para clareza

    public function __construct()
    {
        $this->request = new Request();
    }

    // Exige autenticação JWT — retorna o payload ou responde 401
    protected function auth(): array
    {
        $token   = $this->request->bearerToken();
        if (!$token) Response::unauthorized();

        $jwt     = new JWT();
        $payload = $jwt->decode($token);
        if (!$payload) Response::unauthorized('Token expirado ou inválido');

        return $payload;
    }

    protected function log(string $action, int $userId = 0, string $detail = ''): void
    {
        try {
            Database::connection()
                ->prepare("INSERT INTO activity_logs (user_id, action, detail) VALUES (?,?,?)")
                ->execute([$userId ?: null, $action, $detail ?: null]);
        } catch (\Throwable) {}
    }
}
