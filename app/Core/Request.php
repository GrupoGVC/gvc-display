<?php
namespace App\Core;

class Request
{
    private array $body;
    private array $query;
    private array $files;

    public function __construct()
    {
        $raw        = file_get_contents('php://input');
        $this->body = json_decode($raw ?: '{}', true) ?? [];
        if (empty($this->body) && !empty($_POST)) $this->body = $_POST;
        $this->query = $_GET;
        $this->files = $_FILES;
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
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
