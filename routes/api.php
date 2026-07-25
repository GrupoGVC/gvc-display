<?php
// ============================================================
//  GVC Display — Definição de rotas da API
//  Todas as rotas são registradas aqui, centralizadas.
//  Formato: $router->METHOD('uri/:param', [Controller::class, 'action'])
// ============================================================

use App\Controllers\{
    AuthController, DeviceController, GroupController,
    PlaylistController, ItemController, MediaController,
    PairingController, DashboardController
};

// ── Auth ──────────────────────────────────────────────────────
$router->post('/api/auth/login',           [AuthController::class, 'login']);
$router->post('/api/auth/password',        [AuthController::class, 'changePassword']);

// ── Dashboard ─────────────────────────────────────────────────
$router->get('/api/dashboard',             [DashboardController::class, 'index']);

// ── Devices ───────────────────────────────────────────────────
$router->get('/api/devices',               [DeviceController::class, 'index']);
$router->post('/api/devices',              [DeviceController::class, 'store']);
// Rotas específicas ANTES de :id para evitar conflito de captura
$router->post('/api/devices/heartbeat',    [DeviceController::class, 'heartbeat']);
$router->post('/api/devices/broadcast',    [DeviceController::class, 'broadcast']);
$router->get('/api/devices/tv-playlist',   [DeviceController::class, 'tvPlaylist']);
$router->get('/api/devices/:id',           [DeviceController::class, 'show']);
$router->put('/api/devices/:id',           [DeviceController::class, 'update']);
$router->delete('/api/devices/:id',        [DeviceController::class, 'destroy']);

// ── Groups ────────────────────────────────────────────────────
$router->get('/api/groups',                [GroupController::class, 'index']);
$router->post('/api/groups',               [GroupController::class, 'store']);
$router->put('/api/groups/:id',            [GroupController::class, 'update']);
$router->delete('/api/groups/:id',         [GroupController::class, 'destroy']);

// ── Playlists ─────────────────────────────────────────────────
$router->get('/api/playlists',             [PlaylistController::class, 'index']);
$router->post('/api/playlists',            [PlaylistController::class, 'store']);
$router->get('/api/playlists/:id',         [PlaylistController::class, 'show']);
$router->put('/api/playlists/:id',         [PlaylistController::class, 'update']);
$router->delete('/api/playlists/:id',      [PlaylistController::class, 'destroy']);

// ── Playlist Items ────────────────────────────────────────────
$router->post('/api/items',                [ItemController::class, 'store']);
$router->post('/api/items/reorder',        [ItemController::class, 'reorder']);
$router->put('/api/items/:id',             [ItemController::class, 'update']);
$router->delete('/api/items/:id',          [ItemController::class, 'destroy']);

// ── Media ─────────────────────────────────────────────────────
$router->get('/api/media',                 [MediaController::class, 'index']);
$router->get('/api/media/limits',          [MediaController::class, 'limits']);
$router->post('/api/media',                [MediaController::class, 'store']);
$router->post('/api/media/batch-delete',   [MediaController::class, 'destroyBatch']);
$router->delete('/api/media/:id',          [MediaController::class, 'destroy']);

// ── Pairing ───────────────────────────────────────────────────
$router->get('/api/pairing',               [PairingController::class, 'index']);
// TV: gera código e consulta status (sem JWT — usa client_id)
$router->post('/api/pairing/tv-generate',  [PairingController::class, 'tvGenerate']);
$router->get('/api/pairing/tv-status',     [PairingController::class, 'tvStatus']);
// Admin: pareia/despareia
$router->post('/api/pairing/pair',         [PairingController::class, 'pair']);
$router->post('/api/pairing/unpair',       [PairingController::class, 'unpair']);
