<?php
include(__DIR__ . '/../session_check.php');
checkSession();

// Conexão com o banco de dados "atlas"
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "atlas";

$conn = new mysqli($servername, $username, $password, $dbname); /* atlas-auditoria */ if (is_file(__DIR__ . '/../auditoria/hook.php')) { include_once __DIR__ . '/../auditoria/hook.php'; atlas_aud_conexao($conn); }

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$id = isset($_GET['id']) ? $conn->real_escape_string($_GET['id']) : '';

if (empty($id)) {
    echo json_encode(['status' => 'error', 'message' => 'ID da tarefa não fornecido.']);
    $conn->close();
    exit;
}

// Buscar o token da tarefa com o ID fornecido
$stmt = $conn->prepare("SELECT token FROM tarefas WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(['status' => 'success', 'token' => $row['token']]);
} else {
    echo json_encode(['status' => 'not_found', 'message' => 'Tarefa não encontrada.']);
}

$stmt->close();
$conn->close();
?>
