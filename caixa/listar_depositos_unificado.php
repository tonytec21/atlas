<?php
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

$data = $_GET['data'];

try {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("
        SELECT id, funcionario, data_caixa, data_cadastro, valor_do_deposito, tipo_deposito, caminho_anexo
        FROM deposito_caixa
        WHERE DATE(data_caixa) = :data_caixa AND status = 'ativo'
    ");
    $stmt->bindParam(':data_caixa', $data);
    $stmt->execute();
    $depositos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['depositos' => $depositos]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
