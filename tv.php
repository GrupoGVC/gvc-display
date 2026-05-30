<?php
/**
 * GVC Display — Rota curta para TVs
 * Acesso: display.drc-gvc.tech/tv/nome-da-tv
 *
 * - Primeira vez (sem token no localStorage): redireciona para player (tela de pareamento)
 * - Com slug cadastrado: player carrega direto via token
 */
declare(strict_types=1);
require_once __DIR__ . '/api/config.php';

$slug = trim($_GET['slug'] ?? '', '/');
$slug = preg_replace('/[^a-z0-9_-]/i', '', $slug);

// Sem slug: redireciona para player genérico (gera código na TV)
if (!$slug) {
    header('Location: ' . APP_URL . '/html/player.html');
    exit;
}

// Busca dispositivo pelo slug
try {
    $stmt = db()->prepare('SELECT token, name FROM devices WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $device = $stmt->fetch();
} catch (Exception $e) {
    $device = null;
}

if ($device) {
    // Slug encontrado → redireciona direto para o player com o token
    $url = APP_URL . '/html/player.html?token=' . urlencode($device['token']);
    header('Location: ' . $url);
    exit;
}

// Slug não cadastrado → abre player (mostrará tela de pareamento)
// Passa o slug como dica para o player registrar quando parear
$url = APP_URL . '/html/player.html?slug=' . urlencode($slug);
header('Location: ' . $url);
exit;
