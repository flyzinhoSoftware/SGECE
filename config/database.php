<?php

namespace Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection === null) {
            try {
                $host = $_ENV['DB_HOST'];
                $port = $_ENV['DB_PORT'];
                $database = $_ENV['DB_NAME'];
                $username = $_ENV['DB_USER'];
                $password = $_ENV['DB_PASS'];

                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

                self::$connection = new PDO(
                    $dsn,
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );

                return self::$connection;

            } catch (PDOException $e) {
                error_log($e->getMessage());

                throw new PDOException(
                    "Não foi possível estabelecer ligação à base de dados."
                );
            }
        }

        return self::$connection;
    }
}