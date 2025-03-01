<?php
include(__DIR__ . '/db_connection.php');
header('Content-Type: application/json');

try {
    $conn = getDatabaseConnection();
    $stmt = $conn->query("SELECT id, nome_modelo FROM modelos_de_orcamento ORDER BY nome_modelo ASC");
    $modelos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['modelos' => $modelos]);
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
