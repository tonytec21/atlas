<?php
/**
 * Atlas · Tarefas — compatibilidade: view_task.php.
 *
 * Mantém o contrato antigo (objeto plano da tarefa, com `comentarios`,
 * `recibo_gerado` e `guia_gerada`), para não quebrar nada que ainda consuma
 * este endereço. A tela nova usa api/tarefa.php, que devolve também anexos
 * normalizados, checklist, subtarefas e histórico.
 */

require_once __DIR__ . '/core/bootstrap.php';
api_iniciar();

$token = entrada('token');
if ($token === '') {
    responder_json(array('error' => 'Token da tarefa não informado.'), 400);
}

try {
    $task = db_one(
        'SELECT t.*, c.titulo AS categoria_titulo, o.titulo AS origem_titulo
           FROM tarefas t
           LEFT JOIN categorias c ON t.categoria = c.id
           LEFT JOIN origem o ON t.origem = o.id
          WHERE t.token = ? LIMIT 1',
        array($token)
    );
} catch (Exception $e) {
    error_log('[tarefas] view_task: ' . $e->getMessage());
    responder_json(array('error' => 'Não foi possível consultar a tarefa.'), 500);
}

if (!$task) {
    // Contrato antigo: array vazio quando não encontra.
    responder_json(array());
}

$taskId = (int) $task['id'];

$task['comentarios'] = array();
try {
    foreach (db_all('SELECT * FROM comentarios WHERE hash_tarefa = ? OR id_tarefa_principal = ?',
                    array($token, $taskId)) as $c) {
        $c['is_subtask'] = isset($c['id_tarefa_principal']) && (int) $c['id_tarefa_principal'] === $taskId;
        $task['comentarios'][] = $c;
    }
} catch (Exception $e) {
    error_log('[tarefas] view_task comentarios: ' . $e->getMessage());
}

$task['recibo_gerado'] = false;
$task['guia_gerada']   = false;

if (db_tem_tabela('recibos_de_entrega')) {
    try {
        $task['recibo_gerado'] = (int) db_valor(
            'SELECT COUNT(*) FROM recibos_de_entrega WHERE task_id = ?', array($taskId), 0) > 0;
    } catch (Exception $e) { /* segue sem o dado */ }
}
if (db_tem_tabela('guia_de_recebimento')) {
    try {
        $task['guia_gerada'] = (int) db_valor(
            'SELECT COUNT(*) FROM guia_de_recebimento WHERE task_id = ?', array($taskId), 0) > 0;
    } catch (Exception $e) { /* segue sem o dado */ }
}

responder_json($task);
