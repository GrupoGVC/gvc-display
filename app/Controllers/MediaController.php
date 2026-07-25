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

    /**
     * Retorna os limites reais de upload do servidor (para o front saber o que o PHP aceita).
     */
    public function limits(): void
    {
        $this->auth();
        $parse = fn(string $v): int => match (strtolower(substr(trim($v), -1))) {
            'g' => (int)$v * 1073741824,
            'm' => (int)$v * 1048576,
            'k' => (int)$v * 1024,
            default => (int)$v,
        };

        $uploadMax = $parse(ini_get('upload_max_filesize') ?: '2M');
        $postMax   = $parse(ini_get('post_max_size') ?: '8M');
        $effective = min($uploadMax, $postMax);
        $appMax    = 100 * 1024 * 1024; // limite do app

        Response::json([
            'upload_max_filesize' => $uploadMax,
            'post_max_size'       => $postMax,
            'app_max'             => $appMax,
            'effective_max'       => min($effective, $appMax),
            'effective_max_mb'    => round(min($effective, $appMax) / 1048576),
        ]);
    }

    public function store(): void
    {
        $payload  = $this->auth();
        $fileErr  = null;
        $file     = $this->request->file('file', $fileErr);

        if (!$file) {
            Response::error($fileErr ?: 'Nenhum arquivo enviado');
        }

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

    /**
     * Exclusão em lote: recebe { ids: [1, 2, 3] }
     */
    public function destroyBatch(): void
    {
        $payload = $this->auth();
        $ids     = $this->request->body('ids');

        if (!is_array($ids) || empty($ids)) {
            Response::error('Envie um array de IDs em { "ids": [...] }');
        }

        // Sanitiza: aceita somente inteiros positivos
        $ids = array_values(array_filter(
            array_map('intval', $ids),
            fn(int $id) => $id > 0
        ));

        if (empty($ids)) Response::error('Nenhum ID válido enviado');

        $deleted = [];
        $errors  = [];

        foreach ($ids as $id) {
            $media = $this->model->find($id);
            if (!$media) {
                $errors[] = "ID $id não encontrado";
                continue;
            }

            $path = ROOT . $media['url'];
            if (file_exists($path)) unlink($path);

            $this->model->delete($id);
            $this->log('delete_media', $payload['sub'], $media['url']);
            $deleted[] = $id;
        }

        Response::json([
            'deleted' => $deleted,
            'count'   => count($deleted),
            'errors'  => $errors,
        ]);
    }
}
