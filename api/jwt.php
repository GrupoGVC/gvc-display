<?php
function jwt_secret(): string {
    return $_ENV['JWT_SECRET'] ?? 'gvc_display_secret_2024';
}

function jwt_create(array $payload): string {
    $header  = base64url_encode(json_encode(['alg'=>'HS256','typ'=>'JWT']));
    $payload['iat'] = time();
    $payload['exp'] = time() + (int)($_ENV['JWT_TTL'] ?? 86400);
    $body    = base64url_encode(json_encode($payload));
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$body", jwt_secret(), true));
    return "$header.$body.$sig";
}

function jwt_verify(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $body, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$body", jwt_secret(), true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(base64url_decode($body), true);
    if (!$payload || (isset($payload['exp']) && $payload['exp'] < time())) return null;
    return $payload;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function auth_required(): array {
    // Apache pode bloquear HTTP_AUTHORIZATION — testa 3 fontes
    $h = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';
    // Fallback para mod_php no Apache
    if (!$h && function_exists('apache_request_headers')) {
        $apacheHeaders = apache_request_headers();
        $h = $apacheHeaders['Authorization'] ?? $apacheHeaders['authorization'] ?? '';
    }
    // Aceita também via query string (usado no upload)
    $t = $_GET['_token'] ?? '';
    if (!$h && !$t) json_err('Não autenticado', 401);
    if ($t) $token = $t;
    else {
        if (!preg_match('/^Bearer\s+(.+)$/i', $h, $m)) json_err('Token inválido', 401);
        $token = $m[1];
    }
    $payload = jwt_verify($token);
    if (!$payload) json_err('Token expirado ou inválido', 401);
    return $payload;
}
