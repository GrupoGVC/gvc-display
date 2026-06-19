<?php

namespace App\Controllers;

use App\Core\Controller;
use PDO;
use Throwable;

class TvController extends Controller
{
    private ?PDO $pdo = null;

    public function show(array $params = []): void
    {
        $this->noCacheHeaders();

        try {
            $slug = $this->resolveSlug($params);
            $device = $this->findDeviceBySlug($slug);

            if (!$device) {
                $device = $this->createDevice($slug);
            }

            if (empty($device['token'])) {
                $token = bin2hex(random_bytes(32));
                $this->updateDeviceToken((int) $device['id'], $token);
                $device['token'] = $token;
            }

            $this->renderPlayer($device);
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Erro TV</title></head>';
            echo '<body style="font-family:Arial,sans-serif;background:#0d1117;color:#fff;padding:32px">';
            echo '<h1>Erro ao abrir a TV</h1>';
            echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p style="color:#94a3b8">Verifique o .env, a porta do MySQL e se o banco db_gvc_display está acessível.</p>';
            echo '</body></html>';
        }
    }

    private function noCacheHeaders(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private function resolveSlug(array $params): string
    {
        $slug = $params['slug'] ?? $params[0] ?? null;

        if (!$slug && !empty($_COOKIE['gvc_tv_slug'])) {
            $slug = $_COOKIE['gvc_tv_slug'];
        }

        if (!$slug) {
            $slug = 'tv-' . bin2hex(random_bytes(6));
        }

        $slug = strtolower((string) $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = trim(preg_replace('/\-+/', '-', $slug), '-');

        if ($slug === '') {
            $slug = 'tv-' . bin2hex(random_bytes(6));
        }

        setcookie('gvc_tv_slug', $slug, [
            'expires' => time() + (10 * 365 * 24 * 60 * 60),
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax'
        ]);

        return $slug;
    }

    private function findDeviceBySlug(string $slug): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM devices WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function createDevice(string $slug): array
    {
        $token = bin2hex(random_bytes(32));
        $suffix = strtoupper(substr(preg_replace('/^tv\-/', '', $slug), -6));
        $name = 'TV ' . ($suffix ?: strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)));

        $stmt = $this->db()->prepare(
            'INSERT INTO devices (name, location, slug, token, group_id, playlist_id, status, last_ping, created_at)
             VALUES (:name, NULL, :slug, :token, NULL, NULL, "offline", NULL, NOW())'
        );

        $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'token' => $token,
        ]);

        return $this->findDeviceBySlug($slug) ?: [
            'id' => (int) $this->db()->lastInsertId(),
            'name' => $name,
            'slug' => $slug,
            'token' => $token,
            'playlist_id' => null,
        ];
    }

    private function updateDeviceToken(int $id, string $token): void
    {
        $stmt = $this->db()->prepare('UPDATE devices SET token = :token WHERE id = :id');
        $stmt->execute(['token' => $token, 'id' => $id]);
    }

    private function renderPlayer(array $device): void
    {
        $root = $this->rootPath();
        $view = $root . '/resources/views/player.html';

        if (!is_file($view)) {
            $view = $root . '/html/player.html';
        }

        if (!is_file($view)) {
            throw new \RuntimeException('Arquivo player.html não encontrado em resources/views ou html.');
        }

        $html = file_get_contents($view);
        $baseUrl = $this->baseUrl();

        $vars = '<script>' . PHP_EOL .
            'window.__DEVICE_TOKEN__ = ' . json_encode($device['token'] ?? '') . ';' . PHP_EOL .
            'window.__DEVICE_SLUG__ = ' . json_encode($device['slug'] ?? '') . ';' . PHP_EOL .
            'window.__BASE_URL__ = ' . json_encode($baseUrl) . ';' . PHP_EOL .
            '</script>';

        $pattern = '#<script>\s*/\*\s*GVC_TV_VARS\s*\*/.*?</script>#s';
        $newHtml = preg_replace($pattern, $vars, $html, 1, $count);
        $html = $newHtml ?? $html;

        if ($count === 0) {
            $html = str_replace('</head>', $vars . PHP_EOL . '</head>', $html);
        }

        $assetVersion = (string) time();

        $replacements = [
            'src="../js/player.js"' => 'src="' . $baseUrl . '/resources/js/player.js?v=' . $assetVersion . '"',
            "src='../js/player.js'" => "src='" . $baseUrl . "/resources/js/player.js?v=" . $assetVersion . "'",
            'src="/resources/js/player.js"' => 'src="' . $baseUrl . '/resources/js/player.js?v=' . $assetVersion . '"',
            'href="../css/player.css"' => 'href="' . $baseUrl . '/resources/css/player.css?v=' . $assetVersion . '"',
            "href='../css/player.css'" => "href='" . $baseUrl . "/resources/css/player.css?v=" . $assetVersion . "'",
            'href="/resources/css/player.css"' => 'href="' . $baseUrl . '/resources/css/player.css?v=' . $assetVersion . '"',
            'src="" id="player-logo"' => 'src="' . $baseUrl . '/assets/logos/logo_gvc_display_192.png" id="player-logo"',
        ];

        $html = strtr($html, $replacements);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    private function db(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $env = $this->env();
        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = $env['DB_PORT'] ?? '3307';
        $name = $env['DB_NAME'] ?? 'db_gvc_display';
        $user = $env['DB_USER'] ?? 'root';
        $pass = $env['DB_PASS'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->pdo;
    }

    private function env(): array
    {
        $env = $_ENV;
        $file = $this->rootPath() . '/.env';

        if (!is_file($file)) {
            return $env;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $env;
    }

    private function rootPath(): string
    {
        return dirname(__DIR__, 2);
    }

    private function baseUrl(): string
    {
        $env = $this->env();
        $basePath = $env['APP_BASE_PATH'] ?? null;

        if (!$basePath) {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $basePath = rtrim(dirname($script), '/');
            if (str_ends_with($basePath, '/public')) {
                $basePath = substr($basePath, 0, -7);
            }
            if ($basePath === '/' || $basePath === '.' || $basePath === '\\') {
                $basePath = '';
            }
        }

        $basePath = '/' . trim((string) $basePath, '/');
        if ($basePath === '/') {
            $basePath = '';
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443);

        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return rtrim($scheme . '://' . $host . $basePath, '/');
    }
}
