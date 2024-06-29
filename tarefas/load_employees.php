<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json; charset=UTF-8');

$conn = new mysqli("localhost", "root", "", "atlas");
if ($conn->connect_error) {
    die(json_encode(['error' => "Falha na conexão com o banco atlas: " . $conn->connect_error]));
}

$conn->set_charset("utf8"); // Adicionando esta linha para definir o charset para UTF-8

$sql = "SELECT nome_completo FROM funcionarios WHERE status = 'ativo'";
$result = $conn->query($sql);

$employees = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = ['nome_completo' => $row['nome_completo']];
    }
}

$conn->close();

echo json_encode($employees, JSON_UNESCAPED_UNICODE);
?>
