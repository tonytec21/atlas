<?php
/**
 * Atlas · Tarefas — compatibilidade: consulta do vínculo pai de uma tarefa.
 *
 * Correção: a versão anterior consultava a coluna `hash_tarefa` na tabela
 * `tarefas`, que não existe ali (é da tabela `comentarios`). A consulta
 * falhava silenciosamente. Aqui usamos `token`, que é o campo correto, e
 * continuamos aceitando o parâmetro com o nome antigo.
 */
require_once __DIR__ . '/core/bootstrap.php';
api_iniciar();

$token = entrada('hash_tarefa', entrada('token'));
if ($token === '') {
    responder_json(array('id_tarefa_principal' => null));
}

$v = db_valor('SELECT id_tarefa_principal FROM tarefas WHERE token = ?', array($token));
responder_json(array('id_tarefa_principal' => $v === null ? null : (int) $v));
