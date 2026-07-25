<?php
// ============================================================
//  GVC Display — Front Controller
// ============================================================

declare(strict_types=1);
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('UTC');

// Limites de upload — fallback se .htaccess / .user.ini não aplicarem
@ini_set('upload_max_filesize', '110M');
@ini_set('post_max_size',       '120M');
@ini_set('max_execution_time',  '300');
@ini_set('max_input_time',      '300');
@ini_set('memory_limit',        '256M');

define('ROOT', __DIR__);

// .env
$envFile = ROOT . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

// BASE_PATH
if (!empty($_ENV['APP_BASE_PATH'])) {
    define('BASE_PATH', rtrim($_ENV['APP_BASE_PATH'], '/'));
} else {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath   = preg_replace('#/(public/)?index\.php$#', '', $scriptName);
    $basePath   = rtrim($basePath, '/');
    define('BASE_PATH', $basePath === '' || $basePath === '.' ? '' : $basePath);
}

// Autoloader
spl_autoload_register(function (string $class): void {
    $rel  = str_replace(['App\\', '\\'], ['', '/'], $class);
    $file = ROOT . '/app/' . $rel . '.php';
    if (file_exists($file)) require_once $file;
});

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

use App\Core\Router;
use App\Controllers\TvController;

$router = new Router();

// TV
$router->get('/tv',       [TvController::class, 'show']);
$router->get('/tv/',      [TvController::class, 'show']);
$router->get('/tv/:slug', [TvController::class, 'show']);

// API
require ROOT . '/routes/api.php';

// Serve HTML views
function serveView(string $file, string $base, bool $isAdmin = true, bool $injectSW = true): void {
    $html = file_get_contents(ROOT . '/resources/views/' . $file);
    $v = filemtime(ROOT . '/resources/css/main.css') ?: time();

    $html = preg_replace('/href="css\/([^"]+)"/', 'href="' . $base . '/resources/css/$1?v=' . $v . '"', $html);
    $html = preg_replace('/src="js\/([^"]+)"/',   'src="'  . $base . '/resources/js/$1?v='  . $v . '"', $html);
    $html = str_replace('href="assets/', "href=\"{$base}/assets/", $html);
    $html = str_replace('src="assets/',  "src=\"{$base}/assets/",  $html);

    // PWA meta
    $manifest = $isAdmin ? 'manifest.admin.json' : 'manifest.tv.json';
    $pwaMeta  = <<<HTML
    <link rel="manifest" href="{$base}/assets/{$manifest}"/>
    <meta name="theme-color" content="#00AA8E"/>
    <meta name="mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
    <meta name="apple-mobile-web-app-title" content="GVC Display"/>
    <link rel="apple-touch-icon" href="{$base}/assets/icons/icon-192.png"/>
    HTML;
    $html = str_replace('</head>', $pwaMeta . "\n  </head>", $html);

    // SW opcional (não injetar no login)
    if ($injectSW) {
        $html = str_replace('</body>', "<script src=\"{$base}/resources/js/pwa.js\"></script>\n</body>", $html);
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $html;
    exit;
}

$router->get('/', function() {
    serveView('index.html', BASE_PATH, true, true);
});

$router->get('/login', function() {
    serveView('login.html', BASE_PATH, true, false);  // SEM SW no login
});

// Service Worker
$router->get('/sw.js', function() {
    $file = ROOT . '/resources/js/sw.js';
    if (!file_exists($file)) { http_response_code(404); exit; }
    $sw = file_get_contents($file);
    $sw = str_replace("'/gvc-display/", "'" . BASE_PATH . "/", $sw);
    $sw = str_replace('"/gvc-display/', '"' . BASE_PATH . '/', $sw);
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Service-Worker-Allowed: ' . BASE_PATH . '/');
    echo $sw;
    exit;
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
