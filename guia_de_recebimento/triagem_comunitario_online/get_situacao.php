<?php
include(__DIR__ . '/db_connection.php');

// Verifica se o ID foi enviado
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID não fornecido.']);
    exit;
}

$id = $_GET['id'];

// Consulta para obter as informações das situações
$query = "SELECT pedido_deferido, cadastro_efetivado, processo_concluido, 
                 habilitacao_concluida, numero_proclamas 
          FROM triagem_comunitario WHERE id = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Erro na preparação da consulta: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $situacao = $result->fetch_assoc();
    echo json_encode(['success' => true, 'situacao' => $situacao]);
} else {
    echo json_encode(['success' => false, 'error' => 'Registro não encontrado.']);
}
?>
