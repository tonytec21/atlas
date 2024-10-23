<?php
include(__DIR__ . '/db_connection.php');

$id = $_POST['id'] ?? 0;
$nomeAnexo = $_POST['nome'] ?? '';

// Busca o registro atual
$query = "SELECT caminho_anexo FROM triagem_comunitario WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$registro = $result->fetch_assoc();

// Remove o anexo específico da lista
$caminhos = explode(';', $registro['caminho_anexo']);
$caminhos = array_filter($caminhos, function ($caminho) use ($nomeAnexo) {
    return basename($caminho) !== $nomeAnexo;
});
$novosCaminhos = implode(';', $caminhos);

// Atualiza o banco de dados com a nova lista de anexos
$queryUpdate = "UPDATE triagem_comunitario SET caminho_anexo = ? WHERE id = ?";
$stmtUpdate = $conn->prepare($queryUpdate);
$stmtUpdate->bind_param('si', $novosCaminhos, $id);

if ($stmtUpdate->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar o banco de dados.']);
}
?>
