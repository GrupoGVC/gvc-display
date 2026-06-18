<?php
// Diagnóstico completo do ambiente Apache
// Acesse: http://localhost/gvc-display/public/diagnose.php
// com POST: curl -X POST http://localhost/gvc-display/public/diagnose.php -d "test=1"
header('Content-Type: application/json');
echo json_encode([
    'REQUEST_METHOD'   => $_SERVER['REQUEST_METHOD'] ?? 'AUSENTE',
    'REQUEST_URI'      => $_SERVER['REQUEST_URI'] ?? 'AUSENTE',
    'SCRIPT_NAME'      => $_SERVER['SCRIPT_NAME'] ?? 'AUSENTE',
    'SCRIPT_FILENAME'  => $_SERVER['SCRIPT_FILENAME'] ?? 'AUSENTE',
    'HTTP_HOST'        => $_SERVER['HTTP_HOST'] ?? 'AUSENTE',
    'CONTENT_TYPE'     => $_SERVER['CONTENT_TYPE'] ?? 'AUSENTE',
    'CONTENT_LENGTH'   => $_SERVER['CONTENT_LENGTH'] ?? 'AUSENTE',
    'php_input_raw'    => file_get_contents('php://input'),
    'POST_data'        => $_POST,
    'GET_data'         => $_GET,
    'AllowOverride_ok' => function_exists('apache_get_modules') 
                          ? in_array('mod_rewrite', apache_get_modules()) 
                          : 'não verificável',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
