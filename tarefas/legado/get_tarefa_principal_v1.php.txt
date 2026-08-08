<?php
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$id_tarefa_sub = $_GET['id_tarefa_sub']; // Recebe o ID da subtarefa

// Verifica se a subtarefa tem uma tarefa principal
$sql = "SELECT id_tarefa_principal FROM tarefas WHERE id = ? AND sub_categoria = 'Sim'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_tarefa_sub);
$stmt->execute();
$result = $stmt->get_result();
$tarefa_principal_id = $result->fetch_assoc()['id_tarefa_principal'];

if ($tarefa_principal_id) {
    // Busca os dados da tarefa principal
    $sql_principal = "SELECT id, titulo, funcionario_responsavel, data_criacao, data_limite, status FROM tarefas WHERE id = ?";
    $stmt_principal = $conn->prepare($sql_principal);
    $stmt_principal->bind_param("i", $tarefa_principal_id);
    $stmt_principal->execute();
    $result_principal = $stmt_principal->get_result();
    $tarefa_principal = $result_principal->fetch_assoc();
    
    echo json_encode($tarefa_principal);
} else {
    echo json_encode(['error' => 'Tarefa principal não encontrada']);
}

$stmt->close();
$conn->close();
?>
