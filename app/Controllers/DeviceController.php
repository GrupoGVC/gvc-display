<?php
namespace App\Controllers;

use App\Core\{Controller, Response, Database};
use App\Models\Device;

class DeviceController extends Controller
{
    private Device $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Device();
    }

    public function index(): void
    {
        $this->auth();
        Response::json($this->model->allWithRelations());
    }

    public function show(array $params): void
    {
        $this->auth();
        $device = $this->model->findWithRelations((int)$params['id']);
        if (!$device) Response::notFound('Dispositivo não encontrado');
        Response::json($device);
    }

    /**
     * Cria uma TV SEM slug/token (não pareada).
     * O admin cadastra a TV, depois usa o botão "Parear" para vinculá-la
     * a uma TV física via código de 6 dígitos.
     */
    public function store(): void
    {
        $payload = $this->auth();
        $name    = $this->request->input('name');
        if (!$name) Response::error('Nome é obrigatório');

        $db = Database::connection();
        $db->prepare("
            INSERT INTO devices (name, location, group_id, playlist_id, slug, token, client_id)
            VALUES (?, ?, ?, ?, NULL, NULL, NULL)
        ")->execute([
            $name,
            $this->request->input('location') ?: null,
            $this->request->int('group_id')   ?: null,
            $this->request->int('playlist_id') ?: null,
        ]);
        $id = (int)$db->lastInsertId();

        $this->log('create_device', $payload['sub'], $name);
        Response::json($this->model->findWithRelations($id), 201);
    }

    public function update(array $params): void
    {
        $payload = $this->auth();
        $id      = (int)$params['id'];
        $name    = $this->request->input('name');

        $this->model->update($id, [
            'name'        => $name ?: null,
            'location'    => $this->request->input('location') ?: null,
            'group_id'    => $this->request->int('group_id')   ?: null,
            'playlist_id' => $this->request->int('playlist_id') ?: null,
        ]);

        $this->log('update_device', $payload['sub'], $name);
        Response::json($this->model->findWithRelations($id));
    }

    public function destroy(array $params): void
    {
        $payload = $this->auth();
        $device  = $this->model->find((int)$params['id']);
        if (!$device) Response::notFound();
        $this->model->delete((int)$params['id']);
        $this->log('delete_device', $payload['sub'], $device['name']);
        Response::json(['deleted' => (int)$params['id']]);
    }

    /**
     * TV envia heartbeat. Precisa de token válido (só TVs pareadas fazem heartbeat).
     * Retorna a playlist ativa e um hash pra detectar mudanças.
     */
    public function heartbeat(): void
    {
        $token = $this->request->input('token');
        if (!$token) Response::unauthorized('Token ausente');

        $device = $this->model->findByToken($token);
        if (!$device) Response::unauthorized('Token inválido');

        $this->model->updateStatus($device['id'], 'online');

        // Resolve playlist ativa: direta → default
        $plId = $device['playlist_id'] ? (int)$device['playlist_id'] : null;

        if (!$plId) {
            $def  = Database::connection()->query("SELECT id FROM playlists WHERE is_default=1 LIMIT 1")->fetch();
            $plId = $def ? (int)$def['id'] : null;
        }

        $hash = null;
        if ($plId) {
            $st = Database::connection()->prepare(
                "SELECT GROUP_CONCAT(CONCAT(id,':',sort_order,':',duration) ORDER BY sort_order SEPARATOR ',')
                  FROM playlist_items WHERE playlist_id = ?"
            );
            $st->execute([$plId]);
            $hash = md5((string)($st->fetchColumn() ?: ''));
        }

        Response::json(['playlist_id' => $plId, 'playlist_hash' => $hash]);
    }

    public function broadcast(): void
    {
        $payload  = $this->auth();
        $plId     = $this->request->int('playlist_id');
        $target   = $this->request->input('target', 'all');
        $db       = Database::connection();
        $affected = 0;

        if ($target === 'all') {
            $db->prepare("UPDATE devices SET playlist_id = ?")->execute([$plId]);
            $affected = (int)$db->query("SELECT ROW_COUNT()")->fetchColumn();
        } elseif (str_starts_with($target, 'group:')) {
            $gId = (int)substr($target, 6);
            $st  = $db->prepare("UPDATE devices SET playlist_id = ? WHERE group_id = ?");
            $st->execute([$plId, $gId]);
            $affected = $st->rowCount();
        } elseif (str_starts_with($target, 'device:')) {
            $dId = (int)substr($target, 7);
            $db->prepare("UPDATE devices SET playlist_id = ? WHERE id = ?")->execute([$plId, $dId]);
            $affected = 1;
        }

        $this->log('broadcast', $payload['sub'], "playlist=$plId target=$target");
        Response::json(['affected' => $affected]);
    }

    /**
     * TV busca sua playlist. Autentica via token na query.
     * GET /api/devices/tv-playlist?token=...
     */
    public function tvPlaylist(): void
    {
        $token = $this->request->query('token', '');
        if (!$token) Response::unauthorized('Token ausente');

        $device = $this->model->findByToken($token);
        if (!$device) Response::unauthorized('Token inválido');

        $plId = $device['playlist_id'] ? (int)$device['playlist_id'] : null;

        if (!$plId) {
            $def  = Database::connection()
                ->query("SELECT id FROM playlists WHERE is_default=1 LIMIT 1")->fetch();
            $plId = $def ? (int)$def['id'] : null;
        }

        if (!$plId) {
            http_response_code(204);
            exit;
        }

        $pl = (new \App\Models\Playlist())->findWithItems($plId);
        if (!$pl || empty($pl['items'])) {
            http_response_code(204);
            exit;
        }

        Response::json($pl);
    }
}
