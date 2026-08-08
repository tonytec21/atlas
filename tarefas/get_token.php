<?php
/** Atlas · Tarefas — token de uma tarefa pelo número do protocolo. */
require_once __DIR__ . '/core/bootstrap.php';
api_iniciar();

$id = entrada_int('id');
if ($id <= 0) {
    responder_json(array('error' => 'ID não fornecido.'));
}

$token = db_valor('SELECT token FROM tarefas WHERE id = ?', array($id));
responder_json($token === null
    ? array('error' => 'Token não encontrado.')
    : array('token' => $token));
