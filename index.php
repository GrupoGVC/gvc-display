<?php
// ============================================================
//  GVC Display — Front Controller
//  Único ponto de entrada de toda a aplicação.
// ============================================================

declare(strict_types=1);
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('UTC');

define('ROOT', __DIR__);

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
// ── Helper: serve view HTML com assets resolvidos + PWA meta ───
function serveView(string $file, string $base, bool $isAdmin = true): void {
    $html = file_get_contents(ROOT . '/resources/views/' . $file);

    // Resolve paths relativos para absolutos
    $html = str_replace('href="css/',    "href=\"{$base}/resources/css/",  $html);
    $html = str_replace('src="js/',      "src=\"{$base}/resources/js/",    $html);
    $html = str_replace('href="assets/', "href=\"{$base}/assets/",         $html);
    $html = str_replace('src="assets/',  "src=\"{$base}/assets/",          $html);

    // Injeta meta tags PWA antes de </head>
    $manifest = $isAdmin ? 'manifest.admin.json' : 'manifest.tv.json';
    $pwaMeta  = <<<HTML
    <!-- PWA -->
    <link rel="manifest" href="{$base}/assets/{$manifest}"/>
    <meta name="theme-color" content="#4f8cff"/>
    <meta name="mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
    <meta name="apple-mobile-web-app-title" content="GVC Display"/>
    <link rel="apple-touch-icon" href="{$base}/assets/icons/icon-192.png"/>
    HTML;

    $html = str_replace('</head>', $pwaMeta . "
  </head>", $html);

    // Injeta registro do Service Worker antes de </body>
    $swScript = <<<HTML
    <script src="{$base}/resources/js/pwa.js"></script>
    HTML;
    $html = str_replace('</body>', $swScript . "
</body>", $html);

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $html;
    exit;
}

$router->get('/', function() {
    serveView('index.html', BASE_PATH, true);
});

$router->get('/login', function() {
    serveView('login.html', BASE_PATH, true);
});

// ── 8. Dispatch ───────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'] ?? '/';

// ── Service Worker — servido com headers corretos ─────────────────────────
$router->get('/sw.js', function() {
    $file = ROOT . '/resources/js/sw.js';
    if (!file_exists($file)) { http_response_code(404); exit; }
    $sw = file_get_contents($file);
    // Substitui o base path hardcoded pelo valor real do ambiente
    $sw = str_replace("'/gvc-display/", "'" . BASE_PATH . "/", $sw);
    $sw = str_replace('"/gvc-display/', '"' . BASE_PATH . '/', $sw);
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Service-Worker-Allowed: ' . BASE_PATH . '/');
    echo $sw;
    exit;
});

$router->dispatch($method, $uri);
