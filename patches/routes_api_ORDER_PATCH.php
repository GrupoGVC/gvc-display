<?php
/*
No arquivo routes/api.php, garanta que estas rotas específicas venham ANTES das rotas com :id.
Não precisa duplicar se já existirem; ajuste somente a ordem.
*/

$router->post('/api/devices/heartbeat', [DeviceController::class, 'heartbeat']);

$router->get('/api/pairing/generate', [PairingController::class, 'generate']);
$router->post('/api/pairing/confirm', [PairingController::class, 'confirm']);

$router->get('/tv/', [TvController::class, 'show']);
$router->get('/tv/:slug', [TvController::class, 'show']);
