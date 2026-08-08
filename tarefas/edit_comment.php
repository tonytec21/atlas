<?php
/**
 * Atlas · Tarefas — compatibilidade: edição de comentário.
 *
 * Acrescenta a verificação que faltava: só o autor do comentário (ou um
 * administrador) pode alterá-lo.
 */

require_once __DIR__ . '/core/bootstrap.php';
exigir_login();

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'Método inválido.';
    exit;
}

$u  = usuario_atual();
$id = entrada_int('commentId', 0, $_POST);

$c = db_one('SELECT * FROM comentarios WHERE id = ?', array($id));
if (!$c) {
    echo 'Comentário não encontrado.';
    exit;
}
if (!usuario_ve_tudo() && $c['funcionario'] !== $u['usuario']) {
    echo 'Você só pode editar os próprios comentários.';
    exit;
}

try {
    db_exec('UPDATE comentarios SET comentario = ?, data_atualizacao = ? WHERE id = ?',
        array(entrada('editCommentDescription', '', $_POST), date('Y-m-d H:i:s'), $id));
} catch (Exception $e) {
    error_log('[tarefas] edit_comment: ' . $e->getMessage());
    echo 'Erro ao atualizar o comentário.';
    exit;
}

if (!empty($_FILES['editCommentAttachments']['name'][0])) {
    $token = entrada('taskToken', $c['hash_tarefa'], $_POST);
    $up = salvar_uploads('editCommentAttachments', $token);
    if ($up['caminhos']) {
        db_exec('UPDATE comentarios SET caminho_anexo = ? WHERE id = ?',
            array(anexos_concatenar($c['caminho_anexo'], $up['caminhos']), $id));
    }
}

echo 'Comentário atualizado com sucesso.';
