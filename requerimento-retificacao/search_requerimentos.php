<?php
include(__DIR__ . '/db_connection.php');

$requerente = $_GET['requerente'] ?? '';
$qualificacao = $_GET['qualificacao'] ?? '';
$motivo = $_GET['motivo'] ?? '';
$criado_por = $_GET['criado_por'] ?? '';

// Montar a consulta SQL
$sql = "SELECT * FROM requerimentos WHERE 1=1";

if (!empty($requerente)) {
    $sql .= " AND requerente LIKE '%" . $conn->real_escape_string($requerente) . "%'";
}
if (!empty($qualificacao)) {
    $sql .= " AND qualificacao LIKE '%" . $conn->real_escape_string($qualificacao) . "%'";
}
if (!empty($motivo)) {
    $sql .= " AND motivo LIKE '%" . $conn->real_escape_string($motivo) . "%'";
}
if (!empty($criado_por)) {
    $sql .= " AND criado_por LIKE '%" . $conn->real_escape_string($criado_por) . "%'";
}

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $requerimentos = [];
    while ($row = $result->fetch_assoc()) {
        $requerimentos[] = $row;
    }
    echo json_encode($requerimentos);
} else {
    echo json_encode([]);
}

$conn->close();
?>
