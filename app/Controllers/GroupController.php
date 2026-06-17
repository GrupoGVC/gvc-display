<?php
namespace App\Controllers;

use App\Core\{Controller, Response};
use App\Models\Group;

class GroupController extends Controller
{
    private Group $model;
    public function __construct() { parent::__construct(); $this->model = new Group(); }

    public function index(): void
    {
        $this->auth();
        Response::json($this->model->allWithCount());
    }

    public function store(): void
    {
        $payload = $this->auth();
        $name    = $this->request->input('name');
        if (!$name) Response::error('Nome é obrigatório');
        $id = $this->model->create(['name' => $name, 'description' => $this->request->input('description') ?: null]);
        $this->log('create_group', $payload['sub'], $name);
        Response::json($this->model->findWithCount($id), 201);
    }

    public function update(array $params): void
    {
        $payload = $this->auth();
        $id      = (int)$params['id'];
        $name    = $this->request->input('name');
        $this->model->update($id, ['name' => $name, 'description' => $this->request->input('description') ?: null]);
        $this->log('update_group', $payload['sub'], $name);
        Response::json($this->model->findWithCount($id));
    }

    public function destroy(array $params): void
    {
        $payload = $this->auth();
        $id      = (int)$params['id'];
        \App\Core\Database::connection()->prepare("UPDATE devices SET group_id=NULL WHERE group_id=?")->execute([$id]);
        $this->model->delete($id);
        $this->log('delete_group', $payload['sub'], "id=$id");
        Response::json(['deleted' => $id]);
    }
}
