<?php
// ── Ambiente ───────────────────────────────────────────────────
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('UTC');

// Carrega .env se existir
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

function env(string $key, string $default = ''): string {
    return $_ENV[$key] ?? $default;
}

// ── Headers CORS/JSON ──────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Conexão PDO ────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'db_gvc_display');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
        exit;
    }
    return $pdo;
}

// ── Helpers ────────────────────────────────────────────────────
function json_ok(mixed $data = null): never {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function json_err(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function body(): array {
    static $body = null;
    if ($body !== null) return $body;
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
    return $body;
}

function req(string $key): string {
    return trim(body()[$key] ?? '');
}

function method(): string {
    return $_SERVER['REQUEST_METHOD'];
}

function action(): string {
    return $_GET['action'] ?? '';
}

function log_activity(string $act, ?int $userId = null, ?string $detail = null): void {
    try {
        $stmt = db()->prepare("INSERT INTO activity_logs (user_id, action, detail) VALUES (?,?,?)");
        $stmt->execute([$userId, $act, $detail]);
    } catch (Throwable) {}
}
