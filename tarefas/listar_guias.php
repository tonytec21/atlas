<?php
/**
 * Atlas · Tarefas — histórico de guias de recebimento de uma tarefa.
 *
 * Devolve sempre JSON limpo (nunca HTML de erro), porque o front-end faz
 * JSON.parse direto na resposta.
 *
 * GET  task_id  Protocolo geral da tarefa.
 *
 * Resposta:
 * {
 *   "success": true,
 *   "task_id": 123,
 *   "total": 2,
 *   "guias": [ { id, cliente, funcionario, data_recebimento_br, criado_em_br,
 *                emitido_por, impressoes, ultima_impressao_br,
 *                documentos_recebidos, observacoes }, ... ]   // mais nova primeiro
 * }
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/guia_helpers.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** Resposta única e limpa. */
function guia_responder($dados, $codigo = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Fatal também vira JSON. */
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
            'error'   => 'Erro interno ao consultar o histórico de guias.',
        ), JSON_UNESCAPED_UNICODE);
    }
});

ob_start();

$task_id = isset($_GET['task_id']) ? (int) $_GET['task_id'] : 0;
if ($task_id <= 0) {
    guia_responder(array('success' => false, 'error' => 'Protocolo não informado.'), 400);
}

$linhas = guia_listar_por_tarefa($conn, $task_id);

$guias = array();
foreach ($linhas as $g) {
    $criadoEm = isset($g['criado_em']) && $g['criado_em'] !== null && $g['criado_em'] !== ''
        ? $g['criado_em']
        : (isset($g['data_recebimento']) ? $g['data_recebimento'] : '');

    $guias[] = array(
        'id'                   => (int) $g['id'],
        'cliente'              => isset($g['cliente']) ? (string) $g['cliente'] : '',
        'funcionario'          => isset($g['funcionario']) ? (string) $g['funcionario'] : '',
        'data_recebimento'     => isset($g['data_recebimento']) ? (string) $g['data_recebimento'] : '',
        'data_recebimento_br'  => guia_data_br(isset($g['data_recebimento']) ? $g['data_recebimento'] : ''),
        'criado_em_br'         => guia_data_br($criadoEm),
        'emitido_por'          => isset($g['emitido_por']) && trim((string) $g['emitido_por']) !== ''
                                    ? (string) $g['emitido_por']
                                    : '—',
        'impressoes'           => isset($g['impressoes']) ? (int) $g['impressoes'] : 0,
        'ultima_impressao_br'  => guia_data_br(isset($g['ultima_impressao']) ? $g['ultima_impressao'] : ''),
        'documentos_recebidos' => isset($g['documentos_recebidos']) ? (string) $g['documentos_recebidos'] : '',
        'observacoes'          => isset($g['observacoes']) ? (string) $g['observacoes'] : '',
    );
}

$conn->close();

guia_responder(array(
    'success' => true,
    'task_id' => $task_id,
    'total'   => count($guias),
    'guias'   => $guias,
));
