<?php
include 'db_connection.php';

if (isset($_GET['id'])) {
    $arquivo_id = $_GET['id'];

    $stmt = $conn->prepare("SELECT selos.numero_selo FROM selos_arquivamentos INNER JOIN selos ON selos_arquivamentos.selo_id = selos.id WHERE selos_arquivamentos.arquivo_id = ?");
    $stmt->bind_param("i", $arquivo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selo = $result->fetch_assoc();
    $stmt->close();

    if ($selo) {
        echo json_encode($selo);
    } else {
        echo json_encode(['numero_selo' => 'Nenhum selo encontrado']);
    }
} else {
    echo json_encode(['numero_selo' => 'ID não fornecido']);
}
?>
