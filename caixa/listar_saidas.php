<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

try {
    $funcionario = $_GET['funcionario'];
    $data_caixa = $_GET['data_caixa'];

    $conn = getDatabaseConnection();

    $sql = 'SELECT * FROM saidas_despesas WHERE funcionario = :funcionario AND DATE(data_caixa) = :data_caixa AND status = "ativo"';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->bindParam(':data_caixa', $data_caixa);
    $stmt->execute();
    $saidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['saidas' => $saidas]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
