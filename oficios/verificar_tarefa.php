<?php
include(__DIR__ . '/../session_check.php');
checkSession();

// Conexão com o banco de dados "atlas"
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "atlas";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Obter o número do ofício a partir do parâmetro GET
$numero_oficio = isset($_GET['numero_oficio']) ? $conn->real_escape_string($_GET['numero_oficio']) : '';

if (empty($numero_oficio)) {
    echo json_encode(['status' => 'error', 'message' => 'Número do ofício não fornecido.']);
    $conn->close();
    exit;
}

// Consultar a tabela "tarefas" para verificar se o número do ofício existe
$stmt = $conn->prepare("SELECT id FROM tarefas WHERE numero_oficio = ?");
$stmt->bind_param("s", $numero_oficio);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Pegar o ID da primeira ocorrência encontrada
    $row = $result->fetch_assoc();
    echo json_encode(['status' => 'success', 'id' => $row['id']]);
} else {
    echo json_encode(['status' => 'not_found', 'message' => 'Número do ofício não encontrado na tabela tarefas.']);
}

$stmt->close();
$conn->close();
?>
