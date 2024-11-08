<?php
session_start();
include('db_connection.php');

$username = $_SESSION['username'];

// Consulta para verificar o nível de acesso e acesso adicional do usuário
$sql = "SELECT nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$nivel_de_acesso = $user['nivel_de_acesso'];
$acesso_adicional = $user['acesso_adicional'] ?? '';
$acessos = array_map('trim', explode(',', $acesso_adicional));
$tem_acesso_controle_tarefas = in_array('Controle de Tarefas', $acessos);

// Verificar se é administrador ou tem acesso adicional
$tem_acesso = ($nivel_de_acesso === 'administrador' || $tem_acesso_controle_tarefas);

// Retorna um JSON com o status de acesso
echo json_encode(['tem_acesso' => $tem_acesso]);

$stmt->close();
$conn->close();
?>
