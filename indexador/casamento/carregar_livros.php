<?php
include(__DIR__ . '/db_connection.php');
header('Content-Type: application/json; charset=utf-8');

$rows = [];
$rs = $conn->query("SELECT DISTINCT livro FROM indexador_casamento WHERE status='ativo' ORDER BY CAST(livro AS UNSIGNED) ASC");
if ($rs) {
  while ($r = $rs->fetch_assoc()) { $rows[] = $r['livro']; }
}
echo json_encode($rows);
$conn->close();
