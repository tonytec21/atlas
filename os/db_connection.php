<?php
require_once __DIR__ . '/atlas_tempo.php';

if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection() {
        $host = 'localhost';
        $db   = 'atlas';
        $user = 'root';
        $pass = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }

        /* Alinha a sessão do MySQL ao fuso da serventia (-03:00), para que
           NOW() e DEFAULT CURRENT_TIMESTAMP gravem a hora correta. */
        atlas_alinhar_fuso($pdo);

        return $pdo;
    }
}
?>
