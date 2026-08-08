<?php
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

$id_tarefa_principal = $_GET['id_tarefa_principal'];

$sql = "SELECT id, titulo, funcionario_responsavel, data_criacao, data_limite, status FROM tarefas WHERE id_tarefa_principal = ? AND sub_categoria = 'Sim'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_tarefa_principal);
$stmt->execute();
$result = $stmt->get_result();

$subTasks = array();
while ($row = $result->fetch_assoc()) {
    $subTasks[] = $row;
}

echo json_encode($subTasks);

$stmt->close();
$conn->close();
?>
