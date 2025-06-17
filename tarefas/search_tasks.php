<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

date_default_timezone_set('America/Sao_Paulo');

// Verificar se o usuário está logado
if (isset($_SESSION['username'])) {
    $usuarioLogado = $_SESSION['username'];
} else {
    die('Usuário não logado.');
}

// Buscar dados do usuário logado
$sqlUser = "SELECT nome_completo, nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = '$usuarioLogado' AND status = 'ativo'";
$resultUser = $conn->query($sqlUser);

if ($resultUser->num_rows > 0) {
    $userData = $resultUser->fetch_assoc();
    $nomeCompleto = $userData['nome_completo'];
    $nivelAcesso = $userData['nivel_de_acesso'];
    $acessoAdicional = $userData['acesso_adicional'];

    // Verificar se tem acesso total
    $acessos = array_map('trim', explode(',', $acessoAdicional));
    $temAcessoTotal = in_array('Controle de Tarefas', $acessos);
} else {
    die('Usuário não encontrado ou inativo.');
}

// Receber parâmetros de pesquisa
$protocol = isset($_GET['protocol']) ? trim($_GET['protocol']) : '';
$title = isset($_GET['title']) ? trim($_GET['title']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$employee = isset($_GET['employee']) ? trim($_GET['employee']) : '';
$revisor = isset($_GET['revisor']) ? trim($_GET['revisor']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$description = isset($_GET['description']) ? trim($_GET['description']) : '';
$priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';
$origin = isset($_GET['origin']) ? trim($_GET['origin']) : '';
$dateStart = isset($_GET['dateStart']) ? trim($_GET['dateStart']) : '';
$dateEnd = isset($_GET['dateEnd']) ? trim($_GET['dateEnd']) : '';

// Início da query
$sql = "SELECT tarefas.*, categorias.titulo AS categoria_titulo, origem.titulo AS origem_titulo 
        FROM tarefas 
        LEFT JOIN categorias ON tarefas.categoria = categorias.id 
        LEFT JOIN origem ON tarefas.origem = origem.id 
        WHERE 1=1";

// 🔥 Controle de acesso
if ($nivelAcesso === 'usuario' && !$temAcessoTotal) {
    $sql .= " AND (tarefas.status = 'Concluída' OR tarefas.funcionario_responsavel = '$nomeCompleto' OR tarefas.revisor = '$nomeCompleto')";
}

// 🔍 Aplicar filtros
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
} elseif (
    empty($protocol) && 
    empty($title) && 
    empty($category) && 
    empty($employee) && 
    empty($revisor) && 
    empty($description) && 
    empty($priority) && 
    empty($origin) && 
    empty($dateStart) && 
    empty($dateEnd)
) {
    // 🔥 Nenhum filtro aplicado — carregamento inicial
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

// 🔍 Filtro por intervalo de datas da data limite
if (!empty($dateStart) && !empty($dateEnd)) {
    $sql .= " AND DATE(tarefas.data_limite) BETWEEN '" . $conn->real_escape_string($dateStart) . "' AND '" . $conn->real_escape_string($dateEnd) . "'";
} elseif (!empty($dateStart)) {
    $sql .= " AND DATE(tarefas.data_limite) >= '" . $conn->real_escape_string($dateStart) . "'";
} elseif (!empty($dateEnd)) {
    $sql .= " AND DATE(tarefas.data_limite) <= '" . $conn->real_escape_string($dateEnd) . "'";
}

// 🔄 Ordenar por ID decrescente
$sql .= " ORDER BY tarefas.id DESC";

// Executar consulta
$result = $conn->query($sql);

$tasks = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Buscar comentários da tarefa
        $taskToken = $row['token'];
        $sql_comments = "SELECT * FROM comentarios WHERE hash_tarefa = '$taskToken'";
        $comments_result = $conn->query($sql_comments);
        $comments = [];
        if ($comments_result->num_rows > 0) {
            while ($comment_row = $comments_result->fetch_assoc()) {
                $comments[] = $comment_row;
            }
        }
        $row['comentarios'] = $comments;
        $tasks[] = $row;
    }
}

// Retornar em JSON
echo json_encode($tasks, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
