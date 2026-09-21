<?php

namespace Core;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;
    private function __construct() {}

    public static function getConnection(): PDO {
        if(self::$instance === null) {
            $host = '127.0.0.1';
            $db = 'intranet';
            $user = 'root';
            $password = '';
            $charset = 'utf8mb4';


            $dns = "mysql:host$host;dbname=$db;charset=$charset";

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