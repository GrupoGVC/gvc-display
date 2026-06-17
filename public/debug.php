<?php
// Diagnóstico completo de senha + fix automático
// Acesse: http://localhost/gvc-display-mvc/public/debug.php
// DELETE após resolver

$envFile = dirname(__DIR__) . '/.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}

try {
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'], $_ENV['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('<pre>ERRO BANCO: ' . $e->getMessage() . '</pre>');
}

$users = $pdo->query("SELECT id, name, email, password_hash FROM users")->fetchAll();

echo '<pre style="font-family:monospace;font-size:14px;background:#1a1a1a;color:#fff;padding:20px;">';
echo "=== USUÁRIOS NO BANCO ===\n\n";

foreach ($users as $u) {
    echo "ID: {$u['id']} | Email: {$u['email']} | Nome: {$u['name']}\n";
    echo "Hash: {$u['password_hash']}\n";
    
    $tests = ['admin123', 'password', 'admin', '123456', 'gvc123', 'admin@gvc.com'];
    foreach ($tests as $pwd) {
        if (password_verify($pwd, $u['password_hash'])) {
            echo "✅ SENHA ENCONTRADA: '$pwd'\n";
        }
    }
    echo "\n";
}

// Gera novo hash e atualiza
$newHash = password_hash('admin123', PASSWORD_BCRYPT);
$pdo->prepare("UPDATE users SET password_hash=? WHERE email='admin@gvc.com'")->execute([$newHash]);
$verify = password_verify('admin123', $newHash);

echo "=== FIX APLICADO ===\n";
echo "Novo hash: $newHash\n";
echo "Verificação imediata: " . ($verify ? '✅ OK' : '❌ FALHOU') . "\n\n";

// Relê do banco para confirmar
$check = $pdo->query("SELECT password_hash FROM users WHERE email='admin@gvc.com'")->fetch();
echo "Hash salvo no banco: {$check['password_hash']}\n";
echo "Bate com 'admin123': " . (password_verify('admin123', $check['password_hash']) ? '✅ SIM' : '❌ NÃO') . "\n";

echo "\n=== TENTE LOGAR AGORA ===\n";
echo "Email: admin@gvc.com\n";
echo "Senha: admin123\n";
echo '</pre>';
