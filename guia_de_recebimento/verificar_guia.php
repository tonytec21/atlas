<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if (isset($_GET['task_id'])) {
    $task_id = intval($_GET['task_id']);

    // Verificar se a guia de recebimento já foi gerada para essa tarefa
    $stmt = $conn->prepare("SELECT id FROM guia_de_recebimento WHERE task_id = ?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Guia encontrada, retornamos sucesso e o task_id (não o guia_id)
        echo json_encode(['guia_existe' => true, 'task_id' => $task_id]);
    } else {
        // Nenhuma guia encontrada
        echo json_encode(['guia_existe' => false]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['error' => 'task_id não fornecido']);
}
?>
