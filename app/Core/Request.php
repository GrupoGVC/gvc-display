<?php
namespace App\Core;

class Request
{
    private array $body;
    private array $query;
    private array $files;

    public function __construct()
    {
        // Inicializa sempre — evita "uninitialized property" no PHP 8.2
        $this->body  = [];
        $this->query = $_GET  ?? [];
        $this->files = $_FILES ?? [];

        $raw = (string) file_get_contents('php://input');

        // JSON body
        if ($raw !== '' && str_contains($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $this->body = $decoded;
            }
        }

        // Fallback: $_POST (form-urlencoded)
        if (empty($this->body) && !empty($_POST)) {
            $this->body = $_POST;
        }

        // Fallback: parse_str
        if (empty($this->body) && $raw !== '') {
            $ct = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($ct, 'application/x-www-form-urlencoded')) {
                parse_str($raw, $parsed);
                $this->body = is_array($parsed) ? $parsed : [];
            }
        }
    }

    public function method(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

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
        if (in_array($key, ['password', 'current_password', 'new_password'], true)) {
            return (string) $v;
        }
        return trim((string) $v);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) ($this->body[$key] ?? $this->query[$key] ?? $default);
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
