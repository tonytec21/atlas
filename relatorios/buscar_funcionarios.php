<?php
include(__DIR__ . '/db_connection.php');

// Query para buscar funcionários únicos
$query = "SELECT DISTINCT criado_por FROM ordens_de_servico WHERE criado_por IS NOT NULL AND criado_por != ''";
$result = $conn->query($query);

$funcionarios = [];
while ($row = $result->fetch_assoc()) {
    $funcionarios[] = $row['criado_por'];
}

// Retornar o array de funcionários como JSON
header('Content-Type: application/json');
echo json_encode($funcionarios, JSON_UNESCAPED_UNICODE);
?>
