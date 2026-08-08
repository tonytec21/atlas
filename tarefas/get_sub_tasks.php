<?php
/** Atlas · Tarefas — subtarefas de uma tarefa principal (contrato original). */
require_once __DIR__ . '/core/bootstrap.php';
api_iniciar();

$id = entrada_int('id_tarefa_principal');
$subs = $id > 0
    ? db_all("SELECT id, titulo, funcionario_responsavel, data_criacao, data_limite, status
                FROM tarefas WHERE id_tarefa_principal = ? AND sub_categoria = 'Sim' ORDER BY id",
             array($id))
    : array();

while (ob_get_level() > 0) { ob_end_clean(); }
echo json_encode($subs, JSON_UNESCAPED_UNICODE);
