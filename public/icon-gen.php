<?php
// Gera ícones PNG a partir do SVG usando Imagick ou fallback com cor sólida
// Acesse: /gvc-display/assets/icons/icon-192.png (via .htaccess rewrite)
// Este arquivo é chamado apenas uma vez para gerar os PNGs

$size = (int)($_GET['size'] ?? 192);
$valid = [72, 96, 128, 144, 152, 192, 384, 512];
if (!in_array($size, $valid)) { http_response_code(400); exit; }

$outDir  = dirname(__DIR__) . '/assets/icons/';
$outFile = $outDir . "icon-{$size}.png";

if (file_exists($outFile)) {
    header('Content-Type: image/png');
    readfile($outFile);
    exit;
}

// Tenta gerar com GD + SVG simples (fundo + texto)
if (function_exists('imagecreatetruecolor')) {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    // Fundo arredondado simulado (GD não suporta border-radius nativamente)
    $bg   = imagecolorallocate($img, 13, 17, 23);     // #0d1117
    $blue = imagecolorallocate($img, 0, 170, 142);   // #00AA8E
    $dark = imagecolorallocate($img, 26, 31, 46);     // #1a1f2e

    imagefill($img, 0, 0, $bg);

    // Retângulo da TV
    $pad = (int)($size * 0.1);
    $tvH = (int)($size * 0.55);
    imagefilledrectangle($img, $pad, $pad, $size - $pad, $pad + $tvH, $dark);
    imagerectangle($img, $pad, $pad, $size - $pad, $pad + $tvH, $blue);

    // Texto "GVC"
    $fontSize = max(8, (int)($size * 0.2));
    $tx = (int)($size / 2);
    $ty = (int)($size * 0.45);
    if (function_exists('imagettftext') && file_exists('/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf')) {
        imagettftext($img, $fontSize, 0, $tx - (int)($fontSize * 1.5), $ty, $blue,
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', 'GVC');
    } else {
        imagestring($img, 5, $tx - 20, $ty - 8, 'GVC', $blue);
    }

    // Pé da TV
    $footY = $pad + $tvH + (int)($size * 0.04);
    imagefilledrectangle($img,
        (int)($size * 0.38), $footY,
        (int)($size * 0.62), $footY + (int)($size * 0.05),
        $blue);

    imagepng($img, $outFile);
    imagedestroy($img);

    header('Content-Type: image/png');
    readfile($outFile);
} else {
    // Sem GD — retorna um PNG 1x1 transparente
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
}
