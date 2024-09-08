<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Verifique se o nome de usuário está presente na sessão (usando a chave 'username')
if (isset($_SESSION['username'])) {
    $usuarioLogado = $_SESSION['username'];
} else {
    die('Usuário não logado.');
}

// Verifique o nome completo e o nível de acesso do usuário logado
$sqlUser = "SELECT nome_completo, nivel_de_acesso FROM funcionarios WHERE usuario = '$usuarioLogado' AND status = 'ativo'";
$resultUser = $conn->query($sqlUser);

if ($resultUser->num_rows > 0) {
    $userData = $resultUser->fetch_assoc();
    $nomeCompleto = $userData['nome_completo'];
    $nivelAcesso = $userData['nivel_de_acesso'];
} else {
    // Se o usuário não for encontrado, redirecione ou mostre uma mensagem de erro
    die('Usuário não encontrado ou inativo.');
}

// Parâmetros de pesquisa
$protocol = $_GET['protocol'] ?? '';
$title = $_GET['title'] ?? '';
$category = $_GET['category'] ?? '';
$employee = $_GET['employee'] ?? '';
$status = $_GET['status'] ?? '';
$description = $_GET['description'] ?? '';
$origin = $_GET['origin'] ?? '';

// Montagem da consulta SQL
$sql = "SELECT tarefas.*, categorias.titulo AS categoria_titulo, origem.titulo AS origem_titulo 
        FROM tarefas 
        LEFT JOIN categorias ON tarefas.categoria = categorias.id 
        LEFT JOIN origem ON tarefas.origem = origem.id 
        WHERE 1=1";

// Se o nível de acesso for 'usuario', filtre apenas as tarefas atribuídas ao usuário logado
if ($nivelAcesso === 'usuario') {
    $sql .= " AND tarefas.funcionario_responsavel = '$nomeCompleto'";
}

// Filtros de pesquisa
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
} else {
    // Se nenhum status foi selecionado, exclua as tarefas com status "Concluída" e "Cancelada"
    $sql .= " AND tarefas.status NOT IN ('Concluída', 'Cancelada')";
}
if (!empty($description)) {
    $sql .= " AND tarefas.descricao LIKE '%$description%'";
}
if (!empty($origin)) {
    $sql .= " AND tarefas.origem = '$origin'";
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
?>
