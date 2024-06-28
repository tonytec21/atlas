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

        // Fetch comments for the task
        $sql_comments = "SELECT * FROM comentarios WHERE hash_tarefa = ?";
        $stmt_comments = $conn->prepare($sql_comments);
        $stmt_comments->bind_param("s", $token);
        $stmt_comments->execute();
        $comments_result = $stmt_comments->get_result();
        $comments = [];
        while ($comment_row = $comments_result->fetch_assoc()) {
            $comments[] = $comment_row;
        }
        $task['comentarios'] = $comments;

        echo json_encode($task);
    } else {
        echo json_encode([]);
    }

    $stmt->close();
    $conn->close();
}
?>
