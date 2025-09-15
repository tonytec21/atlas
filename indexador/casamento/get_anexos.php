<?php
include(__DIR__ . '/db_connection.php');
header('Content-Type: application/json; charset=utf-8');

$id_casamento = isset($_GET['id_casamento']) ? intval($_GET['id_casamento']) : 0;

$sql = "SELECT id, id_casamento, caminho_anexo, data
        FROM indexador_casamento_anexos
        WHERE id_casamento=? AND status='ativo'
        ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_casamento);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($r = $res->fetch_assoc()) { $out[] = $r; }
echo json_encode($out);

$stmt->close();
$conn->close();
