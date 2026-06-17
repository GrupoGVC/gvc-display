<?php
namespace App\Core;

class JWT
{
    private string $secret;
    private int    $ttl;

    public function __construct()
    {
        $this->secret = $_ENV['JWT_SECRET'] ?? 'gvc_display_secret';
        $this->ttl    = (int)($_ENV['JWT_TTL'] ?? 86400);
    }

    public function encode(array $payload): string
    {
        $header  = $this->b64e(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = array_merge($payload, ['iat' => time(), 'exp' => time() + $this->ttl]);
        $body    = $this->b64e(json_encode($payload));
        $sig     = $this->b64e(hash_hmac('sha256', "$header.$body", $this->secret, true));
        return "$header.$body.$sig";
    }

    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $body, $sig] = $parts;
        $expected = $this->b64e(hash_hmac('sha256', "$header.$body", $this->secret, true));
        if (!hash_equals($expected, $sig)) return null;

        $payload = json_decode($this->b64d($body), true);
        if (!$payload) return null;
        if (isset($payload['exp']) && $payload['exp'] < time()) return null;

        return $payload;
    }

    private function b64e(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function b64d(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
