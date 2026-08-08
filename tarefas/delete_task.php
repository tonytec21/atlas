<?php
/**
 * Atlas · Tarefas — compatibilidade: exclusão de tarefa.
 *
 * Acrescenta a checagem de permissão que faltava: antes, qualquer usuário
 * logado conseguia excluir qualquer tarefa chamando este endereço direto.
 */

require_once __DIR__ . '/core/bootstrap.php';
exigir_login();

header('Content-Type: text/plain; charset=utf-8');

if (!usuario_ve_tudo()) {
    echo 'Somente administradores podem excluir tarefas.';
    exit;
}

$id = entrada_int('id', 0, $_POST);
if ($id <= 0) {
    echo 'Tarefa não informada.';
    exit;
}

$t = db_one('SELECT id, titulo FROM tarefas WHERE id = ?', array($id));
if (!$t) {
    echo 'Tarefa não encontrada.';
    exit;
}

try {
    db_exec('DELETE FROM tarefas WHERE id = ?', array($id));
    registrar_historico($id, 'exclusao', 'Tarefa excluída: ' . $t['titulo']);
    echo 'Tarefa excluída com sucesso!';
} catch (Exception $e) {
    error_log('[tarefas] delete_task: ' . $e->getMessage());
    echo 'Erro ao excluir a tarefa.';
}
