<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

$db  = db();
$m   = method();
$act = action();

// ── TV gera código de pareamento ───────────────────────────────
// GET /api/pairing/index.php?action=generate&token=DEVICE_TOKEN
if ($m === 'GET' && $act === 'generate') {
    $devToken = $_GET['token'] ?? '';
    if (!$devToken) json_err('Token ausente', 401);

    $stmt = $db->prepare("SELECT id, name FROM devices WHERE token=?");
    $stmt->execute([$devToken]);
    $dev = $stmt->fetch();
    if (!$dev) json_err('Dispositivo não reconhecido', 404);

    $db->query("DELETE FROM pairing_codes WHERE expires_at < NOW()");

    // Reutiliza código válido existente
    $existing = $db->prepare("SELECT code FROM pairing_codes WHERE device_id=? AND expires_at > NOW()");
    $existing->execute([$dev['id']]);
    $row = $existing->fetch();
    if ($row) { json_ok(['code' => $row['code']]); }

    // Gera novo código de 6 dígitos único
    do {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ck   = $db->prepare("SELECT 1 FROM pairing_codes WHERE code=?");
        $ck->execute([$code]);
    } while ($ck->fetchColumn());

    $db->prepare("INSERT INTO pairing_codes (device_id, code, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))")
       ->execute([$dev['id'], $code]);

    json_ok(['code' => $code]);
}

// ── Admin lista códigos pendentes ──────────────────────────────
if ($m === 'GET') {
    auth_required();
    $db->query("DELETE FROM pairing_codes WHERE expires_at < NOW()");
    $rows = $db->query("
        SELECT pc.code, pc.expires_at, d.id AS device_id, d.name AS device_name
        FROM pairing_codes pc
        LEFT JOIN devices d ON d.id = pc.device_id
        ORDER BY pc.created_at DESC
    ")->fetchAll();
    json_ok($rows);
}

// ── Admin pareia: só precisa do código ─────────────────────────
// POST /api/pairing/index.php?action=confirm
// Body: { code, name, location }
// O código já identifica a TV — não precisa escolher device_id
if ($m === 'POST' && $act === 'confirm') {
    $payload  = auth_required();
    $code     = req('code');
    $name     = req('name');      // nome que o admin quer dar à TV
    $location = req('location');  // local opcional

    if (!$code) json_err('Código ausente');
    if (!$name) json_err('Nome é obrigatório');

    // Busca o código
    $stmt = $db->prepare("SELECT pc.id, pc.device_id FROM pairing_codes pc WHERE pc.code=? AND pc.expires_at > NOW()");
    $stmt->execute([$code]);
    $pair = $stmt->fetch();
    if (!$pair) json_err('Código inválido ou expirado');

    $devId = (int)$pair['device_id'];

    // Atualiza nome e localização do device (confirma o pareamento)
    $db->prepare("UPDATE devices SET name=?, location=? WHERE id=?")
       ->execute([$name, $location ?: null, $devId]);

    $db->prepare("DELETE FROM pairing_codes WHERE id=?")->execute([$pair['id']]);
    log_activity('pair_device', $payload['sub'], "device=$devId code=$code name=$name");

    $devStmt = $db->prepare("
        SELECT d.id, d.name, d.location, d.slug, d.status, d.token,
               d.playlist_id, d.group_id,
               g.name AS group_name, p.name AS playlist_name,
               CONCAT('/tv/', d.slug) AS player_url
        FROM devices d
        LEFT JOIN `groups` g ON g.id = d.group_id
        LEFT JOIN playlists p ON p.id = d.playlist_id
        WHERE d.id=?");
    $devStmt->execute([$devId]);
    $dev = $devStmt->fetch();

    json_ok([
        'device_id'  => $devId,
        'player_url' => $dev ? $dev['player_url'] : ('/tv/' . $devId),
        'device'     => $dev ?: null,
    ]);
}

// ── Admin pareia vinculando a device existente (modal Dispositivos) ─
// POST /api/pairing/index.php?action=pair
// Body: { code, device_id }
if ($m === 'POST' && $act === 'pair') {
    $payload = auth_required();
    $code    = req('code');
    $devId   = (int)(body()['device_id'] ?? 0);

    if (!$code || !$devId) json_err('code e device_id são obrigatórios');

    $stmt = $db->prepare("SELECT id, device_id FROM pairing_codes WHERE code=? AND expires_at > NOW()");
    $stmt->execute([$code]);
    $pair = $stmt->fetch();
    if (!$pair) json_err('Código inválido ou expirado');

    $srcDevId = (int)$pair['device_id'];

    if ($srcDevId !== $devId) {
        // Transfere token da TV para o device escolhido pelo admin
        $tokenStmt = $db->prepare("SELECT token FROM devices WHERE id=?");
        $tokenStmt->execute([$srcDevId]);
        $srcDev = $tokenStmt->fetch();

        if ($srcDev) {
            $db->prepare("UPDATE devices SET token=? WHERE id=?")->execute([$srcDev['token'], $devId]);
        }
        // Remove device temporário auto-gerado pelo tv.php
        $db->prepare("DELETE FROM devices WHERE id=? AND name REGEXP '^TV [A-Fa-f0-9]{6}$'")->execute([$srcDevId]);
    }

    $db->prepare("DELETE FROM pairing_codes WHERE id=?")->execute([$pair['id']]);
    log_activity('pair_device', $payload['sub'], "device=$devId code=$code");

    $devStmt = $db->prepare("
        SELECT d.id, d.name, d.location, d.slug, d.status, d.token,
               d.playlist_id, d.group_id,
               g.name AS group_name, p.name AS playlist_name,
               CONCAT('/tv/', d.slug) AS player_url
        FROM devices d
        LEFT JOIN `groups` g ON g.id = d.group_id
        LEFT JOIN playlists p ON p.id = d.playlist_id
        WHERE d.id=?");
    $devStmt->execute([$devId]);
    $dev = $devStmt->fetch();

    json_ok([
        'device_id'  => $devId,
        'player_url' => $dev ? $dev['player_url'] : ('/tv/' . $devId),
        'device'     => $dev ?: null,
    ]);
}

// ── Admin vincula via seção Pareamento ─────────────────────────
// POST /api/pairing/index.php?action=link
if ($m === 'POST' && $act === 'link') {
    $payload  = auth_required();
    $code     = req('code');
    $devId    = (int)(body()['device_id'] ?? 0);
    $name     = req('name');
    $location = req('location');

    if (!$code) json_err('Código ausente');

    $stmt = $db->prepare("SELECT pc.id, pc.device_id FROM pairing_codes pc WHERE pc.code=? AND pc.expires_at > NOW()");
    $stmt->execute([$code]);
    $pair = $stmt->fetch();
    if (!$pair) json_err('Código inválido ou expirado');

    $srcDevId = (int)$pair['device_id'];

    if ($devId && $srcDevId !== $devId) {
        $codeDevStmt = $db->prepare("SELECT token FROM devices WHERE id=?");
        $codeDevStmt->execute([$srcDevId]);
        $codeDev = $codeDevStmt->fetch();
        if ($codeDev) {
            $db->prepare("UPDATE devices SET token=? WHERE id=?")->execute([$codeDev['token'], $devId]);
        }
        $db->prepare("DELETE FROM devices WHERE id=? AND name REGEXP '^TV [A-Fa-f0-9]{6}$'")->execute([$srcDevId]);
    } else {
        $devId = $srcDevId;
        if ($name) {
            $db->prepare("UPDATE devices SET name=?, location=? WHERE id=?")
               ->execute([$name, $location ?: null, $devId]);
        }
    }

    $db->prepare("DELETE FROM pairing_codes WHERE id=?")->execute([$pair['id']]);
    log_activity('pair_device', $payload['sub'], "device=$devId code=$code");

    $devStmt = $db->prepare("SELECT d.*, CONCAT('/tv/', d.slug) AS player_url FROM devices d WHERE d.id=?");
    $devStmt->execute([$devId]);
    $dev = $devStmt->fetch();

    json_ok(['device_id' => $devId, 'player_url' => $dev['player_url'] ?? '/tv/'.$devId]);
}

json_err('Ação inválida', 400);
