<?php
// ============================================================
//  GVC Display — Front Controller
//  Único ponto de entrada de toda a aplicação.
// ============================================================

declare(strict_types=1);
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('UTC');

define('ROOT', dirname(__DIR__));

// ── 1. Carrega .env PRIMEIRO (antes de qualquer define que dependa dele) ──
$envFile = ROOT . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

// ── 2. BASE_PATH — detecta automaticamente se não estiver no .env ────────
// Exemplo: acessando http://localhost/gvc-display-mvc/ → BASE_PATH = /gvc-display-mvc
// Exemplo: acessando https://display.drc-gvc.tech/    → BASE_PATH = (vazio)
if (!empty($_ENV['APP_BASE_PATH'])) {
    define('BASE_PATH', rtrim($_ENV['APP_BASE_PATH'], '/'));
} else {
    // Auto-detect: SCRIPT_NAME pode ser /gvc-display-mvc/index.php (raiz)
    // ou /gvc-display-mvc/public/index.php (via public/)
    // Normalizamos para sempre obter /gvc-display-mvc
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    // Remove /public/index.php ou /index.php do final
    $basePath = preg_replace('#/(public/)?index\.php$#', '', $scriptName);
    $basePath = rtrim($basePath, '/');
    define('BASE_PATH', $basePath === '' || $basePath === '.' ? '' : $basePath);
}

// ── 3. Autoloader PSR-4 (sem Composer) ───────────────────────────────────
spl_autoload_register(function (string $class): void {
    $rel  = str_replace(['App\\', '\\'], ['', '/'], $class);
    $file = ROOT . '/app/' . $rel . '.php';
    if (file_exists($file)) require_once $file;
});

// ── 4. CORS ───────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

use App\Core\Router;
use App\Controllers\TvController;

$router = new Router();

// ── 5. Rotas da TV (serve HTML, não JSON) ─────────────────────────────────
$router->get('/tv',       [TvController::class, 'show']);
$router->get('/tv/',      [TvController::class, 'show']);
$router->get('/tv/:slug', [TvController::class, 'show']);

// ── 6. Rotas da API ───────────────────────────────────────────────────────
require ROOT . '/routes/api.php';

// ── 7. Páginas admin (servem HTML) ────────────────────────────────────────
$router->get('/', function() {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $html = file_get_contents(ROOT . '/resources/views/index.html');
    // Injeta BASE_PATH nas views para que os assets carreguem corretamente
    $base = BASE_PATH;
    $html = str_replace('href="css/', "href=\"{$base}/resources/css/", $html);
    $html = str_replace('src="js/',   "src=\"{$base}/resources/js/",   $html);
    $html = str_replace('href="assets/', "href=\"{$base}/assets/",     $html);
    $html = str_replace('src="assets/',  "src=\"{$base}/assets/",      $html);
    echo $html;
    exit;
});

$router->get('/login', function() {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $html = file_get_contents(ROOT . '/resources/views/login.html');
    $base = BASE_PATH;
    $html = str_replace('href="css/', "href=\"{$base}/resources/css/", $html);
    $html = str_replace('src="js/',   "src=\"{$base}/resources/js/",   $html);
    $html = str_replace('href="assets/', "href=\"{$base}/assets/",     $html);
    echo $html;
    exit;
});

// ── 8. Dispatch ───────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$router->dispatch($method, $uri);
