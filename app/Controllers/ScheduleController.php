<?php
namespace App\Controllers;

use App\Core\{Controller, Response};
use App\Models\Schedule;

class ScheduleController extends Controller
{
    private Schedule $model;
    public function __construct() { parent::__construct(); $this->model = new Schedule(); }

    public function index(): void
    {
        $this->auth();
        $rows = $this->model->allWithPlaylist();
        foreach ($rows as &$r) {
            if ($r['weekdays']) $r['weekdays'] = json_decode($r['weekdays'], true);
        }
        Response::json($rows);
    }

    public function store(): void
    {
        $payload = $this->auth();
        $plId    = $this->request->int('playlist_id');
        $starts  = $this->request->input('starts_at');
        $ends    = $this->request->input('ends_at');
        if (!$plId || !$starts || !$ends) Response::error('Campos obrigatórios ausentes');

        $target = $this->request->input('target_type', 'all');
        $repeat = (bool)$this->request->body('repeat_weekly');
        $days   = $repeat ? json_encode($this->request->body('weekdays') ?? []) : null;

        $id = $this->model->create([
            'playlist_id'   => $plId,
            'target_type'   => $target,
            'target_id'     => $this->request->int('target_id') ?: null,
            'starts_at'     => $starts,
            'ends_at'       => $ends,
            'repeat_weekly' => $repeat ? 1 : 0,
            'weekdays'      => $days,
            'active'        => 1,
        ]);

        $this->log('create_schedule', $payload['sub'], "playlist=$plId");
        Response::json($this->model->find($id), 201);
    }

    public function update(array $params): void
    {
        $this->auth();
        $active = $this->request->body('active');
        $this->model->update((int)$params['id'], ['active' => $active ? 1 : 0]);
        Response::json($this->model->find((int)$params['id']));
    }

    public function destroy(array $params): void
    {
        $payload = $this->auth();
        $this->model->delete((int)$params['id']);
        $this->log('delete_schedule', $payload['sub'], "id={$params['id']}");
        Response::json(['deleted' => (int)$params['id']]);
    }
}
