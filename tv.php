<?php
/**
 * tv.php — Roteador da TV
 * Acesso: /tv/  ou  /tv/{slug}  ou  tv.php?slug=xxx
 */
error_reporting(E_ERROR | E_PARSE);

// 1. Carrega .env ─────────────────────────────────────────────
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

// 2. Slug — resolve ANTES de qualquer output ──────────────────
$slug = strtolower(trim($_GET['slug'] ?? ''));

if ($slug === '') {
    $saved = strtolower(trim($_COOKIE['gvc_tv_slug'] ?? ''));
    if ($saved !== '' && preg_match('/^tv-[a-f0-9]{12}$/', $saved)) {
        $slug = $saved;
    } else {
        $slug = 'tv-' . bin2hex(random_bytes(6));
        setcookie('gvc_tv_slug', $slug, time() + 315360000, '/');
    }
}

if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $slug)) {
    http_response_code(400);
    die('<h2 style="font-family:sans-serif;color:#ff4f6a">Slug inválido</h2>');
}

// 3. Banco ────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? '127.0.0.1',
            $_ENV['DB_PORT'] ?? '3306',
            $_ENV['DB_NAME'] ?? 'db_gvc_display'),
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(503);
    die('<!doctype html><html><head><meta charset="UTF-8"><title>Erro</title>
    <style>body{background:#0d1117;color:#fff;font-family:system-ui;display:flex;
    align-items:center;justify-content:center;height:100vh;margin:0;flex-direction:column;gap:16px}
    h2{color:#ff4f6a}p{color:#6b7280;text-align:center}</style></head><body>
    <h2>⚠️ Banco indisponível</h2>
    <p>MySQL não está respondendo.<br>Verifique se está rodando e o .env está correto.</p>
    </body></html>');
}

// 4. Busca ou cria device ─────────────────────────────────────
$row = $pdo->prepare('SELECT token FROM devices WHERE slug = ?');
$row->execute([$slug]);
$dev = $row->fetch();

if ($dev) {
    $token = $dev['token'];
} else {
    $token = bin2hex(random_bytes(16));
    $name  = 'TV ' . strtoupper(substr($slug, -6));
    try {
        $pdo->prepare("INSERT INTO devices (name,slug,token,status) VALUES (?,?,?,'offline')")
            ->execute([$name, $slug, $token]);
    } catch (PDOException $e) {
        $row2  = $pdo->prepare('SELECT token FROM devices WHERE slug = ?');
        $row2->execute([$slug]);
        $d2    = $row2->fetch();
        $token = $d2 ? $d2['token'] : $token;
    }
}

// 5. BASE URL do projeto ───────────────────────────────────────
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$basePath = rtrim(preg_replace('#/tv(/.*)?$#', '', $uri), '/');
$BASE_URL = $scheme . '://' . $host . $basePath;

// 6. Lê e processa o player.html ──────────────────────────────
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$html = file_get_contents(__DIR__ . '/html/player.html');

// Injeta as variáveis ANTES do bloco de script existente no player.html
// Isso garante que __DEVICE_TOKEN__, __DEVICE_SLUG__ e __BASE_URL__
// estão definidos quando o player.js carregar
$varScript = '<script>' . PHP_EOL
    . '    window.__DEVICE_TOKEN__ = ' . json_encode($token) . ';' . PHP_EOL
    . '    window.__DEVICE_SLUG__  = ' . json_encode($slug)  . ';' . PHP_EOL
    . '    window.__BASE_URL__     = ' . json_encode($BASE_URL) . ';' . PHP_EOL
    . '  </script>';

// Substitui o bloco de defaults do player.html pelo bloco com valores reais
$html = str_replace(
    '<script>
    /* Variáveis injetadas pelo tv.php */
    window.__DEVICE_TOKEN__ = window.__DEVICE_TOKEN__ || \'\';
    window.__DEVICE_SLUG__  = window.__DEVICE_SLUG__  || \'\';
    window.__BASE_URL__     = window.__BASE_URL__      || \'\';
  </script>',
    $varScript,
    $html
);

// Corrige o caminho do player.js para absoluto
$html = str_replace(
    'src="../js/player.js"',
    'src="' . htmlspecialchars($BASE_URL, ENT_QUOTES) . '/js/player.js"',
    $html
);

echo $html;
