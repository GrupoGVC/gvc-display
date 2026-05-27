<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = sint($_GET['id'] ?? 0);

if ($method === 'GET') {
    auth();
    $type = s($_GET['type'] ?? '', 10);
    $sql  = "SELECT * FROM media";
    $params = [];
    if ($type === 'image' || $type === 'video') { $sql .= " WHERE type=?"; $params[] = $type; }
    $sql .= " ORDER BY created_at DESC";
    $st = db()->prepare($sql);
    $st->execute($params);
    json_ok($st->fetchAll());
}

if ($method === 'POST') {
    $a = auth_admin();

    if (empty($_FILES['file'])) json_err('Nenhum arquivo enviado', 422);
    $file     = $_FILES['file'];
    $maxbytes = UPLOAD_MAX_MB * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK)   json_err('Erro no upload: código ' . $file['error'], 422);
    if ($file['size']  > $maxbytes)          json_err('Arquivo excede ' . UPLOAD_MAX_MB . 'MB', 422);

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = array_merge(UPLOAD_IMG_TYPES, UPLOAD_VID_TYPES);
    if (!in_array($mime, $allowed)) json_err("Tipo não permitido: $mime", 422);

    $type   = in_array($mime, UPLOAD_IMG_TYPES) ? 'image' : 'video';
    $ext    = strtolower(preg_replace('/[^a-z0-9]/i', '', pathinfo($file['name'], PATHINFO_EXTENSION)) ?: ($type === 'image' ? 'jpg' : 'mp4'));
    $fname  = bin2hex(random_bytes(16)) . '.' . $ext;
    $subdir = $type === 'image' ? 'images/' : 'videos/';
    $dest   = UPLOAD_DIR . $subdir . $fname;

    if (!is_dir(UPLOAD_DIR . $subdir)) mkdir(UPLOAD_DIR . $subdir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) json_err('Falha ao salvar arquivo', 500);

    $url = APP_URL . '/uploads/' . $subdir . $fname;
    db()->prepare("INSERT INTO media (filename,original,type,mime,size,url,uploaded_by) VALUES (?,?,?,?,?,?,?)")
        ->execute([$fname, s($file['name'], 255), $type, $mime, $file['size'], $url, (int)$a['sub']]);
    $new_id = (int)db()->lastInsertId();

    log_act((int)$a['sub'], 'upload_media', 'media', $new_id, s($file['name']));
    json_ok(['id' => $new_id, 'url' => $url, 'type' => $type, 'mime' => $mime,
             'original' => s($file['name']), 'size' => $file['size']], 201);
}

if ($method === 'DELETE') {
    auth_admin();
    if (!$id) json_err('ID obrigatório', 422);
    $row = db()->prepare("SELECT filename,type FROM media WHERE id=?");
    $row->execute([$id]);
    $row = $row->fetch();
    if (!$row) json_err('Mídia não encontrada', 404);

    $subdir = $row['type'] === 'image' ? 'images/' : 'videos/';
    $path   = UPLOAD_DIR . $subdir . $row['filename'];
    if (file_exists($path)) @unlink($path);

    db()->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
    json_ok(['deleted' => $id]);
}

json_err('Método não suportado', 405);
