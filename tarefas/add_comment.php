<?php
/** Atlas · Tarefas — compatibilidade: novo comentário na linha do tempo. */

require_once __DIR__ . '/core/bootstrap.php';
exigir_login();

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'Método inválido.';
    exit;
}

$u     = usuario_atual();
$token = entrada('taskToken', '', $_POST);
$texto = entrada('commentDescription', '', $_POST);

if ($token === '') {
    echo 'Tarefa não informada.';
    exit;
}

$t = db_one('SELECT id, id_tarefa_principal FROM tarefas WHERE token = ?', array($token));
if (!$t) {
    echo 'Tarefa não encontrada.';
    exit;
}

$up = salvar_uploads('commentAttachments', $token);

try {
    db_exec(
        'INSERT INTO comentarios
            (hash_tarefa, comentario, caminho_anexo, data_comentario, funcionario, status, id_tarefa_principal)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        array(
            $token,
            $texto,
            implode(';', $up['caminhos']),
            date('Y-m-d H:i:s'),
            $u['usuario'],
            'Ativo',
            $t['id_tarefa_principal'] !== null ? (int) $t['id_tarefa_principal'] : null,
        )
    );
    registrar_historico((int) $t['id'], 'comentario', 'Comentário adicionado');
    echo 'Comentário adicionado com sucesso!';
} catch (Exception $e) {
    error_log('[tarefas] add_comment: ' . $e->getMessage());
    echo 'Erro ao adicionar comentário.';
}
