<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

$payload = auth_required();
$db      = db();
$m       = method();

$ALLOWED_IMAGE = ['image/jpeg','image/png','image/gif','image/webp'];
$ALLOWED_VIDEO = ['video/mp4','video/webm','video/ogg'];
$MAX_SIZE      = 100 * 1024 * 1024; // 100 MB
$UPLOAD_DIR    = __DIR__ . '/../../uploads/';

if ($m === 'GET') {
    $rows = $db->query("SELECT id, original_name AS original, type, url, size, created_at FROM media ORDER BY created_at DESC")->fetchAll();
    json_ok($rows);
}

if ($m === 'POST') {
    if (empty($_FILES['file'])) json_err('Nenhum arquivo enviado');
    $f    = $_FILES['file'];
    $mime = mime_content_type($f['tmp_name']);
    $size = $f['size'];

    if ($size > $MAX_SIZE) json_err('Arquivo muito grande (máx 100 MB)');

    if (in_array($mime, $ALLOWED_IMAGE)) {
        $type    = 'image';
        $subdir  = 'images/';
    } elseif (in_array($mime, $ALLOWED_VIDEO)) {
        $type   = 'video';
        $subdir = 'videos/';
    } else {
        json_err('Tipo de arquivo não permitido: ' . $mime);
    }

    $ext      = pathinfo($f['name'], PATHINFO_EXTENSION) ?: ($type === 'image' ? 'jpg' : 'mp4');
    $filename = md5(uniqid('', true)) . '.' . strtolower($ext);
    $dest     = $UPLOAD_DIR . $subdir . $filename;

    if (!is_dir($UPLOAD_DIR . $subdir)) mkdir($UPLOAD_DIR . $subdir, 0755, true);
    if (!move_uploaded_file($f['tmp_name'], $dest)) json_err('Falha ao salvar arquivo');

    $url = '/uploads/' . $subdir . $filename;

    $stmt = $db->prepare("INSERT INTO media (original_name, type, url, size) VALUES (?,?,?,?)");
    $stmt->execute([$f['name'], $type, $url, $size]);
    $id = (int)$db->lastInsertId();

    log_activity('upload_media', $payload['sub'], $f['name']);
    json_ok(['id' => $id, 'original' => $f['name'], 'type' => $type, 'url' => $url, 'size' => $size]);
}

if ($m === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_err('ID inválido');

    $stmt = $db->prepare("SELECT url, original_name FROM media WHERE id=?");
    $stmt->execute([$id]);
    $media = $stmt->fetch();
    if (!$media) json_err('Mídia não encontrada', 404);

    // Tenta remover o arquivo físico
    $file = __DIR__ . '/../../' . ltrim($media['url'], '/');
    if (file_exists($file)) @unlink($file);

    $db->prepare("UPDATE playlist_items SET media_id=NULL WHERE media_id=?")->execute([$id]);
    $db->prepare("DELETE FROM media WHERE id=?")->execute([$id]);

    log_activity('delete_media', $payload['sub'], $media['original_name']);
    json_ok(['deleted' => $id]);
}

json_err('Método não permitido', 405);
