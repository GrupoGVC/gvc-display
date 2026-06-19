<?php

namespace App\Services;

use PDO;
use Throwable;

class ContentVersionService
{
    private static ?PDO $pdo = null;

    public static function bump(string $name = 'player_content'): void
    {
        try {
            $stmt = self::db()->prepare(
                'INSERT INTO content_versions (name, version)
                 VALUES (:name, 1)
                 ON DUPLICATE KEY UPDATE version = version + 1, updated_at = NOW()'
            );
            $stmt->execute(['name' => $name]);
        } catch (Throwable $e) {
            // Evita quebrar ações do sistema caso a tabela de versão ainda não exista.
        }
    }

    public static function get(string $name = 'player_content'): int
    {
        try {
            $stmt = self::db()->prepare('SELECT version FROM content_versions WHERE name = :name LIMIT 1');
            $stmt->execute(['name' => $name]);
            return (int) ($stmt->fetchColumn() ?: 1);
        } catch (Throwable $e) {
            return 1;
        }
    }

    private static function db(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $env = self::env();
        $host = $env['DB_HOST'] ?? '127.0.0.1';
        $port = $env['DB_PORT'] ?? '3307';
        $name = $env['DB_NAME'] ?? 'db_gvc_display';
        $user = $env['DB_USER'] ?? 'root';
        $pass = $env['DB_PASS'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }

    private static function env(): array
    {
        $env = $_ENV;
        $file = dirname(__DIR__, 2) . '/.env';

        if (!is_file($file)) {
            return $env;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $env;
    }
}
