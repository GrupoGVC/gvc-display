<?php
// ── meta_injector.php ─────────────────────────────────────────
// Lê API_BASE_URL do .env e emite um <meta> tag se estiver preenchido.
// Se vazio (ambiente local), não emite nada — js/api.js usa auto-detect.
//
// Uso em arquivos .php:
//   <?php require_once __DIR__ . '/../api/meta_injector.php'; ?>

// Evita execução dupla
if (defined('GVC_META_INJECTED')) return;
define('GVC_META_INJECTED', true);

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

$apiUrl = trim($_ENV['API_BASE_URL'] ?? '');
if ($apiUrl !== '' && preg_match('#^https?://#', $apiUrl)) {
    echo '<meta name="gvc-api-url" content="' . htmlspecialchars($apiUrl, ENT_QUOTES) . '">' . "\n";
}
// Se vazio → não emite nada, js/api.js detecta automaticamente
