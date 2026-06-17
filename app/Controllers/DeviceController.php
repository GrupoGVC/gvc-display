<?php
namespace App\Controllers;

use App\Core\{Controller, Response};
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
        $payload = $this->auth();
        Response::json($this->model->allWithRelations());
    }

    public function show(array $params): void
    {
        $this->auth();
        $device = $this->model->findWithRelations((int)$params['id']);
        if (!$device) Response::notFound('Dispositivo não encontrado');
        Response::json($device);
    }

    public function store(): void
    {
        $payload = $this->auth();
        $name    = $this->request->input('name');
        if (!$name) Response::error('Nome é obrigatório');

        $id = $this->model->createWithSlug([
            'name'        => $name,
            'location'    => $this->request->input('location') ?: null,
            'group_id'    => $this->request->int('group_id')   ?: null,
            'playlist_id' => $this->request->int('playlist_id') ?: null,
            'status'      => 'offline',
        ]);

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

    public function heartbeat(): void
    {
        $token = $this->request->input('token');
        if (!$token) Response::unauthorized('Token ausente');

        $device = $this->model->findByToken($token);
        if (!$device) Response::unauthorized('Token inválido');

        $this->model->updateStatus($device['id'], 'online');

        if (!$this->model->isConfigured($device['name'])) {
            Response::json(['playlist_id' => null, 'playlist_hash' => null, 'configured' => false]);
        }

        $playlistModel = new \App\Models\Playlist();
        $plId = $playlistModel->activeForDevice($device['id'], $device['group_id'])
             ?? $device['playlist_id'];

        if (!$plId) {
            $def  = \App\Core\Database::connection()->query("SELECT id FROM playlists WHERE is_default=1 LIMIT 1")->fetch();
            $plId = $def ? (int)$def['id'] : null;
        }

        $hash = null;
        if ($plId) {
            $st = \App\Core\Database::connection()->prepare("SELECT GROUP_CONCAT(id ORDER BY sort_order) FROM playlist_items WHERE playlist_id=?");
            $st->execute([$plId]);
            $hash = md5($st->fetchColumn() ?? '');
        }

        Response::json(['playlist_id' => $plId, 'playlist_hash' => $hash, 'configured' => true]);
    }

    public function broadcast(): void
    {
        $payload  = $this->auth();
        $plId     = $this->request->int('playlist_id');
        $target   = $this->request->input('target', 'all');
        $db       = \App\Core\Database::connection();
        $affected = 0;

        if ($target === 'all') {
            $affected = $db->prepare("UPDATE devices SET playlist_id=?")->execute([$plId]) ? $db->query("SELECT ROW_COUNT()")->fetchColumn() : 0;
            $db->prepare("UPDATE devices SET playlist_id=?")->execute([$plId]);
            $affected = $db->query("SELECT COUNT(*) FROM devices")->fetchColumn();
        } elseif (str_starts_with($target, 'group:')) {
            $gId = (int)substr($target, 6);
            $st  = $db->prepare("UPDATE devices SET playlist_id=? WHERE group_id=?");
            $st->execute([$plId, $gId]);
            $affected = $st->rowCount();
        } elseif (str_starts_with($target, 'device:')) {
            $dId = (int)substr($target, 7);
            $db->prepare("UPDATE devices SET playlist_id=? WHERE id=?")->execute([$plId, $dId]);
            $affected = 1;
        }

        $this->log('broadcast', $payload['sub'], "playlist=$plId target=$target");
        Response::json(['affected' => $affected]);
    }
}
