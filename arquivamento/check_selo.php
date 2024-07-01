<?php
include 'db_connection.php';

$arquivo_id = $_GET['arquivo_id'];

$stmt = $conn->prepare("SELECT selos.* FROM selos_arquivamentos INNER JOIN selos ON selos_arquivamentos.selo_id = selos.id WHERE selos_arquivamentos.arquivo_id = ?");
$stmt->bind_param("i", $arquivo_id);
$stmt->execute();
$result = $stmt->get_result();
$selo = $result->fetch_assoc();
$stmt->close();

if ($selo) {
    error_log('Selo encontrado: ' . json_encode($selo));
    echo json_encode(['status' => 'success', 'selo' => $selo]);
} else {
    error_log('Nenhum selo encontrado para o arquivo_id: ' . $arquivo_id);
    echo json_encode(['status' => 'error', 'message' => 'Nenhum selo encontrado para este arquivo.']);
}
?>
