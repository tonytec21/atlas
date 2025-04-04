<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Verifique se o nome de usuário está presente na sessão
if (isset($_SESSION['username'])) {
    $usuarioLogado = $_SESSION['username'];
} else {
    die('Usuário não logado.');
}

// Parâmetros de pesquisa
$protocol = isset($_GET['protocol']) ? trim($_GET['protocol']) : '';
$title = isset($_GET['title']) ? trim($_GET['title']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$employee = isset($_GET['employee']) ? trim($_GET['employee']) : '';
$revisor = isset($_GET['revisor']) ? trim($_GET['revisor']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$description = isset($_GET['description']) ? trim($_GET['description']) : '';
$priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';
$origin = isset($_GET['origin']) ? trim($_GET['origin']) : '';

// Montagem da consulta SQL
$sql = "SELECT tarefas.*, categorias.titulo AS categoria_titulo, origem.titulo AS origem_titulo 
        FROM tarefas 
        LEFT JOIN categorias ON tarefas.categoria = categorias.id 
        LEFT JOIN origem ON tarefas.origem = origem.id 
        WHERE 1=1";

// Aplicar filtros de pesquisa
if (!empty($protocol)) {
    $sql .= " AND tarefas.id = '" . $conn->real_escape_string($protocol) . "'";
}
if (!empty($title)) {
    $sql .= " AND tarefas.titulo LIKE '%" . $conn->real_escape_string($title) . "%'";
}
if (!empty($category)) {
    $sql .= " AND tarefas.categoria = '" . $conn->real_escape_string($category) . "'";
}
if (!empty($employee)) {
    $sql .= " AND tarefas.funcionario_responsavel LIKE '%" . $conn->real_escape_string($employee) . "%'";
}
if (!empty($revisor)) {
    $sql .= " AND tarefas.revisor LIKE '%" . $conn->real_escape_string($revisor) . "%'";
}
if (!empty($status)) {
    $sql .= " AND tarefas.status = '" . $conn->real_escape_string($status) . "'";
} else {
    // Se nenhum status foi selecionado, exclua as tarefas com status "Concluída" e "Cancelada"
    $sql .= " AND tarefas.status NOT IN ('Concluída', 'Cancelada', 'Finalizado sem prática do ato', 'Aguardando Retirada')";
}
if (!empty($description)) {
    $sql .= " AND tarefas.descricao LIKE '%" . $conn->real_escape_string($description) . "%'";
}
if (!empty($priority)) {
    $sql .= " AND tarefas.nivel_de_prioridade = '" . $conn->real_escape_string($priority) . "'";
}
if (!empty($origin)) {
    $sql .= " AND tarefas.origem = '" . $conn->real_escape_string($origin) . "'";
}

// Ordenação por ID em ordem decrescente
$sql .= " ORDER BY tarefas.id DESC";

$result = $conn->query($sql);

$tasks = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Buscar comentários para cada tarefa
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

// Retorna os dados em formato JSON
echo json_encode($tasks, JSON_UNESCAPED_UNICODE);
$conn->close();
