<?php
namespace App\Core;

class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode(
            ['success' => true, 'data' => $data],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    public static function error(string $message, int $status = 400): never
    {
        http_response_code($status);
        echo json_encode(
            ['success' => false, 'error' => $message],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    public static function notFound(string $msg = 'Não encontrado'): never
    {
        self::error($msg, 404);
    }

    public static function unauthorized(string $msg = 'Não autenticado'): never
    {
        self::error($msg, 401);
    }

    public static function forbidden(string $msg = 'Acesso negado'): never
    {
        self::error($msg, 403);
    }
}
