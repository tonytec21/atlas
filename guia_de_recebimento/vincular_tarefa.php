<?php
include('db_connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guia_id = $_POST['guia_id'];
    $task_id = $_POST['task_id'];

    // Verifica se os valores foram passados corretamente
    if (!empty($guia_id) && !empty($task_id)) {
        // Conexão com o banco de dados
        $conn = new mysqli($servername, $username, $password, $dbname);
        $conn->set_charset("utf8"); // Define a codificação para utf8

        if ($conn->connect_error) {
            die("Falha na conexão: " . $conn->connect_error);
        }

        // Verifica se a tarefa com o ID fornecido existe
        $checkTask = $conn->prepare("SELECT COUNT(*) AS total FROM tarefas WHERE id = ?");
        $checkTask->bind_param("i", $task_id);
        $checkTask->execute();
        $checkTask->bind_result($taskExists);
        $checkTask->fetch();
        $checkTask->close();

        if ($taskExists > 0) {
            // Atualiza o task_id na tabela guia_de_recebimento
            $stmt = $conn->prepare("UPDATE guia_de_recebimento SET task_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $task_id, $guia_id);

            if ($stmt->execute()) {
                echo "Tarefa vinculada com sucesso!";
            } else {
                echo "Erro ao vincular a tarefa.";
            }

            $stmt->close();
        } else {
            // Retorna um erro se a tarefa não foi encontrada
            echo "Erro: Tarefa não localizada.";
        }

        $conn->close();
    } else {
        echo "Dados incompletos.";
    }
}
?>
