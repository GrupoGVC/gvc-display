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

    /**
     * TV gera um código de pareamento.
     * Chamado pelo player.js quando o dispositivo NÃO está pareado.
     * POST /api/pairing/tv-generate  { client_id }
     */
    public function tvGenerate(): void
    {
        $clientId = $this->request->input('client_id');
        if (!$clientId) Response::error('client_id ausente');
        // Sanitiza — deve ser só hex de 32 chars
        if (!preg_match('/^[a-f0-9]{16,64}$/i', $clientId)) {
            Response::error('client_id inválido');
        }

        $code = $this->codes->generateForClient($clientId);
        Response::json(['code' => $code]);
    }

    /**
     * TV consulta status de pareamento.
     * Retorna: { paired: true, token, slug } se já vinculada
     *          { paired: false, code, expires_at } se ainda aguardando
     * GET /api/pairing/tv-status?client_id=...
     */
    public function tvStatus(): void
    {
        $clientId = $this->request->query('client_id', '');
        if (!$clientId || !preg_match('/^[a-f0-9]{16,64}$/i', $clientId)) {
            Response::error('client_id inválido');
        }

        // Já foi pareada? Procura um device que tenha esse client_id anotado.
        $st = Database::connection()->prepare(
            "SELECT id, name, slug, token FROM devices WHERE client_id = ? AND token IS NOT NULL LIMIT 1"
        );
        $st->execute([$clientId]);
        $device = $st->fetch();

        if ($device) {
            Response::json([
                'paired' => true,
                'token'  => $device['token'],
                'slug'   => $device['slug'],
                'name'   => $device['name'],
            ]);
        }

        // Ainda não pareada — retorna código pendente
        $pending = $this->codes->findPendingByClient($clientId);
        if ($pending) {
            Response::json([
                'paired'     => false,
                'code'       => $pending['code'],
                'expires_at' => $pending['expires_at'],
            ]);
        }

        Response::json(['paired' => false, 'code' => null]);
    }

    /**
     * Admin pareia uma TV existente com um código digitado.
     * POST /api/pairing/pair  { code, device_id }
     *
     * Efeito: gera slug+token para o device, associa ao client_id do código,
     * remove o código de pareamento. A TV, no próximo polling, vai receber
     * o token e passar ao modo player.
     */
    public function pair(): void
    {
        $payload = $this->auth();
        $code    = $this->request->input('code');
        $devId   = $this->request->int('device_id');

        if (!$code || !$devId) Response::error('code e device_id são obrigatórios');

        $device = $this->devices->find($devId);
        if (!$device) Response::error('Dispositivo não encontrado', 404);

        if (!empty($device['token'])) {
            Response::error('Esta TV já está pareada. Desparei primeiro para reparear.');
        }

        // Valida código e pega o client_id que gerou
        $pair = $this->codes->consume($code);
        if (!$pair) Response::error('Código inválido ou expirado');

        $clientId = $pair['client_id'] ?? null;
        if (!$clientId) Response::error('Código sem client_id (inválido)');

        // Gera slug e token únicos
        $slug  = $this->generateSlug($device['name']);
        $token = bin2hex(random_bytes(32));

        Database::connection()->prepare(
            "UPDATE devices SET slug = ?, token = ?, client_id = ? WHERE id = ?"
        )->execute([$slug, $token, $clientId, $devId]);

        $this->log('pair_device', $payload['sub'], "device=$devId code=$code");
        Response::json([
            'success'    => true,
            'device_id'  => $devId,
            'slug'       => $slug,
            'player_url' => "/tv/{$slug}",
            'device'     => $this->devices->findWithRelations($devId),
        ]);
    }

    /**
     * Admin despareia uma TV — apaga slug, token, client_id.
     * A TV, no próximo polling, detecta 401 e volta à tela de pareamento.
     * POST /api/pairing/unpair  { device_id }
     */
    public function unpair(): void
    {
        $payload = $this->auth();
        $devId   = $this->request->int('device_id');
        if (!$devId) Response::error('device_id obrigatório');

        $device = $this->devices->find($devId);
        if (!$device) Response::error('Dispositivo não encontrado', 404);

        Database::connection()->prepare(
            "UPDATE devices SET slug = NULL, token = NULL, client_id = NULL, last_ping = NULL WHERE id = ?"
        )->execute([$devId]);

        // Remove códigos pendentes
        Database::connection()
            ->prepare("DELETE FROM pairing_codes WHERE device_id = ?")
            ->execute([$devId]);

        $this->log('unpair_device', $payload['sub'], "device=$devId name={$device['name']}");
        Response::json([
            'success'   => true,
            'device_id' => $devId,
            'device'    => $this->devices->findWithRelations($devId),
        ]);
    }

    /**
     * Admin lista códigos pendentes.
     */
    public function index(): void
    {
        $this->auth();
        Response::json($this->codes->pending());
    }

    private function generateSlug(string $name): string
    {
        // Base: nome sanitizado
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $base = trim($base, '-');
        if ($base === '') $base = 'tv';
        $base = substr($base, 0, 60);

        // Garante unicidade
        $slug = $base;
        $i    = 2;
        while (true) {
            $st = Database::connection()->prepare("SELECT 1 FROM devices WHERE slug = ?");
            $st->execute([$slug]);
            if (!$st->fetchColumn()) return $slug;
            $slug = $base . '-' . $i;
            $i++;
        }
    }
}
