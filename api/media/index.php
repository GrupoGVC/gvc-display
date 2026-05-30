<?php

declare(strict_types=1);
@ini_set('display_errors', '0');
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = sint($_GET['id'] ?? 0);

if ($method === 'GET') {
    auth();
    $type = s($_GET['type'] ?? '', 10);
    $sql  = "SELECT * FROM media";
    $params = [];
    if ($type === 'image' || $type === 'video') {
        $sql .= " WHERE type=?";
        $params[] = $type;
    }
    $sql .= " ORDER BY created_at DESC";
    $st = db()->prepare($sql);
    $st->execute($params);
    json_ok($st->fetchAll());
}

if ($method === 'POST') {
    $a = auth_admin();

    // Se post_max_size foi excedido, $_FILES fica vazio e $_SERVER indica o problema
    if ($_SERVER['CONTENT_LENGTH'] > 0 && empty($_FILES) && empty($_POST)) {
        json_err('Arquivo excede o limite do servidor. Reduza o tamanho ou ajuste post_max_size no php.ini', 422);
    }

    if (empty($_FILES['file'])) json_err('Nenhum arquivo enviado', 422);
    $file     = $_FILES['file'];
    $maxbytes = UPLOAD_MAX_MB * 1024 * 1024;

    // Erros do PHP no upload
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => 'Arquivo excede upload_max_filesize no php.ini (' . ini_get('upload_max_filesize') . ')',
        UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede MAX_FILE_SIZE do formulário',
        UPLOAD_ERR_PARTIAL    => 'Upload incompleto — tente novamente',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo recebido',
        UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada no servidor',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo no disco',
        UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por extensão PHP',
    ];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_err($upload_errors[$file['error']] ?? 'Erro no upload: código ' . $file['error'], 422);
    }
    if ($file['size'] > $maxbytes) json_err('Arquivo excede ' . UPLOAD_MAX_MB . 'MB', 422);

    // finfo pode falhar em alguns ambientes XAMPP — usa fallback por extensão
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? $finfo->file($file['tmp_name']) : '';

    // Fallback: detecta pelo nome do arquivo se finfo falhar ou retornar genérico
    if (!$mime || $mime === 'application/octet-stream') {
        $ext_check = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime_map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'mov'  => 'video/quicktime',
            'mkv' => 'video/x-matroska',
            'avi' => 'video/x-msvideo',
        ];
        $mime = $mime_map[$ext_check] ?? $mime ?? 'application/octet-stream';
    }
    $allowed = array_merge(UPLOAD_IMG_TYPES, UPLOAD_VID_TYPES);
    if (!in_array($mime, $allowed)) json_err("MIME detectado: {$mime} | nao permitido", 422);

    $type   = in_array($mime, UPLOAD_IMG_TYPES) ? 'image' : 'video';
    // Extrai extensão de forma segura — nome pode ter caracteres especiais/unicode
    $orig_name = mb_convert_encoding($file['name'], 'UTF-8', 'UTF-8');
    $ext_raw   = pathinfo($orig_name, PATHINFO_EXTENSION);
    $ext       = strtolower(preg_replace('/[^a-z0-9]/i', '', $ext_raw) ?: ($type === 'image' ? 'jpg' : 'mp4'));
    if (!$ext) $ext = $type === 'image' ? 'jpg' : 'mp4';
    $fname  = bin2hex(random_bytes(16)) . '.' . $ext;
    $subdir = $type === 'image' ? 'images/' : 'videos/';
    $dest   = UPLOAD_DIR . $subdir . $fname;

    if (!is_dir(UPLOAD_DIR . $subdir)) mkdir(UPLOAD_DIR . $subdir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) json_err('Falha ao salvar arquivo', 500);

    // Salva path relativo — o frontend constrói a URL absoluta com BASE do api.js
    // Isso evita 404 quando APP_URL difere da URL de acesso atual
    $url = '/uploads/' . $subdir . $fname;
    db()->prepare("INSERT INTO media (filename,original,type,mime,size,url,uploaded_by) VALUES (?,?,?,?,?,?,?)")
        ->execute([$fname, s($file['name'], 255), $type, $mime, $file['size'], $url, (int)$a['sub']]);
    $new_id = (int)db()->lastInsertId();

    log_act((int)$a['sub'], 'upload_media', 'media', $new_id, s($file['name']));
    json_ok([
        'id' => $new_id,
        'url' => $url,
        'type' => $type,
        'mime' => $mime,
        'original' => s($file['name']),
        'size' => $file['size']
    ], 201);
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
