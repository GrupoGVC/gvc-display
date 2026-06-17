<?php
namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance) return self::$instance;

        $cfg = require ROOT . '/config/database.php';
        try {
            self::$instance = new PDO(
                "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset=utf8mb4",
                $cfg['user'], $cfg['pass'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(503);
            echo json_encode(['error' => 'Banco indisponível: ' . $e->getMessage()]);
            exit;
        }
        return self::$instance;
    }
}
