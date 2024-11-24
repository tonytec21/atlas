<?php
include(__DIR__ . '/db_connection.php');

$cpf = $_POST['cpf'] ?? '';
$response = ['existe' => false, 'nome' => ''];

if ($cpf) {
    // Remove todos os caracteres não numéricos do CPF enviado
    $cpf = preg_replace('/\D/', '', $cpf);

    // Consulta no banco, ignorando a formatação do CPF
    $stmt = $conn->prepare("SELECT nome FROM cadastro_de_pessoas WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?");
    $stmt->bind_param("s", $cpf);
    $stmt->execute();
    $stmt->bind_result($nome);
    if ($stmt->fetch()) {
        $response = ['existe' => true, 'nome' => $nome];
    }
    $stmt->close();
}

echo json_encode($response);
