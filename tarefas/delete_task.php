<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$id = $_POST['id'] ?? '';

$sql = "DELETE FROM tarefas WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo "Tarefa excluída com sucesso!";
} else {
    echo "Erro ao excluir a tarefa: " . $conn->error;
}

$stmt->close();
$conn->close();
?>
