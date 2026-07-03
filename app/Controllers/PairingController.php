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
    //
    // Estratégia anti-duplicação:
    // A TV que está aberta no navegador (auto-gerada) tem um slug que o navegador
    // conhece via cookie. Se transferíssemos o token para a TV do admin e
    // deletássemos a auto-gerada, o navegador recriaria outra TV no próximo acesso.
    // Por isso: mantemos a TV auto-gerada (srcId) como a "real", copiamos os dados
    // da TV do admin (nome, local, grupo, playlist) para ela, e removemos a do admin.
    public function pair(): void
    {
        $payload = $this->auth();
        $code    = $this->request->input('code');
        $devId   = $this->request->int('device_id'); // TV criada no admin
        if (!$code || !$devId) Response::error('code e device_id são obrigatórios');

        $pair = $this->codes->consume($code);
        if (!$pair) Response::error('Código inválido ou expirado');

        $srcId = (int)$pair['device_id']; // TV auto-gerada (aberta no navegador)
        $admin = $this->devices->find($devId);
        if (!$admin) Response::error('Dispositivo do admin não encontrado', 404);

        // Mesmo device? Nada a mesclar (raro, mas seguro)
        if ($srcId === $devId) {
            $this->log('pair_device', $payload['sub'], "device=$devId code=$code (self)");
            Response::json([
                'device_id'  => $devId,
                'player_url' => "/tv/{$admin['slug']}",
                'device'     => $this->devices->findWithRelations($devId),
            ]);
        }

        $src = $this->devices->find($srcId);
        if ($src) {
            // Copia dados do admin para a TV auto-gerada (a que o navegador conhece)
            $this->devices->update($srcId, [
                'name'        => $admin['name'],
                'location'    => $admin['location'],
                'group_id'    => $admin['group_id'],
                'playlist_id' => $admin['playlist_id'],
            ]);
            // Remove a TV do admin — a auto-gerada assume a identidade dela
            $this->devices->delete($devId);
            $keepId = $srcId;
        } else {
            // Auto-gerada sumiu? Mantém a do admin
            $keepId = $devId;
        }

        $this->log('pair_device', $payload['sub'], "kept=$keepId code=$code");
        Response::json([
            'device_id'  => $keepId,
            'player_url' => "/tv/{$this->devices->find($keepId)['slug']}",
            'device'     => $this->devices->findWithRelations($keepId),
        ]);
    }

    // Admin despareia uma TV — reseta ao estado inicial
    // POST /api/pairing/unpair  { device_id }
    //
    // Volta o dispositivo ao estado "não configurado": nome auto-gerado
    // (TV XXXXXX), sem playlist e sem grupo. No próximo heartbeat (até 5s)
    // o player detecta configured=false e exibe a tela de pareamento
    // novamente, gerando um novo código. O slug/token são preservados para
    // que a mesma TV no navegador continue funcionando.
    public function unpair(): void
    {
        $payload = $this->auth();
        $devId   = $this->request->int('device_id');
        if (!$devId) Response::error('device_id é obrigatório');

        $device = $this->devices->find($devId);
        if (!$device) Response::error('Dispositivo não encontrado', 404);

        // Nome auto-gerado no mesmo formato das TVs novas (TV + 6 hex maiúsculos)
        $autoName = 'TV ' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $this->devices->update($devId, [
            'name'        => $autoName,
            'location'    => null,
            'group_id'    => null,
            'playlist_id' => null,
        ]);

        // Remove códigos de pareamento pendentes deste device (higiene)
        Database::connection()
            ->prepare("DELETE FROM pairing_codes WHERE device_id = ?")
            ->execute([$devId]);

        $this->log('unpair_device', $payload['sub'], "device=$devId name={$device['name']}");
        Response::json([
            'device_id' => $devId,
            'device'    => $this->devices->findWithRelations($devId),
        ]);
    }
}
