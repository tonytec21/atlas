<?php
/**
 * Atlas · Tarefas — verifica se a tarefa já possui guia de recebimento.
 *
 * Mantido por compatibilidade com telas antigas (a chave `guia_existe`
 * continua igual). Agora devolve também o total emitido e o número da
 * última guia, usados pelo fluxo de reimpressão.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/guia_helpers.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_GET['task_id'])) {
    echo json_encode(array('error' => 'task_id não fornecido'), JSON_UNESCAPED_UNICODE);
    exit;
}

$task_id = (int) $_GET['task_id'];
$guias   = guia_listar_por_tarefa($conn, $task_id);
$total   = count($guias);

$resposta = array(
    'guia_existe' => $total > 0,
    'task_id'     => $task_id,
    'total'       => $total,
);

if ($total > 0) {
    $resposta['ultima_guia_id'] = (int) $guias[0]['id'];
    $resposta['ultima_guia_em'] = guia_data_br(
        isset($guias[0]['criado_em']) && $guias[0]['criado_em'] !== null && $guias[0]['criado_em'] !== ''
            ? $guias[0]['criado_em']
            : $guias[0]['data_recebimento']
    );
}

$conn->close();

echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
