<?php

namespace Core;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;
    private function __construct() {}

    public static function getConnection(): PDO {
        if(self::$instance === null) {

            Env::load(__DIR__.'/../.env');

            $host     = Env::get('DB_HOST', '127.0.0.1');
            $db       = Env::get('DB_DATABASE', 'intranet');
            $user     = Env::get('DB_USERNAME', 'root');
            $password = Env::get('DB_PASSWORD', '');
            $charset  = Env::get('DB_CHARSET', 'utf8mb4');
            $port     = Env::get('DB_PORT', '3306');


            $dns = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$instance = new PDO($dns, $user, $password, $options);
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());

            }
        }

        return self::$instance;
    }
}