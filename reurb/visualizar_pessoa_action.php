<?php
include(__DIR__ . '/db_connection.php');

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

$query = "SELECT * FROM cadastro_de_pessoas WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $pessoa = $result->fetch_assoc();

    // Formatação de CPF e Data de Nascimento
    $pessoa['cpf'] = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $pessoa['cpf']);
    $pessoa['data_de_nascimento'] = date('d/m/Y', strtotime($pessoa['data_de_nascimento']));

    echo json_encode($pessoa);
} else {
    echo json_encode(['error' => 'Pessoa não encontrada']);
}
$stmt->close();
$conn->close();
