<?php
include(__DIR__ . '/db_connection.php');

// Obter os dados do formulário
$guiaId = isset($_POST['guiaId']) ? $_POST['guiaId'] : '';
$cliente = isset($_POST['cliente']) ? $_POST['cliente'] : '';
$documentoApresentante = isset($_POST['documentoApresentante']) ? $_POST['documentoApresentante'] : '';
$documentosRecebidos = isset($_POST['documentosRecebidos']) ? $_POST['documentosRecebidos'] : '';
$observacoes = isset($_POST['observacoes']) ? $_POST['observacoes'] : '';

// Verificar se o ID da guia foi fornecido
if (empty($guiaId)) {
    echo json_encode(['success' => false, 'message' => 'ID da guia não fornecido.']);
    exit;
}

// Atualizar os dados da guia no banco de dados
$sql = "UPDATE guia_de_recebimento SET cliente = ?, documento_apresentante = ?, documentos_recebidos = ?, observacoes = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssssi', $cliente, $documentoApresentante, $documentosRecebidos, $observacoes, $guiaId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar as alterações.']);
}

$stmt->close();
$conn->close();
?>
