<?php
function getDatabaseConnection() {
    $host = 'localhost'; // Substitua pelo seu host
    $db   = 'atlas'; // Substitua pelo seu banco de dados
    $user = 'root'; // Substitua pelo seu usuário do banco de dados
    $pass = ''; // Substitua pela sua senha do banco de dados
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

    return $pdo;
}
?>
