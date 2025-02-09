<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $taskToken = $_POST['taskToken'];
    $status = $_POST['status'];
    $data_conclusao = null;

    // Verificar se o status é "Concluída", "Finalizado sem prática do ato" ou "Aguardando Retirada"
    if ($status === 'Concluída' || $status === 'Finalizado sem prática do ato' || $status === 'Aguardando Retirada') {
        $data_conclusao = date('Y-m-d H:i:s');
    }

    // Atualizar o status da tarefa no banco de dados
    $sql = "UPDATE tarefas SET status = ?, data_conclusao = ? WHERE token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $status, $data_conclusao, $taskToken);

    if ($stmt->execute()) {
        echo "Status atualizado com sucesso!";
    } else {
        echo "Erro ao atualizar o status: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
