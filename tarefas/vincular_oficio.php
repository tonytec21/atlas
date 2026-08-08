<?php
/** Atlas · Tarefas — compatibilidade: vínculo de ofício à tarefa. */

require_once __DIR__ . '/core/bootstrap.php';
exigir_login();

header('Content-Type: text/plain; charset=utf-8');

$token  = entrada('taskToken', '', $_POST);
$numero = entrada('numeroOficio', '', $_POST);

if ($token === '' || $numero === '') {
    echo 'Informe a tarefa e o número do ofício.';
    exit;
}

$t = db_one('SELECT id, numero_oficio FROM tarefas WHERE token = ?', array($token));
if (!$t) {
    echo 'Tarefa não encontrada.';
    exit;
}

try {
    db_exec('UPDATE tarefas SET numero_oficio = ? WHERE token = ?', array($numero, $token));
    registrar_historico((int) $t['id'], 'oficio', 'Ofício vinculado', $t['numero_oficio'], $numero);
    echo 'Ofício vinculado com sucesso!';
} catch (Exception $e) {
    error_log('[tarefas] vincular_oficio: ' . $e->getMessage());
    echo 'Erro ao vincular o ofício.';
}
