<?php
namespace App\Controllers;

use App\Core\{Controller, Response};
use App\Models\Media;

class MediaController extends Controller
{
    private Media $model;
    public function __construct() { parent::__construct(); $this->model = new Media(); }

    public function index(): void
    {
        $this->auth();
        Response::json($this->model->allOrdered());
    }

    public function store(): void
    {
        $payload = $this->auth();
        $file    = $this->request->file('file');
        if (!$file) Response::error('Nenhum arquivo enviado');

        $allowed = ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/webm'];
        if (!in_array($file['type'], $allowed)) Response::error('Tipo não permitido: ' . $file['type']);
        if ($file['size'] > 100 * 1024 * 1024) Response::error('Arquivo muito grande (máx 100 MB)');

        $isVideo = str_starts_with($file['type'], 'video/');
        $subdir  = $isVideo ? 'videos' : 'images';
        $ext     = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fname   = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
        $dest    = ROOT . "/uploads/$subdir/$fname";

        if (!move_uploaded_file($file['tmp_name'], $dest)) Response::error('Erro ao salvar arquivo');

        $id = $this->model->create([
            'original_name' => $file['name'],
            'type'          => $isVideo ? 'video' : 'image',
            'url'           => "/uploads/$subdir/$fname",
            'size'          => $file['size'],
        ]);

        $this->log('upload_media', $payload['sub'], $file['name']);
        Response::json($this->model->find($id), 201);
    }

    public function destroy(array $params): void
    {
        $payload = $this->auth();
        $media   = $this->model->find((int)$params['id']);
        if (!$media) Response::notFound();

        $path = ROOT . $media['url'];
        if (file_exists($path)) unlink($path);

        $this->model->delete((int)$params['id']);
        $this->log('delete_media', $payload['sub'], $media['url']);
        Response::json(['deleted' => (int)$params['id']]);
    }
}
