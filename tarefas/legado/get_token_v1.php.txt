<?php
include(__DIR__ . '/db_connection.php');

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "SELECT token FROM tarefas WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $tarefa = $result->fetch_assoc();
        echo json_encode(['token' => $tarefa['token']]);
    } else {
        echo json_encode(['error' => 'Token não encontrado.']);
    }
} else {
    echo json_encode(['error' => 'ID não fornecido.']);
}
?>
