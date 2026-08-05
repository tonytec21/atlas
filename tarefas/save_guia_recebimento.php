<?php
/**
 * Atlas · Tarefas — emissão de uma nova Guia de Recebimento.
 *
 * Cada chamada gera SEMPRE uma nova guia (o histórico da tarefa é mantido).
 * O funcionário que consta na guia vem do formulário: por padrão é o
 * responsável pela tarefa, mas pode ser outro, já que nem sempre quem emite
 * a guia é o responsável.
 *
 * POST task_id, cliente, dataRecebimento, documentosRecebidos, funcionario
 *      observacoes (opcional)
 *
 * Resposta: { "success": true, "guia_id": 45 }
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/guia_helpers.php');
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** Resposta única e limpa — o front faz JSON.parse direto. */
function guia_responder($dados, $codigo = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode(array(
            'success' => false,
            'error'   => 'Erro interno ao salvar a guia de recebimento.',
        ), JSON_UNESCAPED_UNICODE);
    }
});

ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    guia_responder(array('success' => false, 'error' => 'Método não permitido.'), 405);
}

/* ---------- Entrada ---------- */
$task_id             = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
$cliente             = isset($_POST['cliente']) ? trim((string) $_POST['cliente']) : '';
$dataRecebimento     = isset($_POST['dataRecebimento']) ? trim((string) $_POST['dataRecebimento']) : '';
$documentosRecebidos = isset($_POST['documentosRecebidos']) ? trim((string) $_POST['documentosRecebidos']) : '';
$funcionario         = isset($_POST['funcionario']) ? trim((string) $_POST['funcionario']) : '';
$observacoes         = isset($_POST['observacoes']) ? trim((string) $_POST['observacoes']) : '';

if ($task_id <= 0 || $cliente === '' || $dataRecebimento === '' || $documentosRecebidos === '' || $funcionario === '') {
    guia_responder(array('success' => false, 'error' => 'Dados incompletos.'), 400);
}

/* O input datetime-local manda "2025-08-05T14:30"; o MySQL quer espaço. */
$dataRecebimento = str_replace('T', ' ', $dataRecebimento);
if (strlen($dataRecebimento) === 16) {
    $dataRecebimento .= ':00';
}

/* ---------- Validações ---------- */
try {
    $stmt = $conn->prepare('SELECT id FROM tarefas WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $task_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $existe = $res && $res->num_rows > 0;
        $stmt->close();
        if (!$existe) {
            guia_responder(array('success' => false, 'error' => 'Tarefa não encontrada.'), 404);
        }
    }
} catch (Exception $e) {
    error_log('[tarefas] save_guia_recebimento (tarefa): ' . $e->getMessage());
}

if (!guia_funcionario_valido($conn, $funcionario, $task_id)) {
    guia_responder(array(
        'success' => false,
        'error'   => 'Funcionário informado não consta no cadastro.',
    ), 422);
}

/* ---------- Gravação ----------
   As colunas de controle só entram no INSERT se existirem no banco, para
   que o módulo continue funcionando antes de rodar a migração. */
$colunas = array('task_id', 'cliente', 'funcionario', 'data_recebimento', 'documentos_recebidos', 'observacoes');
$tipos   = 'isssss';
$valores = array($task_id, $cliente, $funcionario, $dataRecebimento, $documentosRecebidos, $observacoes);

if (guia_tem_coluna($conn, 'emitido_por')) {
    $colunas[] = 'emitido_por';
    $tipos    .= 's';
    $valores[] = guia_usuario_logado($conn);
}
if (guia_tem_coluna($conn, 'criado_em')) {
    $colunas[] = 'criado_em';
    $tipos    .= 's';
    $valores[] = date('Y-m-d H:i:s');
}
if (guia_tem_coluna($conn, 'impressoes')) {
    $colunas[] = 'impressoes';
    $tipos    .= 'i';
    $valores[] = 0; // a 1ª impressão é contabilizada quando o PDF é aberto
}

$sql = 'INSERT INTO `' . GUIA_TABELA . '` (`' . implode('`, `', $colunas) . '`) VALUES ('
     . implode(', ', array_fill(0, count($colunas), '?')) . ')';

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        guia_responder(array('success' => false, 'error' => $conn->error), 500);
    }

    $stmt->bind_param($tipos, ...$valores);

    if (!$stmt->execute()) {
        $erro = $stmt->error;
        $stmt->close();
        guia_responder(array('success' => false, 'error' => $erro), 500);
    }

    $guia_id = (int) $stmt->insert_id;
    $stmt->close();
    $conn->close();

    guia_responder(array(
        'success' => true,
        'guia_id' => $guia_id,
        'task_id' => $task_id,
    ));
} catch (Exception $e) {
    error_log('[tarefas] save_guia_recebimento: ' . $e->getMessage());

    /* Se ainda existir um índice UNIQUE em task_id (modelo antigo, uma guia
       por tarefa), a segunda emissão falha aqui. A mensagem orienta o
       caminho em vez de mostrar o erro cru do MySQL. */
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        guia_responder(array(
            'success' => false,
            'error'   => 'O banco ainda impede mais de uma guia por tarefa. '
                       . 'Execute migracao_guia_recebimento.php para habilitar o histórico.',
        ), 409);
    }

    guia_responder(array('success' => false, 'error' => 'Erro ao salvar a guia de recebimento.'), 500);
}
