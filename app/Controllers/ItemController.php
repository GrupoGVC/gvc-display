<?php
namespace App\Controllers;

use App\Core\{Controller, Response};
use App\Models\PlaylistItem;

class ItemController extends Controller
{
    private PlaylistItem $model;
    public function __construct() { parent::__construct(); $this->model = new PlaylistItem(); }

    public function store(): void
    {
        $this->auth();
        $plId = $this->request->int('playlist_id');
        $url  = $this->request->input('url');
        if (!$plId || !$url) Response::error('playlist_id e url são obrigatórios');

        $max  = \App\Core\Database::connection()
                    ->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM playlist_items WHERE playlist_id=?");
        $max->execute([$plId]);
        $order = (int)$max->fetchColumn();

        $id = $this->model->create([
            'playlist_id' => $plId,
            'type'        => $this->request->input('type', 'image'),
            'url'         => $url,
            'duration'    => $this->request->int('duration') ?: 10,
            'media_id'    => $this->request->int('media_id') ?: null,
            'sort_order'  => $order,
        ]);
        Response::json(['id' => $id, 'sort_order' => $order], 201);
    }

    public function destroy(array $params): void
    {
        $this->auth();
        $this->model->delete((int)$params['id']);
        Response::json(['deleted' => (int)$params['id']]);
    }

    public function reorder(): void
    {
        $this->auth();
        $items = $this->request->body('items') ?? [];
        if (!is_array($items)) Response::error('items deve ser array');
        $this->model->reorder($items);
        Response::json(['reordered' => count($items)]);
    }
}
