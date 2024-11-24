<?php
include(__DIR__ . '/db_connection.php');
header('Content-Type: application/json');

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID do imóvel não fornecido.']);
    exit;
}

$query = "SELECT 
            CONCAT(logradouro, ', Qd. ', quadra, ', nº ', numero, ', ', bairro, ', ', cidade) AS endereco,
            proprietario_nome AS proprietario,
            conjuge AS conjuge,
            proprietario_cpf,
            nome_conjuge,
            cpf_conjuge,
            area_do_lote,
            perimetro,
            area_construida,
            processo_adm,
            memorial_descritivo,
            DATE_FORMAT(data_cadastro, '%d/%m/%Y') AS data_cadastro
          FROM cadastro_de_imoveis 
          WHERE id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Imóvel não encontrado.']);
} else {
    echo json_encode(['success' => true] + $result->fetch_assoc());
}

$stmt->close();
$conn->close();
