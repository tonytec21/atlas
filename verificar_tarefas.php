<?php
session_start();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

function checkSession() {
    if (!isset($_SESSION['username'])) {
        header('Location: login.php');
        exit;
    }
}

checkSession();

// Obtendo o nome completo, nível de acesso e acessos adicionais do usuário logado
$username = $_SESSION['username'];

$sql = "SELECT nome_completo, nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$funcionario = $result->fetch_assoc();
$nome_completo = $funcionario['nome_completo'];
$nivel_de_acesso = $funcionario['nivel_de_acesso'];

// Verificar se o usuário tem acesso adicional a "Controle de Tarefas"
$acesso_adicional = $funcionario['acesso_adicional'];
$acessos = array_map('trim', explode(',', $acesso_adicional));
$tem_acesso_controle_tarefas = in_array('Controle de Tarefas', $acessos);

$response = [
    'tarefas' => [],
    'novas_tarefas' => []
];

$now = new DateTime();

// Se o usuário for administrador ou tiver acesso adicional "Controle de Tarefas", buscar as tarefas de todos os funcionários
if ($nivel_de_acesso === 'administrador' || $tem_acesso_controle_tarefas) {
    $sql_tarefas = "
        SELECT id, titulo, descricao, data_limite, data_criacao, nivel_de_prioridade, status, funcionario_responsavel, token
        FROM tarefas 
        WHERE status NOT IN ('Concluída', 'Cancelada', 'Finalizado sem prática do ato', 'Aguardando Retirada')
        ORDER BY funcionario_responsavel
    ";
    $stmt_tarefas = $conn->prepare($sql_tarefas);
} else {
    // Caso contrário, buscar as tarefas atribuídas ao usuário logado como responsável ou revisor
    $sql_tarefas = "
        SELECT id, titulo, descricao, data_limite, data_criacao, nivel_de_prioridade, status, funcionario_responsavel, revisor, token
        FROM tarefas 
        WHERE (funcionario_responsavel = ? OR revisor = ?) 
        AND status NOT IN ('Concluída', 'Cancelada', 'Finalizado sem prática do ato', 'Aguardando Retirada')
    ";
    $stmt_tarefas = $conn->prepare($sql_tarefas);
    $stmt_tarefas->bind_param("ss", $nome_completo, $nome_completo);
}

$stmt_tarefas->execute();
$result_tarefas = $stmt_tarefas->get_result();
$tarefas = $result_tarefas->fetch_all(MYSQLI_ASSOC);

if (!empty($tarefas)) {
    foreach ($tarefas as $tarefa) {
        $data_limite = new DateTime($tarefa['data_limite']);
        $data_criacao = new DateTime($tarefa['data_criacao']);
        
        // Verifica se a tarefa foi criada nas últimas 32 horas
        $interval = $data_criacao->diff($now);
        $horas_desde_criacao = $interval->h + ($interval->days * 24);
        
        // Tarefas com menos de 32 horas são consideradas "novas"
        if ($horas_desde_criacao < 32) {
            $response['novas_tarefas'][$tarefa['funcionario_responsavel']][] = [
                'id' => $tarefa['id'],
                'titulo' => $tarefa['titulo'],
                'descricao' => $tarefa['descricao'],
                'data_criacao' => $data_criacao->format('Y-m-d H:i:s'),
                'data_limite' => $data_limite->format('Y-m-d H:i:s'),
                'status' => $tarefa['status'], 
                'nivel_de_prioridade' => $tarefa['nivel_de_prioridade'],
                'token' => $tarefa['token']
            ];
        }

        $status_data = '';
        if ($data_limite < $now) {
            $status_data = 'Vencida';
        } elseif ($data_limite->diff($now)->days <= 3) {
            $status_data = 'Prestes a vencer';
        }

        $response['tarefas'][$tarefa['funcionario_responsavel']][] = [
            'id' => $tarefa['id'],
            'titulo' => $tarefa['titulo'],
            'descricao' => $tarefa['descricao'],
            'data_criacao' => $data_criacao->format('Y-m-d H:i:s'),
            'data_limite' => $data_limite->format('Y-m-d H:i:s'),
            'nivel_de_prioridade' => $tarefa['nivel_de_prioridade'],
            'status' => $tarefa['status'], 
            'status_data' => $status_data,
            'token' => $tarefa['token']
        ];        
    }
}

// Retornar dados em formato JSON, incluindo novas tarefas
header('Content-Type: application/json');
echo json_encode($response);
?>
