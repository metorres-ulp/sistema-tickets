<?php
require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log the actual error; do not expose it to the user
                error_log('Database connection error: ' . $e->getMessage());
                $isApi = (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'json') ||
                          str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'json'));
                if ($isApi) {
                    header('Content-Type: application/json');
                    die(json_encode(['error' => 'Error de conexión a la base de datos.']));
                }
                die('<div style="font-family:sans-serif;padding:2rem;text-align:center"><h2>Error de conexión</h2><p>No se pudo conectar a la base de datos. Por favor contacta al administrador del sistema.</p></div>');
            }
        }
        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
}

function db(): PDO {
    return Database::getInstance();
}
