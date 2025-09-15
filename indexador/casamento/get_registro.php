<?php
include(__DIR__ . '/db_connection.php');
header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$sql = "SELECT id, termo, livro, folha, tipo_casamento, data_registro,
               conjuge1_nome, conjuge1_nome_casado, conjuge1_sexo, conjuge2_nome, conjuge2_nome_casado, conjuge2_sexo,
               regime_bens, data_casamento, matricula
        FROM indexador_casamento WHERE id=? AND status='ativo' LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    echo json_encode($row);
} else {
    echo json_encode([]);
}
$stmt->close();
$conn->close();
