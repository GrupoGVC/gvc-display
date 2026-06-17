<?php
// ============================================================
//  GVC Display — Definição de rotas da API
//  Todas as rotas são registradas aqui, centralizadas.
//  Formato: $router->METHOD('uri/:param', [Controller::class, 'action'])
// ============================================================

use App\Controllers\{
    AuthController,
    DeviceController,
    GroupController,
    PlaylistController,
    ItemController,
    MediaController,
    ScheduleController,
    PairingController,
    DashboardController
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
$router->delete('/api/playlists/:id',      [PlaylistController::class, 'destroy']);

// ── Playlist Items ────────────────────────────────────────────
$router->post('/api/items',                [ItemController::class, 'store']);
$router->post('/api/items/reorder',        [ItemController::class, 'reorder']);
$router->delete('/api/items/:id',          [ItemController::class, 'destroy']);

// ── Media ─────────────────────────────────────────────────────
$router->get('/api/media',                 [MediaController::class, 'index']);
$router->post('/api/media',                [MediaController::class, 'store']);
$router->delete('/api/media/:id',          [MediaController::class, 'destroy']);

// ── Schedules ─────────────────────────────────────────────────
$router->get('/api/schedules',             [ScheduleController::class, 'index']);
$router->post('/api/schedules',            [ScheduleController::class, 'store']);
$router->put('/api/schedules/:id',         [ScheduleController::class, 'update']);
$router->delete('/api/schedules/:id',      [ScheduleController::class, 'destroy']);

// ── Pairing ───────────────────────────────────────────────────
$router->get('/api/pairing',               [PairingController::class, 'index']);
$router->get('/api/pairing/generate',      [PairingController::class, 'generate']);
$router->post('/api/pairing/confirm',      [PairingController::class, 'confirm']);
$router->post('/api/pairing/pair',         [PairingController::class, 'pair']);
