<?php
include(__DIR__ . '/db_connection.php');
header('Content-Type: application/json');

$filtros = [
    'proprietario' => $_GET['proprietario'] ?? '',
    'cpfProprietario' => preg_replace('/\D/', '', $_GET['cpfProprietario'] ?? ''),
    'logradouro' => $_GET['logradouro'] ?? '',
    'cidade' => $_GET['cidade'] ?? ''
];

$query = "SELECT id, CONCAT(logradouro, ', Qd. ', quadra, ', nº ', numero, ', ', bairro, ', ', cidade) AS endereco, proprietario_nome AS proprietario, conjuge AS conjuge 
          FROM cadastro_de_imoveis 
          WHERE 1=1";

$params = [];
$types = '';

if ($filtros['proprietario']) {
    $query .= " AND proprietario_nome LIKE ?";
    $params[] = '%' . $filtros['proprietario'] . '%';
    $types .= 's';
}

if ($filtros['cpfProprietario']) {
    $query .= " AND proprietario_cpf = ?";
    $params[] = $filtros['cpfProprietario'];
    $types .= 's';
}

if ($filtros['logradouro']) {
    $query .= " AND logradouro LIKE ?";
    $params[] = '%' . $filtros['logradouro'] . '%';
    $types .= 's';
}

if ($filtros['cidade']) {
    $query .= " AND cidade LIKE ?";
    $params[] = '%' . $filtros['cidade'] . '%';
    $types .= 's';
}

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$imoveis = [];
while ($row = $result->fetch_assoc()) {
    $imoveis[] = $row;
}

echo json_encode(['success' => true, 'results' => $imoveis]);
$stmt->close();
$conn->close();
