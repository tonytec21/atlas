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

$sql = "SELECT id AS funcionario_id, nome_completo, nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$funcionario = $result->fetch_assoc();
$funcionario_id   = isset($funcionario['funcionario_id']) ? (int)$funcionario['funcionario_id'] : 0;
$nome_completo    = $funcionario['nome_completo'];
$nivel_de_acesso  = $funcionario['nivel_de_acesso'];

// Verificar se o usuário tem acesso adicional a "Controle de Tarefas"
$acesso_adicional = $funcionario['acesso_adicional'];
$acessos = array_map('trim', explode(',', $acesso_adicional));
$tem_acesso_controle_tarefas = in_array('Controle de Tarefas', $acessos);

$response = [
    'tarefas'          => [],
    'novas_tarefas'    => [],
    'tarefas_certidao' => [] // Bloco para pedidos de certidão
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
        $data_limite  = new DateTime($tarefa['data_limite']);
        $data_criacao = new DateTime($tarefa['data_criacao']);
        
        // Verifica se a tarefa foi criada nas últimas 32 horas
        $interval = $data_criacao->diff($now);
        $horas_desde_criacao = $interval->h + ($interval->days * 24);
        
        // Tarefas com menos de 32 horas são consideradas "novas"
        if ($horas_desde_criacao < 32) {
            $response['novas_tarefas'][$tarefa['funcionario_responsavel']][] = [
                'id'                   => $tarefa['id'],
                'titulo'               => $tarefa['titulo'],
                'descricao'            => $tarefa['descricao'],
                'data_criacao'         => $data_criacao->format('Y-m-d H:i:s'),
                'data_limite'          => $data_limite->format('Y-m-d H:i:s'),
                'status'               => $tarefa['status'], 
                'nivel_de_prioridade'  => $tarefa['nivel_de_prioridade'],
                'token'                => $tarefa['token']
            ];
        }

        $status_data = '';
        if ($data_limite < $now) {
            $status_data = 'Vencida';
        } elseif ($data_limite->diff($now)->days <= 3) {
            $status_data = 'Prestes a vencer';
        }

        $response['tarefas'][$tarefa['funcionario_responsavel']][] = [
            'id'                   => $tarefa['id'],
            'titulo'               => $tarefa['titulo'],
            'descricao'            => $tarefa['descricao'],
            'data_criacao'         => $data_criacao->format('Y-m-d H:i:s'),
            'data_limite'          => $data_limite->format('Y-m-d H:i:s'),
            'nivel_de_prioridade'  => $tarefa['nivel_de_prioridade'],
            'status'               => $tarefa['status'], 
            'status_data'          => $status_data,
            'token'                => $tarefa['token']
        ];        
    }
} // <— FECHA AQUI o bloco das tarefas “normais”

// ===================== TAREFAS ABERTAS — PEDIDOS DE CERTIDÃO =====================
// Admin/controle vê todas as tarefas abertas (pendente e em_andamento).
// Usuário comum vê apenas as suas abertas (pendente e em_andamento).
if ($nivel_de_acesso === 'administrador' || $tem_acesso_controle_tarefas) {
    $sql_cert = "
        SELECT t.id, t.pedido_id, t.status, t.criado_em, t.atualizado_em,
               e.nome AS equipe_nome, p.protocolo
        FROM tarefas_pedido t
        LEFT JOIN equipes e ON e.id = t.equipe_id
        LEFT JOIN pedidos_certidao p ON p.id = t.pedido_id
        WHERE t.status IN ('pendente','em_andamento')
        ORDER BY t.criado_em DESC
    ";
    $stmt_cert = $conn->prepare($sql_cert);
} else {
    $sql_cert = "
        SELECT t.id, t.pedido_id, t.status, t.criado_em, t.atualizado_em,
               e.nome AS equipe_nome, p.protocolo
        FROM tarefas_pedido t
        LEFT JOIN equipes e ON e.id = t.equipe_id
        LEFT JOIN pedidos_certidao p ON p.id = t.pedido_id
        WHERE t.status IN ('pendente','em_andamento')
          AND t.funcionario_id = ?
        ORDER BY t.criado_em DESC
    ";
    $stmt_cert = $conn->prepare($sql_cert);
    $stmt_cert->bind_param("i", $funcionario_id);
}

$stmt_cert->execute();
$res_cert = $stmt_cert->get_result();
$tarefas_cert = $res_cert->fetch_all(MYSQLI_ASSOC);

if (!empty($tarefas_cert)) {
    foreach ($tarefas_cert as $tc) {
        $pedidoId = (int)$tc['pedido_id'];
        $response['tarefas_certidao'][] = [
            'id'            => (int)$tc['id'],
            'pedido_id'     => $pedidoId,
            'protocolo'     => $tc['protocolo'],
            'equipe_nome'   => $tc['equipe_nome'],
            'status'        => $tc['status'],
            'criado_em'     => $tc['criado_em'],
            'atualizado_em' => $tc['atualizado_em'],
            // Agora abrimos diretamente a visualização do pedido de certidão
            'link'          => 'pedidos_certidao/visualizar_pedido.php?id=' . $pedidoId
        ];
    }
}

// Retornar dados em formato JSON, incluindo novas tarefas
header('Content-Type: application/json');
echo json_encode($response);
?>
