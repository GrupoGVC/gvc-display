<?php
declare(strict_types=1);
date_default_timezone_set('UTC');

// Suprimir warnings/notices em produção (evita quebrar JSON)
$debug = strtolower(env('APP_DEBUG', 'false'));
if ($debug !== 'true' && $debug !== '1') {
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ── Carrega .env ──────────────────────────────────────────────
function env(string $k, string $default = ''): string {
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        $path = __DIR__ . '/../.env';
        if (file_exists($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
                $_ENV[trim($key)] = trim(str_replace(["\r", "\n"], '', $val));
            }
        }
    }
    return $_ENV[$k] ?? $default;
}

// ── Constantes ────────────────────────────────────────────────
define('DB_HOST',      env('DB_HOST', '127.0.0.1'));
define('DB_PORT',      env('DB_PORT', '3306'));
define('DB_NAME',      env('DB_NAME', 'db_gvc_display'));
define('DB_USER',      env('DB_USER', 'root'));
define('DB_PASS',      env('DB_PASS', ''));
define('APP_URL',      rtrim(env('APP_URL', 'http://localhost/gvc-display'), '/'));
define('APP_DEBUG',    env('APP_DEBUG', 'false'));
define('JWT_SECRET',   env('JWT_SECRET', 'change_this_secret'));
define('JWT_EXPIRY',   (int)env('JWT_EXPIRY', '86400'));
define('UPLOAD_MAX_MB',(int)env('UPLOAD_MAX_MB', '50'));
define('UPLOAD_DIR',   __DIR__ . '/../uploads/');  // ← adicione esta linha

// Força limites de upload
@ini_set('upload_max_filesize', UPLOAD_MAX_MB . 'M');
@ini_set('post_max_size',       UPLOAD_MAX_MB . 'M');
@ini_set('memory_limit',        '256M');

// ── PDO singleton ─────────────────────────────────────────────
function db(): \PDO {
    static $pdo = null;
    if (!$pdo) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new \PDO($dsn, DB_USER, DB_PASS, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (\PDOException $e) {
            header('Content-Type: application/json');
            http_response_code(503);
            echo json_encode(['success' => false, 'error' => 'Erro de conexão com banco de dados']);
            exit;
        }
    }
    return $pdo;
}

// ── UPLOAD types ──────────────────────────────────────────────
define('UPLOAD_IMG_TYPES', ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml']);
define('UPLOAD_VID_TYPES', ['video/mp4','video/webm','video/ogg','video/quicktime','video/x-matroska','video/x-msvideo','video/avi','video/x-ms-wmv','video/3gpp','application/octet-stream']);

// ── CORS ──────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
