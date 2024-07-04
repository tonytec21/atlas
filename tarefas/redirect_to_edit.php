<?php
if (isset($_GET['task_id'])) {
    $task_id = intval($_GET['task_id']);
    header("Location: edit_task.php?id=$task_id");
    exit();
} else {
    // Redireciona de volta para a página de criação caso não haja ID
    header("Location: criar-tarefa.php");
    exit();
}
?>
