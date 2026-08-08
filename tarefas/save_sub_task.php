<?php
/**
 * Atlas · Tarefas — compatibilidade: criação de subtarefa.
 *
 * Aceita tanto o envio tradicional por formulário (que redirecionava para
 * edit_task.php) quanto chamadas AJAX. Quando o cabeçalho da requisição
 * indicar AJAX, a resposta vem em JSON.
 */

require_once __DIR__ . '/core/bootstrap.php';
exigir_login();

$ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

function responderSub($ajax, $ok, $mensagem, $id = 0)
{
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success' => $ok, 'message' => $mensagem, 'id' => $id), JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ok) {
        header('Location: edit_task.php?id=' . $id);
        exit;
    }
    echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderSub($ajax, false, 'Método de requisição inválido.');
}

$u = usuario_atual();
$idPrincipal = entrada_int('id_tarefa_principal', 0, $_POST);
$titulo = entrada('title', '', $_POST);

if ($idPrincipal <= 0 || $titulo === '') {
    responderSub($ajax, false, 'Informe a tarefa principal e o título da subtarefa.');
}

$token = md5(uniqid((string) mt_rand(), true));
$caminhoAnexo = '';

/* Compartilhar anexos: a subtarefa aponta para os mesmos arquivos da principal. */
if (isset($_POST['compartilharAnexos']) && $_POST['compartilharAnexos'] !== '') {
    $caminhoAnexo = (string) db_valor('SELECT caminho_anexo FROM tarefas WHERE id = ?',
        array($idPrincipal), '');
} else {
    $up = salvar_uploads('attachments', $token);
    $caminhoAnexo = implode(';', $up['caminhos']);
}

try {
    db_exec(
        "INSERT INTO tarefas
            (token, titulo, categoria, origem, descricao, data_limite, funcionario_responsavel,
             criado_por, data_criacao, caminho_anexo, nivel_de_prioridade, sub_categoria,
             id_tarefa_principal, revisor)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Sim', ?, ?)",
        array(
            $token,
            $titulo,
            entrada('category', '', $_POST),
            entrada('origin', '', $_POST),
            entrada('description', '', $_POST),
            data_para_mysql(entrada('deadline', '', $_POST)),
            entrada('employee', '', $_POST),
            entrada('createdBy', $u['usuario'], $_POST),
            date('Y-m-d H:i:s'),
            $caminhoAnexo,
            entrada('priority', 'Média', $_POST),
            $idPrincipal,
            entrada('reviewer', '', $_POST),
        )
    );
    $novoId = (int) db()->lastInsertId();
} catch (Exception $e) {
    error_log('[tarefas] save_sub_task: ' . $e->getMessage());
    responderSub($ajax, false, 'Erro ao salvar a subtarefa.');
}

registrar_historico($novoId, 'criacao', 'Subtarefa criada a partir da tarefa #' . $idPrincipal);
registrar_historico($idPrincipal, 'edicao', 'Subtarefa #' . $novoId . ' criada');

responderSub($ajax, true, 'Subtarefa criada com sucesso.', $novoId);
