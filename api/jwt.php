<?php
// ============================================================
//  GVC Signage — JWT puro (HMAC-SHA256, sem biblioteca)
// ============================================================

declare(strict_types=1);

class JWT {

    public static function encode(array $payload, string $secret): string {
        $h = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p = self::b64(json_encode($payload));
        $s = self::b64(hash_hmac('sha256', "$h.$p", $secret, true));
        return "$h.$p.$s";
    }

    public static function decode(string $token, string $secret): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$h, $p, $s] = $parts;

        $expected = self::b64(hash_hmac('sha256', "$h.$p", $secret, true));
        if (!hash_equals($expected, $s)) return null;

        $payload = json_decode(self::unb64($p), true);
        if (!is_array($payload)) return null;
        if (isset($payload['exp']) && $payload['exp'] < time()) return null;

        return $payload;
    }

    private static function b64(string $d): string {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }

    private static function unb64(string $d): string {
        return base64_decode(strtr($d, '-_', '+/') . str_repeat('=', (4 - strlen($d) % 4) % 4));
    }
}
