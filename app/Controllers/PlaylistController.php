<?php
namespace App\Controllers;

use App\Core\{Controller, Response};
use App\Models\{Playlist, PlaylistItem};

class PlaylistController extends Controller
{
    private Playlist $model;
    public function __construct() { parent::__construct(); $this->model = new Playlist(); }

    public function index(): void
    {
        $this->auth();
        Response::json($this->model->allWithCount());
    }

    public function show(array $params): void
    {
        $this->auth();
        $pl = $this->model->findWithItems((int)$params['id']);
        if (!$pl) Response::notFound();
        Response::json($pl);
    }

    public function store(): void
    {
        $payload = $this->auth();
        $name    = $this->request->input('name');
        if (!$name) Response::error('Nome é obrigatório');

        $isDefault = (bool)$this->request->body('is_default');
        if ($isDefault) {
            \App\Core\Database::connection()->query("UPDATE playlists SET is_default=0");
        }

        // Duplicar playlist
        $copyFrom = $this->request->int('copy_from');
        if ($copyFrom) {
            $src = $this->model->findWithItems($copyFrom);
            if (!$src) Response::error('Playlist origem não encontrada');
            $newId = $this->model->create(['name' => $name, 'is_default' => 0]);
            $itemModel = new PlaylistItem();
            foreach ($src['items'] as $item) {
                // Remove colunas que não existem na tabela playlist_items
                // (media_url vem do JOIN com media, não é coluna da tabela)
                $itemModel->create([
                    'playlist_id' => $newId,
                    'type'        => $item['type']       ?? 'image',
                    'url'         => $item['url']         ?? '',
                    'duration'    => $item['duration']    ?? 10,
                    'media_id'    => $item['media_id']    ?? null,
                    'sort_order'  => $item['sort_order']  ?? 0,
                ]);
            }
            $this->log('create_playlist', $payload['sub'], "$name (cópia de {$src['name']})");
            Response::json($this->model->findWithItems($newId), 201);
        }

        $id = $this->model->create(['name' => $name, 'is_default' => $isDefault ? 1 : 0]);
        $this->log('create_playlist', $payload['sub'], $name);
        Response::json(array_merge($this->model->find($id), ['item_count' => 0]), 201);
    }

    public function destroy(array $params): void
    {
        $payload = $this->auth();
        $id      = (int)$params['id'];
        \App\Core\Database::connection()->prepare("DELETE FROM playlist_items WHERE playlist_id=?")->execute([$id]);
        $this->model->delete($id);
        $this->log('delete_playlist', $payload['sub'], "id=$id");
        Response::json(['deleted' => $id]);
    }
}
