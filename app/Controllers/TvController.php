<?php
namespace App\Controllers;

use PDO;
use Throwable;

/**
 * TvController — página da TV.
 *
 * Fluxo:
 * 1. TV acessa /tv/ ou /tv/{slug}
 * 2. Se existe cookie 'gvc_tv_token' e ele bate com uma TV pareada → renderiza player com o token
 * 3. Caso contrário → renderiza player em modo pareamento (sem token)
 *
 * NUNCA cria devices automaticamente. O admin deve criar a TV no painel.
 */
class TvController
{
    private ?PDO $pdo = null;

    public function show(array $params = []): void
    {
        $this->noCacheHeaders();

        try {
            $device = $this->resolveDevice($params);
            $this->renderPlayer($device);
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Erro TV</title></head>';
            echo '<body style="font-family:Arial,sans-serif;background:#0d1117;color:#fff;padding:32px">';
            echo '<h1>Erro ao abrir a TV</h1>';
            echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '</body></html>';
        }
    }

    /**
     * Tenta identificar a TV. Retorna array com dados do device ou null (não pareada).
     * Não cria nada.
     */
    private function resolveDevice(array $params): ?array
    {
        // 1. Se veio /tv/{slug} — busca por slug
        $slug = $params['slug'] ?? $params[0] ?? null;
        if ($slug) {
            $slug = $this->sanitizeSlug($slug);
            $device = $this->findBy('slug', $slug);
            if ($device && !empty($device['token'])) {
                setcookie('gvc_tv_token', $device['token'], $this->cookieOpts());
                return $device;
            }
        }

        // 2. Cookie de token — TV já pareada anteriormente
        $token = $_COOKIE['gvc_tv_token'] ?? null;
        if ($token) {
            $device = $this->findBy('token', $token);
            if ($device) return $device;
            // Token no cookie mas não existe no banco → despareada, apaga cookie
            setcookie('gvc_tv_token', '', ['expires' => time() - 3600, 'path' => '/']);
        }

        // 3. Não pareada — retorna null (o player mostrará tela de pareamento)
        return null;
    }

    private function findBy(string $col, string $value): ?array
    {
        $st = $this->db()->prepare("SELECT * FROM devices WHERE {$col} = :v LIMIT 1");
        $st->execute(['v' => $value]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        return trim(preg_replace('/\-+/', '-', $slug), '-');
    }

    private function cookieOpts(): array
    {
        return [
            'expires'  => time() + (10 * 365 * 24 * 60 * 60),
            'path'     => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ];
    }

    private function noCacheHeaders(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private function renderPlayer(?array $device): void
    {
        $root = dirname(__DIR__, 2);
        $view = $root . '/resources/views/player.html';

        if (!is_file($view)) {
            throw new \RuntimeException('player.html não encontrado.');
        }

        // Gera/pega client_id persistente (fingerprint da TV no navegador)
        $clientId = $_COOKIE['gvc_tv_client'] ?? null;
        if (!$clientId) {
            $clientId = bin2hex(random_bytes(16));
            setcookie('gvc_tv_client', $clientId, $this->cookieOpts());
        }

        $html    = file_get_contents($view);
        $baseUrl = $this->baseUrl();
        $v       = (string) (filemtime($root . '/resources/js/player.js') ?: time());

        // Injeta variáveis JS da TV
        $vars = '<script>' . PHP_EOL
            . 'window.__DEVICE_TOKEN__ = ' . json_encode($device['token'] ?? null) . ';' . PHP_EOL
            . 'window.__DEVICE_SLUG__  = ' . json_encode($device['slug']  ?? null) . ';' . PHP_EOL
            . 'window.__CLIENT_ID__    = ' . json_encode($clientId) . ';' . PHP_EOL
            . 'window.__BASE_URL__     = ' . json_encode($baseUrl) . ';' . PHP_EOL
            . 'window.__PAIRED__       = ' . ($device ? 'true' : 'false') . ';' . PHP_EOL
            . '</script>';

        $html = preg_replace(
            '#<script[^>]*>\s*/\*\s*GVC_TV_VARS\s*\*/[^<]*</script>#s',
            $vars, $html, 1, $replaced
        );
        if (!$replaced) {
            $html = str_replace('</head>', $vars . PHP_EOL . '</head>', $html);
        }

        // Resolve caminhos
        $html = str_replace(
            ['href="../css/player.css"', "href='../css/player.css'"],
            'href="' . $baseUrl . '/resources/css/player.css?v=' . $v . '"',
            $html
        );
        $html = str_replace(
            ['src="../js/player.js"', "src='../js/player.js'"],
            'src="' . $baseUrl . '/resources/js/player.js?v=' . $v . '"',
            $html
        );
        $html = str_replace(
            'src="" id="player-logo"',
            'src="' . $baseUrl . '/assets/logos/logo_gvc_display_192.png" id="player-logo"',
            $html
        );
        $html = str_replace('href="assets/', 'href="' . $baseUrl . '/assets/', $html);
        $html = str_replace('src="assets/',  'src="'  . $baseUrl . '/assets/', $html);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    private function db(): PDO
    {
        if ($this->pdo instanceof PDO) return $this->pdo;

        $env  = $this->env();
        $dsn  = "mysql:host=" . ($env['DB_HOST'] ?? '127.0.0.1')
              . ";port="     . ($env['DB_PORT'] ?? '3307')
              . ";dbname="   . ($env['DB_NAME'] ?? 'db_gvc_display')
              . ";charset=utf8mb4";

        $this->pdo = new PDO($dsn, $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $this->pdo;
    }

    private function env(): array
    {
        $env  = $_ENV;
        $file = dirname(__DIR__, 2) . '/.env';
        if (!is_file($file)) return $env;

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
        return $env;
    }

    private function baseUrl(): string
    {
        $env      = $this->env();
        $basePath = $env['APP_BASE_PATH'] ?? '';

        if (!$basePath) {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $basePath = rtrim(dirname($script), '/');
            if (str_ends_with($basePath, '/public')) {
                $basePath = substr($basePath, 0, -7);
            }
            if (in_array($basePath, ['/', '.', '\\'], true)) $basePath = '';
        }

        $basePath = '/' . trim($basePath, '/');
        if ($basePath === '/') $basePath = '';

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['SERVER_PORT'] ?? null) == 443);

        return rtrim(($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath, '/');
    }
}
