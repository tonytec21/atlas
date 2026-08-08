<?php
/**
 * Atlas · Tarefas — compatibilidade: alteração de status.
 *
 * Mesmo contrato de antes (POST taskToken + status, resposta em texto). A
 * diferença é que o status agora é validado contra o catálogo do módulo e a
 * alteração fica registrada no histórico.
 */

require_once __DIR__ . '/core/bootstrap.php';
exigir_login();

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'Método inválido.';
    exit;
}

$token  = entrada('taskToken', '', $_POST);
$status = entrada('status', '', $_POST);

if ($token === '' || $status === '') {
    echo 'Informe a tarefa e o status.';
    exit;
}
if (!array_key_exists($status, tarefas_status_catalogo())) {
    echo 'Status inválido.';
    exit;
}

$t = db_one('SELECT id, status FROM tarefas WHERE token = ?', array($token));
if (!$t) {
    echo 'Tarefa não encontrada.';
    exit;
}

$conclusao = in_array($status, tarefas_status_conclui(), true) ? date('Y-m-d H:i:s') : null;

try {
    db_exec('UPDATE tarefas SET status = ?, data_conclusao = ? WHERE token = ?',
        array($status, $conclusao, $token));
    registrar_historico((int) $t['id'], 'status', 'Status alterado', $t['status'], $status);
    echo 'Status atualizado com sucesso!';
} catch (Exception $e) {
    error_log('[tarefas] update_status: ' . $e->getMessage());
    echo 'Erro ao atualizar o status.';
}
