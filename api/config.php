<?php
// ============================================================
//  GVC Signage — Configuração central
//  Lê variáveis do .env na raiz do projeto
// ============================================================

declare(strict_types=1);

// ── Carregar .env ─────────────────────────────────────────────
// Lê o .env e garante que funciona mesmo com arquivos salvos no
// Windows (CRLF). FILE_IGNORE_NEW_LINES remove \n mas não \r.
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = str_replace(["\r", "\n"], '', $line); // remove CRLF/LF
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        // Remove aspas opcionais: VAR="valor" ou VAR='valor'
        if (
            strlen($val) >= 2 &&
            (($val[0] === '"'  && $val[-1] === '"') ||
                ($val[0] === "'"  && $val[-1] === "'"))
        ) {
            $val = substr($val, 1, -1);
        }
        $_ENV[$key] = $val;
        putenv("$key=$val");
    }
}

// Helper — lê variável de ambiente com fallback
function env(string $key, mixed $default = null): mixed
{
    if (isset($_ENV[$key])) return $_ENV[$key];
    $v = getenv($key);
    return ($v !== false && $v !== '') ? $v : $default;
}

// ── Constantes da aplicação ───────────────────────────────────
define('DB_HOST',       env('DB_HOST',    'localhost'));
define('DB_PORT',   (int)env('DB_PORT',    3306));
define('DB_NAME',       env('DB_NAME',    'db_gvc_display'));
define('DB_USER',       env('DB_USER',    'root'));
define('DB_PASS',       env('DB_PASS',    ''));
define('APP_URL',  rtrim(env('APP_URL',   'http://localhost/gvc_display'), '/'));
define('JWT_SECRET',    env('JWT_SECRET', 'gvc_secret_fallback_local'));
define('JWT_EXPIRY', (int)env('JWT_EXPIRY', 28800));
define('UPLOAD_MAX_MB', (int)env('UPLOAD_MAX_MB', 50));

// Força os limites de upload do PHP em runtime
// (sobrescreve o php.ini padrão do XAMPP que é 2MB)
$_mb = UPLOAD_MAX_MB;
@ini_set('upload_max_filesize', "{$_mb}M");
@ini_set('post_max_size',       "{$_mb}M");
@ini_set('memory_limit',        '256M');
unset($_mb);
define('APP_DEBUG', filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));

define('UPLOAD_DIR',       __DIR__ . '/../uploads/');
define('UPLOAD_IMG_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_VID_TYPES', ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-matroska', 'video/x-msvideo', 'video/avi', 'video/x-ms-wmv', 'video/3gpp', 'application/octet-stream']);

// ── PDO Singleton ─────────────────────────────────────────────
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
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
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Erro de conexão com banco de dados',
            'detail'  => APP_DEBUG ? $e->getMessage() : 'Verifique o .env',
        ]);
        exit;
    }
    return $pdo;
}