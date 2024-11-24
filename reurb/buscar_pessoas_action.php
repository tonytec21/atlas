<?php
include(__DIR__ . '/db_connection.php');

$cpf = $_GET['cpf'] ?? '';
$rg = $_GET['rg'] ?? '';
$nome = $_GET['nome'] ?? '';
$filiacao = $_GET['filiacao'] ?? '';

$query = "SELECT id, nome, data_de_nascimento, cpf, rg, filiacao, estado_civil FROM cadastro_de_pessoas WHERE 1=1";
$params = [];

if ($cpf) {
    $query .= " AND cpf = ?";
    $params[] = $cpf;
}
if ($rg) {
    $query .= " AND rg LIKE ?";
    $params[] = "%$rg%";
}
if ($nome) {
    $query .= " AND nome LIKE ?";
    $params[] = "%$nome%";
}
if ($filiacao) {
    $query .= " AND filiacao LIKE ?";
    $params[] = "%$filiacao%";
}

$stmt = $conn->prepare($query);
$stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$result = $stmt->get_result();

$dados = [];
while ($row = $result->fetch_assoc()) {
    $dados[] = $row;
}
echo json_encode($dados);
