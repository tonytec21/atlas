<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$protocol = $_GET['protocol'] ?? '';
$title = $_GET['title'] ?? '';
$category = $_GET['category'] ?? '';
$employee = $_GET['employee'] ?? '';
$status = $_GET['status'] ?? '';
$description = $_GET['description'] ?? '';
$origin = $_GET['origin'] ?? '';

$sql = "SELECT tarefas.*, categorias.titulo AS categoria_titulo, origem.titulo AS origem_titulo 
        FROM tarefas 
        LEFT JOIN categorias ON tarefas.categoria = categorias.id 
        LEFT JOIN origem ON tarefas.origem = origem.id 
        WHERE 1=1";

if (!empty($protocol)) {
    $sql .= " AND tarefas.id = '$protocol'";
}
if (!empty($title)) {
    $sql .= " AND tarefas.titulo LIKE '%$title%'";
}
if (!empty($category)) {
    $sql .= " AND tarefas.categoria = '$category'";
}
if (!empty($employee)) {
    $sql .= " AND tarefas.funcionario_responsavel LIKE '%$employee%'";
}
if (!empty($status)) {
    $sql .= " AND tarefas.status LIKE '%$status%'";
}
if (!empty($description)) {
    $sql .= " AND tarefas.descricao LIKE '%$description%'";
}
if (!empty($origin)) {
    $sql .= " AND tarefas.origem = '$origin'";
}

// Add the ORDER BY clause to sort by ID in descending order
$sql .= " ORDER BY tarefas.id DESC";

$result = $conn->query($sql);

$tasks = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Fetch comments for each task
        $taskToken = $row['token'];
        $sql_comments = "SELECT * FROM comentarios WHERE hash_tarefa = '$taskToken'";
        $comments_result = $conn->query($sql_comments);
        $comments = [];
        if ($comments_result->num_rows > 0) {
            while($comment_row = $comments_result->fetch_assoc()) {
                $comments[] = $comment_row;
            }
        }
        $row['comentarios'] = $comments;
        $tasks[] = $row;
    }
}

echo json_encode($tasks);
$conn->close();
?>
