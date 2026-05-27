<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = s($_GET['action'] ?? '', 20);

// ── GET: listar pendentes (admin) ─────────────────────────────
if ($method === 'GET') {
    auth();
    $rows = db()->query(
        "SELECT * FROM pairing_codes WHERE paired=0 AND expires_at > NOW() ORDER BY created_at DESC"
    )->fetchAll();
    json_ok($rows);
}

// ── POST generate: TV gera código ────────────────────────────
if ($method === 'POST' && $action === 'generate') {
    db()->exec("DELETE FROM pairing_codes WHERE expires_at < NOW()");

    do {
        $code   = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $exists = db()->prepare("SELECT id FROM pairing_codes WHERE code=?");
        $exists->execute([$code]);
    } while ($exists->fetch());

    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    db()->prepare("INSERT INTO pairing_codes (code,expires_at) VALUES (?,?)")->execute([$code, $expires]);
    json_ok(['code' => $code, 'expires_at' => $expires]);
}

// ── POST check: TV verifica se foi pareada ────────────────────
if ($method === 'POST' && $action === 'check') {
    $b    = require_fields(['code']);
    $code = s($b['code'], 6);

    $row = db()->prepare("SELECT * FROM pairing_codes WHERE code=? AND expires_at > NOW()");
    $row->execute([$code]);
    $row = $row->fetch();
    if (!$row) json_err('Código inválido ou expirado', 404);

    json_ok(['paired' => (bool)$row['paired'], 'token' => $row['token'] ?? null]);
}

// ── POST link: admin vincula código a dispositivo ─────────────
if ($method === 'POST' && $action === 'link') {
    auth_admin();
    $b    = require_fields(['code']);
    $code = s($b['code'], 6);

    $row = db()->prepare("SELECT * FROM pairing_codes WHERE code=? AND paired=0 AND expires_at > NOW()");
    $row->execute([$code]);
    $row = $row->fetch();
    if (!$row) json_err('Código inválido, expirado ou já utilizado', 404);

    if (!empty($b['device_id'])) {
        $dev_id = sint($b['device_id']);
        $token  = db()->prepare("SELECT token FROM devices WHERE id=?");
        $token->execute([$dev_id]);
        $token  = $token->fetchColumn();
        if (!$token) json_err('Dispositivo não encontrado', 404);
    } else {
        $token  = rand_token(32);
        $name   = s($b['name'] ?? 'Nova TV', 120);
        $loc    = s($b['location'] ?? '', 180);
        db()->prepare("INSERT INTO devices (name,location,token) VALUES (?,?,?)")->execute([$name, $loc, $token]);
        $dev_id = (int)db()->lastInsertId();
    }

    db()->prepare("UPDATE pairing_codes SET paired=1, device_id=?, token=? WHERE code=?")
        ->execute([$dev_id, $token, $code]);

    log_act(0, 'pair_device', 'device', $dev_id, "code=$code");
    json_ok([
        'device_id'  => $dev_id,
        'token'      => $token,
        'player_url' => APP_URL . '/html/player.html?token=' . $token,
    ]);
}

json_err('Ação não suportada', 405);
