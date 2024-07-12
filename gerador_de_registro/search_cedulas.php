<?php
include(__DIR__ . '/session_check.php');
include(__DIR__ . '/db_connection.php');
checkSession();

$n_cedula = $_GET['n_cedula'] ?? '';
$credor = $_GET['credor'] ?? '';
$emitente = $_GET['emitente'] ?? '';
$data_emissao = $_GET['data_emissao'] ?? '';
$data_vencimento = $_GET['data_vencimento'] ?? '';
$valor_cedula = $_GET['valor_cedula'] ?? '';
$matricula = $_GET['matricula'] ?? '';
$funcionario = $_GET['funcionario'] ?? '';
$registro_garantia = $_GET['registro_garantia'] ?? '';
$forma_de_pagamento = $_GET['forma_de_pagamento'] ?? '';
$vencimento_antecipado = $_GET['vencimento_antecipado'] ?? '';
$juros = $_GET['juros'] ?? '';

$query = "SELECT * FROM registros_cedulas WHERE 1=1";

if ($n_cedula) {
    $query .= " AND n_cedula LIKE '%" . $conn->real_escape_string($n_cedula) . "%'";
}
if ($credor) {
    $query .= " AND credor LIKE '%" . $conn->real_escape_string($credor) . "%'";
}
if ($emitente) {
    $query .= " AND emitente LIKE '%" . $conn->real_escape_string($emitente) . "%'";
}
if ($data_emissao) {
    $query .= " AND emissao_cedula = '" . $conn->real_escape_string($data_emissao) . "'";
}
if ($data_vencimento) {
    $query .= " AND vencimento_cedula = '" . $conn->real_escape_string($data_vencimento) . "'";
}
if ($valor_cedula) {
    $query .= " AND valor_cedula LIKE '%" . $conn->real_escape_string(str_replace(',', '.', str_replace('.', '', $valor_cedula))) . "%'";
}
if ($matricula) {
    $query .= " AND matricula LIKE '%" . $conn->real_escape_string($matricula) . "%'";
}
if ($funcionario) {
    $query .= " AND funcionario = '" . $conn->real_escape_string($funcionario) . "'";
}
if ($registro_garantia) {
    $query .= " AND registro_garantia LIKE '%" . $conn->real_escape_string($registro_garantia) . "%'";
}
if ($forma_de_pagamento) {
    $query .= " AND forma_de_pagamento LIKE '%" . $conn->real_escape_string($forma_de_pagamento) . "%'";
}
if ($vencimento_antecipado) {
    $query .= " AND vencimento_antecipado LIKE '%" . $conn->real_escape_string($vencimento_antecipado) . "%'";
}
if ($juros) {
    $query .= " AND juros LIKE '%" . $conn->real_escape_string($juros) . "%'";
}

$result = $conn->query($query);
$cedulas = [];
while ($row = $result->fetch_assoc()) {
    $cedulas[] = $row;
}

echo json_encode($cedulas);

$conn->close();
?>
