<?php
namespace App\Controllers;

use App\Core\{Controller, Response, Database};
use App\Models\{PairingCode, Device};

class PairingController extends Controller
{
    private PairingCode $codes;
    private Device      $devices;

    public function __construct()
    {
        parent::__construct();
        $this->codes   = new PairingCode();
        $this->devices = new Device();
    }

    // TV gera/recupera seu código de pareamento
    // GET /api/pairing/generate?token=DEVICE_TOKEN
    public function generate(): void
    {
        $token  = $this->request->query('token', '');
        if (!$token) Response::unauthorized('Token ausente');

        $device = $this->devices->findByToken($token);
        if (!$device) Response::error('Dispositivo não reconhecido', 404);

        $code = $this->codes->generate($device['id']);
        Response::json(['code' => $code]);
    }

    // Admin lista códigos pendentes
    public function index(): void
    {
        $this->auth();
        Response::json($this->codes->pending());
    }

    // Admin confirma pareamento (só código + nome)
    // POST /api/pairing/confirm
    public function confirm(): void
    {
        $payload  = $this->auth();
        $code     = $this->request->input('code');
        $name     = $this->request->input('name');
        if (!$code) Response::error('Código ausente');
        if (!$name) Response::error('Nome é obrigatório');

        $pair = $this->codes->consume($code);
        if (!$pair) Response::error('Código inválido ou expirado');

        $devId = (int)$pair['device_id'];
        $this->devices->update($devId, [
            'name'     => $name,
            'location' => $this->request->input('location') ?: null,
        ]);

        $this->log('pair_device', $payload['sub'], "device=$devId name=$name");
        Response::json([
            'device_id'  => $devId,
            'player_url' => "/tv/{$this->devices->find($devId)['slug']}",
            'device'     => $this->devices->findWithRelations($devId),
        ]);
    }

    // Admin vincula código a device existente
    // POST /api/pairing/pair
    public function pair(): void
    {
        $payload = $this->auth();
        $code    = $this->request->input('code');
        $devId   = $this->request->int('device_id');
        if (!$code || !$devId) Response::error('code e device_id são obrigatórios');

        $pair = $this->codes->consume($code);
        if (!$pair) Response::error('Código inválido ou expirado');

        $srcId = (int)$pair['device_id'];
        if ($srcId !== $devId) {
            $src = $this->devices->find($srcId);
            if ($src) {
                $this->devices->update($devId, ['token' => $src['token']]);
                // Remove device temporário se auto-gerado
                if (!$this->devices->isConfigured($src['name'])) {
                    $this->devices->delete($srcId);
                }
            }
        }

        $this->log('pair_device', $payload['sub'], "device=$devId code=$code");
        Response::json([
            'device_id'  => $devId,
            'player_url' => "/tv/{$this->devices->find($devId)['slug']}",
            'device'     => $this->devices->findWithRelations($devId),
        ]);
    }
}
