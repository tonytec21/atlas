<?php
/** Atlas · Tarefas — tarefa principal de uma subtarefa (contrato original). */
require_once __DIR__ . '/core/bootstrap.php';
api_iniciar();

$idSub = entrada_int('id_tarefa_sub');
$idPrincipal = $idSub > 0
    ? db_valor("SELECT id_tarefa_principal FROM tarefas WHERE id = ? AND sub_categoria = 'Sim'", array($idSub))
    : null;

if (!$idPrincipal) {
    responder_json(array('error' => 'Tarefa principal não encontrada'));
}

$t = db_one('SELECT id, titulo, funcionario_responsavel, data_criacao, data_limite, status
               FROM tarefas WHERE id = ?', array((int) $idPrincipal));

responder_json($t ? $t : array('error' => 'Tarefa principal não encontrada'));
