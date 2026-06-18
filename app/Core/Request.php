<?php
namespace App\Core;

class Request
{
    private array $body;
    private array $query;
    private array $files;

    public function __construct()
    {
        // Lê o body — php://input pode estar vazio em alguns configs do Apache
        $raw = file_get_contents('php://input');

        // Tenta JSON primeiro
        if ($raw && str_contains($raw, '{')) {
            $decoded = json_decode($raw, true);
            $this->body = is_array($decoded) ? $decoded : [];
        }

        // Fallback 1: $_POST (form-urlencoded)
        if (empty($this->body) && !empty($_POST)) {
            $this->body = $_POST;
        }

        // Fallback 2: parse_str quando Content-Type é application/x-www-form-urlencoded
        if (empty($this->body) && $raw) {
            $ct = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($ct, 'application/x-www-form-urlencoded')) {
                parse_str($raw, $parsed);
                $this->body = $parsed ?: [];
            }
        }

        $this->query = $_GET;
        $this->files = $_FILES;
    }

    public function method(): string
    {
        // Em alguns configs do Apache com mod_rewrite, REQUEST_METHOD
        // pode ser sobrescrito. Verifica múltiplas fontes.
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Suporte a method override via header (usado por alguns proxies/firewalls)
        $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']
            ?? $_SERVER['HTTP_X_METHOD_OVERRIDE']
            ?? '';
        if ($override) $method = $override;

        return strtoupper($method);
    }

    public function body(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $this->body;
        return $this->body[$key] ?? $default;
    }

    public function query(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $this->query;
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = ''): string
    {
        $v = $this->body[$key] ?? $this->query[$key] ?? $default;
        // Não faz trim em campos de senha para preservar espaços intencionais
        if ($key === 'password' || $key === 'current_password' || $key === 'new_password') {
            return (string)$v;
        }
        return trim((string)$v);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int)($this->body[$key] ?? $this->query[$key] ?? $default);
    }

    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        return ($f && $f['error'] === UPLOAD_ERR_OK) ? $f : null;
    }

    public function bearerToken(): string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if (!$h && function_exists('apache_request_headers')) {
            $ah = apache_request_headers();
            $h  = $ah['Authorization'] ?? $ah['authorization'] ?? '';
        }
        if (preg_match('/^Bearer\s+(.+)$/i', $h, $m)) return $m[1];
        return $this->query('_token', '');
    }
}
