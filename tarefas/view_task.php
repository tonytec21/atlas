<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $token = $_GET['token'];

    // Consulta para buscar dados da tarefa
    $sql = "SELECT t.*, c.titulo AS categoria_titulo, o.titulo AS origem_titulo 
            FROM tarefas t
            LEFT JOIN categorias c ON t.categoria = c.id
            LEFT JOIN origem o ON t.origem = o.id
            WHERE t.token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $task = $result->fetch_assoc();
        $taskId = $task['id'];  // ID da tarefa principal

        // Buscar comentários da tarefa principal e das subtarefas
        $sql_comments = "SELECT * FROM comentarios 
                         WHERE hash_tarefa = ? OR id_tarefa_principal = ?";
        $stmt_comments = $conn->prepare($sql_comments);
        $stmt_comments->bind_param("si", $token, $taskId);
        $stmt_comments->execute();
        $comments_result = $stmt_comments->get_result();
        $comments = [];
        while ($comment_row = $comments_result->fetch_assoc()) {
            // Verificar se o comentário é de uma subtarefa
            if ($comment_row['id_tarefa_principal'] == $taskId) {
                $comment_row['is_subtask'] = true;  // Indicar que é um comentário de subtarefa
            } else {
                $comment_row['is_subtask'] = false;  // Indicar que é um comentário da tarefa principal
            }
            $comments[] = $comment_row;
        }

        $task['comentarios'] = $comments;

        // Verificar se o recibo de entrega já foi gerado
        $reciboStmt = $conn->prepare("SELECT id FROM recibos_de_entrega WHERE task_id = ?");
        $reciboStmt->bind_param("i", $taskId);
        $reciboStmt->execute();
        $reciboResult = $reciboStmt->get_result();
        $task['recibo_gerado'] = $reciboResult->num_rows > 0;
        $reciboStmt->close();

        // Verificar se a guia de recebimento já foi gerada
        $guiaStmt = $conn->prepare("SELECT id FROM guia_de_recebimento WHERE task_id = ?");
        $guiaStmt->bind_param("i", $taskId);
        $guiaStmt->execute();
        $guiaResult = $guiaStmt->get_result();
        $task['guia_gerada'] = $guiaResult->num_rows > 0;
        $guiaStmt->close();

        echo json_encode($task);
    } else {
        echo json_encode([]);
    }

    $stmt->close();
    $conn->close();
}
?>
