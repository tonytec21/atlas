<?php
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

if (isset($_GET['hash_tarefa'])) {
    $hash_tarefa = $_GET['hash_tarefa'];

    // Consulta o banco de dados para buscar a tarefa e o id_tarefa_principal
    $sql = "SELECT id_tarefa_principal FROM tarefas WHERE hash_tarefa = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $hash_tarefa);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode($row);
    } else {
        echo json_encode(["id_tarefa_principal" => null]);
    }

    $stmt->close();
    $conn->close();
}
?>
