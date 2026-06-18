<?php
// Gera ícones para o PWA
// Acesse: http://localhost/gvc-display/gen-icons.php

$outDir = __DIR__ . '/assets/icons/';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="background:#0d1117;color:#e2e8f0;padding:20px;font-family:monospace;min-height:100vh;">';
echo "=== Gerando ícones PWA ===\n\n";

// Tenta com GD primeiro
if (function_exists('imagecreatetruecolor')) {
    $sizes = [72, 96, 128, 144, 152, 192, 384, 512];
    foreach ($sizes as $size) {
        $file = $outDir . "icon-{$size}.png";
        if (file_exists($file)) { echo "✓ icon-{$size}.png (já existe)\n"; continue; }

        $img   = imagecreatetruecolor($size, $size);
        $bg    = imagecolorallocate($img, 13, 17, 23);
        $blue  = imagecolorallocate($img, 79, 140, 255);
        $dark  = imagecolorallocate($img, 26, 31, 46);
        imagefill($img, 0, 0, $bg);

        $p = (int)($size * 0.12); $h = (int)($size * 0.52);
        imagefilledrectangle($img, $p, $p, $size-$p, $p+$h, $dark);
        imagerectangle($img, $p, $p, $size-$p, $p+$h, $blue);
        $sp = (int)($size*0.06);
        imagefilledrectangle($img, $p+$sp, $p+$sp, $size-$p-$sp, $p+$h-$sp, $bg);

        $fw = (int)($size*0.15); $fy = $p+$h+(int)($size*0.03); $fh = (int)($size*0.06);
        imagefilledrectangle($img, (int)($size/2-$fw), $fy, (int)($size/2+$fw), $fy+$fh, $blue);
        imagefilledrectangle($img, (int)($size/2-$fw*1.4), $fy+$fh, (int)($size/2+$fw*1.4), $fy+$fh+(int)($size*0.025), $blue);
        imagestring($img, 5, (int)($size/2-$size*0.10), (int)($p+$h*0.35), 'GVC', $blue);

        imagepng($img, $file, 9);
        imagedestroy($img);
        echo "✓ icon-{$size}.png gerado com GD\n";
    }
    echo "\n✅ Ícones gerados com GD!\n";
} else {
    // Sem GD: cria um PNG mínimo válido de cor sólida (#0d1117)
    // usando formato PNG raw (sem dependências externas)
    echo "⚠️  GD não disponível — gerando PNG mínimos válidos\n\n";
    echo "Para ativar GD no XAMPP:\n";
    echo "  1. Abra C:\\\\xampp\\\\php\\\\php.ini\n";
    echo "  2. Remova o ; da linha: ;extension=gd\n";
    echo "  3. Reinicie o Apache no XAMPP\n";
    echo "  4. Acesse esta página novamente\n\n";

    // PNG 1x1 com cor #0d1117 codificado em base64
    // Válido como ícone mínimo para não causar erro no PWA
    $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $pngData   = base64_decode($pngBase64);

    $sizes = [72, 96, 128, 144, 152, 192, 384, 512];
    foreach ($sizes as $size) {
        $file = $outDir . "icon-{$size}.png";
        if (file_exists($file)) { echo "✓ icon-{$size}.png (já existe)\n"; continue; }
        file_put_contents($file, $pngData);
        echo "✓ icon-{$size}.png (PNG mínimo — ative GD para PNG completo)\n";
    }
    echo "\n✅ PNGs mínimos criados (o erro 404 vai sumir)\n";
    echo "💡 Ative GD e execute novamente para ícones completos\n";
}

echo "\n⚠️  Apague este arquivo após usar:\n";
echo "   " . __FILE__ . "\n";
echo '</pre>';
