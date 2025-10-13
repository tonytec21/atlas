<?php
/**
 * Conexão unificada com o banco:
 *  - $conn  => mysqli (usado pelos scripts que chamam bind_param, prepare(), etc.)
 *  - getDatabaseConnection() => retorna um PDO (compatibilidade, se algum script usar PDO)
 *
 * Charset: utf8mb4
 */

if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli)) {
    // Configurações do banco
    $DB_HOST = 'localhost';
    $DB_NAME = 'atlas';
    $DB_USER = 'root';
    $DB_PASS = '';
    $DB_CHARSET = 'utf8mb4';

    // Conexão mysqli (padrão do sistema)
    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn->connect_errno) {
        // Pare com mensagem clara (você pode trocar por um throw se preferir)
        die('Erro ao conectar ao MySQL (mysqli): ' . $conn->connect_error);
    }

    // Define charset
    if (!$conn->set_charset($DB_CHARSET)) {
        // Não aborta, mas loga/avisa
        error_log('Aviso: não foi possível definir charset para ' . $DB_CHARSET . ' em mysqli.');
    }

    // Disponibiliza no escopo global
    $GLOBALS['conn'] = $conn;
}

/**
 * (Opcional) Função compatível que retorna um PDO.
 * Só é definida se ainda não existir, para evitar redeclaração.
 */
if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection(): PDO {
        $host    = 'localhost';
        $db      = 'atlas';
        $user    = 'root';
        $pass    = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Propaga o erro de forma clara
            throw new PDOException('Erro ao conectar ao MySQL (PDO): ' . $e->getMessage(), (int)$e->getCode());
        }

        return $pdo;
    }
}
?>
