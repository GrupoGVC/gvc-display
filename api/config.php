<?php
// ============================================================
//  GVC Signage — Configuração central
//  Lê variáveis do .env na raiz do projeto
// ============================================================

declare(strict_types=1);

// ── Carregar .env ─────────────────────────────────────────────
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val);
        }
    }
}

// Helper para ler variável com fallback
function env(string $key, mixed $default = null): mixed {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ── Constantes da aplicação ───────────────────────────────────
define('DB_HOST',    env('DB_HOST',    'localhost'));
define('DB_PORT',    (int) env('DB_PORT', 3306));
define('DB_NAME',    env('DB_NAME',    'gvc_signage'));
define('DB_USER',    env('DB_USER',    'root'));
define('DB_PASS',    env('DB_PASS',    ''));
define('APP_URL',    rtrim(env('APP_URL', 'http://localhost/gvc-signage'), '/'));
define('JWT_SECRET', env('JWT_SECRET', 'changeme'));
define('JWT_EXPIRY', (int) env('JWT_EXPIRY', 28800));
define('UPLOAD_MAX_MB', (int) env('UPLOAD_MAX_MB', 50));
define('APP_DEBUG',  filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));

define('UPLOAD_DIR',      __DIR__ . '/../uploads/');
define('UPLOAD_IMG_TYPES', ['image/jpeg','image/png','image/gif','image/webp']);
define('UPLOAD_VID_TYPES', ['video/mp4','video/webm','video/ogg']);

// ── PDO Singleton ─────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_NAME
    );
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    } catch (PDOException $e) {
        http_response_code(503);
        die(json_encode(['success' => false, 'error' => 'Erro de conexão com banco de dados']));
    }
    return $pdo;
}
