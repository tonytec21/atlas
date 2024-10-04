<?php
include(__DIR__ . '/db_connection.php');

// Verificar se o ID da guia foi fornecido
$guiaId = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($guiaId)) {
    echo json_encode(['success' => false, 'message' => 'ID da guia não fornecido.']);
    exit;
}

// Consulta para obter os dados da guia
$sql = "SELECT id, cliente, documento_apresentante, documentos_recebidos, observacoes FROM guia_de_recebimento WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $guiaId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $guia = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $guia]);
} else {
    echo json_encode(['success' => false, 'message' => 'Guia não encontrada.']);
}

$stmt->close();
$conn->close();
?>
