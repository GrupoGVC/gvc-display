<?php
namespace App\Controllers;

use App\Core\{Controller, Database};
use App\Models\Device;

class TvController extends Controller
{
    public function show(array $params): void
    {
        $slug = strtolower(trim($params['slug'] ?? ''));

        if (!$slug) {
            $saved = strtolower(trim($_COOKIE['gvc_tv_slug'] ?? ''));
            if ($saved && preg_match('/^tv-[a-f0-9]{12}$/', $saved)) {
                $slug = $saved;
            } else {
                $slug = 'tv-' . bin2hex(random_bytes(6));
                setcookie('gvc_tv_slug', $slug, time() + 315360000, '/');
            }
        }

        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $slug)) {
            http_response_code(400); echo 'Slug inválido'; exit;
        }

        $model  = new Device();
        $device = $model->findBySlug($slug);

        if (!$device) {
            $token = bin2hex(random_bytes(16));
            $model->create([
                'name'   => 'TV ' . strtoupper(substr($slug, -6)),
                'slug'   => $slug,
                'token'  => $token,
                'status' => 'offline',
            ]);
        } else {
            $token = $device['token'];
        }

        // BASE URL para o player.js
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Usa o BASE_PATH já calculado pelo front controller
        $baseUrl = $scheme . '://' . $host . (defined('BASE_PATH') ? BASE_PATH : '');

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $html = file_get_contents(ROOT . '/resources/views/player.html');

        // Injeta meta tags PWA para o player (fullscreen, landscape, tema escuro)
        $pwaMeta = <<<PWAMETA
    <link rel="manifest" href="{$baseUrl}/assets/manifest.tv.json"/>
    <meta name="theme-color" content="#0d1117"/>
    <meta name="mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="black"/>
    <link rel="apple-touch-icon" href="{$baseUrl}/assets/icons/icon-192.png"/>
PWAMETA;
        $html = str_replace('</head>', $pwaMeta . "
  </head>", $html);

        // Injeta SW após as variáveis JS
        $swScript = '    <script src="' . $baseUrl . '/resources/js/pwa.js"></script>';
        $html = str_replace('</body>', $swScript . "
</body>", $html);
        // Monta o bloco de variáveis com valores reais
        $inject = '<script>'
            . 'window.__DEVICE_TOKEN__=' . json_encode($token)   . ';'
            . 'window.__DEVICE_SLUG__='  . json_encode($slug)    . ';'
            . 'window.__BASE_URL__='     . json_encode($baseUrl) . ';'
            . '</script>';

        // Substitui o bloco de defaults do player.html pelos valores reais
        // O bloco fica ANTES do src player.js → variáveis definidas quando o módulo carregar
        $html = preg_replace(
            '#<script>\s*/\*\s*GVC_TV_VARS.*?</script>#s',
            $inject,
            $html
        );

        // Corrige caminhos relativos para absolutos
        $html = str_replace(
            'src="../js/player.js"',
            'src="' . htmlspecialchars($baseUrl) . '/resources/js/player.js"',
            $html
        );
        $html = str_replace(
            'href="../css/player.css"',
            'href="' . htmlspecialchars($baseUrl) . '/resources/css/player.css"',
            $html
        );

        echo $html;
    }
}
