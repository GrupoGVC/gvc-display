<?php
// ============================================================
//  GVC Signage — Helpers & Middleware
// ============================================================

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/jwt.php';

// ── CORS & Headers ────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Respostas JSON ────────────────────────────────────────────
function json_ok(mixed $data = null, int $code = 200): never {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_err(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Body parser ───────────────────────────────────────────────
function body(): array {
    static $parsed = null;
    if ($parsed === null) {
        $raw    = file_get_contents('php://input');
        $parsed = json_decode($raw ?: '{}', true) ?? [];
        if (empty($parsed) && !empty($_POST)) $parsed = $_POST;
    }
    return $parsed;
}

function require_fields(array $fields): array {
    $b = body();
    foreach ($fields as $f) {
        if (!isset($b[$f]) || trim((string)$b[$f]) === '') {
            json_err("Campo obrigatório ausente: $f", 422);
        }
    }
    return $b;
}

// ── Sanitização ───────────────────────────────────────────────
function s(mixed $v, int $max = 255): string {
    return mb_substr(strip_tags(trim((string)$v)), 0, $max);
}

function sint(mixed $v): int {
    return (int) filter_var($v, FILTER_SANITIZE_NUMBER_INT);
}

// ── Autenticação JWT (admin) ──────────────────────────────────
function auth(): array {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $t = str_starts_with($h, 'Bearer ') ? substr($h, 7) : ($_GET['_token'] ?? '');
    if (!$t) json_err('Autenticação necessária', 401);
    $p = JWT::decode($t, JWT_SECRET);
    if (!$p)  json_err('Token inválido ou expirado', 401);
    return $p;
}

function auth_admin(): array {
    $p = auth();
    if (($p['role'] ?? '') !== 'admin') json_err('Acesso negado', 403);
    return $p;
}

// ── Log de atividade ──────────────────────────────────────────
function log_act(int $uid, string $action, string $entity = '', int $eid = 0, string $detail = ''): void {
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        db()->prepare("INSERT INTO activity_logs (user_id,action,entity,entity_id,detail,ip) VALUES (?,?,?,?,?,?)")
             ->execute([$uid ?: null, $action, $entity ?: null, $eid ?: null, $detail ?: null, $ip]);
    } catch (Throwable) {}
}

// ── Tokens ───────────────────────────────────────────────────
function rand_token(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}

// ── Resolve playlist ativa para um device ────────────────────
//    Prioridade: agendamento > playlist direta > playlist padrão
function resolve_playlist(int $device_id, ?int $direct_pl, ?int $group_id): ?array {
    $db  = db();
    $now = date('Y-m-d H:i:s');
    $dow = (int) date('w');

    // Mais específico primeiro: device > group > all
    $targets = [['all', null]];
    if ($group_id)  $targets[] = ['group',  $group_id];
    if ($device_id) $targets[] = ['device', $device_id];

    foreach (array_reverse($targets) as [$ttype, $tid]) {
        $sql = "SELECT s.playlist_id FROM schedules s
                WHERE s.active = 1
                  AND s.target_type = ?
                  AND (s.target_id = ? OR s.target_type = 'all')
                  AND s.starts_at <= ?
                  AND s.ends_at   >= ?";
        $st = $db->prepare($sql);
        $st->execute([$ttype, $tid, $now, $now]);

        foreach ($st->fetchAll() as $row) {
            // Verificar dias da semana se repetição semanal
            $sched = $db->prepare("SELECT repeat_weekly, weekdays FROM schedules WHERE playlist_id=? AND target_type=? LIMIT 1");
            $sched->execute([$row['playlist_id'], $ttype]);
            $sr = $sched->fetch();
            if ($sr && $sr['repeat_weekly']) {
                $days = json_decode($sr['weekdays'] ?? '[]', true);
                if (!in_array($dow, $days)) continue;
            }
            $pl = playlist_full((int)$row['playlist_id']);
            if ($pl) return $pl;
        }
    }

    if ($direct_pl) {
        $pl = playlist_full($direct_pl);
        if ($pl) return $pl;
    }

    // Fallback: playlist padrão global
    $def = $db->query("SELECT id FROM playlists WHERE is_default=1 LIMIT 1")->fetchColumn();
    return $def ? playlist_full((int)$def) : null;
}

function playlist_full(int $id): ?array {
    $db = db();
    $pl = $db->prepare("SELECT * FROM playlists WHERE id=?");
    $pl->execute([$id]);
    $pl = $pl->fetch();
    if (!$pl) return null;

    $items = $db->prepare(
        "SELECT i.*, m.url AS media_url, m.type AS media_type, m.thumb_url
         FROM playlist_items i
         LEFT JOIN media m ON m.id = i.media_id
         WHERE i.playlist_id = ?
         ORDER BY i.sort_order, i.id"
    );
    $items->execute([$id]);
    $pl['items'] = $items->fetchAll();
    $pl['hash']  = md5(json_encode($pl['items']) . ($pl['updated_at'] ?? ''));
    return $pl;
}
