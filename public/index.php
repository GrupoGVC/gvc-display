<?php
// Acesso direto a public/ — redireciona para a raiz
// O entry point real é /gvc-display/index.php
$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
header('Location: ' . $base . '/');
exit;
