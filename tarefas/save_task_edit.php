<?php
/**
 * Atlas · Tarefas — compatibilidade: edição de tarefa.
 *
 * Mesmo contrato (POST + JSON status/message). Correções em relação à versão
 * anterior: a resposta JSON era impressa ANTES do processamento dos anexos,
 * o que às vezes gerava texto solto depois do JSON e quebrava o parse no
 * navegador. Agora a resposta sai uma única vez, no fim.
 */

require_once __DIR__ . '/core/bootstrap.php';
api_iniciar();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(array('status' => 'error', 'message' => 'Método inválido.'), 405);
}

$u  = usuario_atual();
$id = entrada_int('taskId', 0, $_POST);

$t = db_one('SELECT * FROM tarefas WHERE id = ?', array($id));
if (!$t) {
    responder_json(array('status' => 'error', 'message' => 'Tarefa não encontrada.'), 404);
}

if (!usuario_ve_tudo()
    && $t['funcionario_responsavel'] !== $u['nome']
    && $t['revisor'] !== $u['nome']
    && $t['criado_por'] !== $u['usuario']) {
    responder_json(array('status' => 'error', 'message' => 'Sem permissão para editar esta tarefa.'), 403);
}

$prazo = data_para_mysql(entrada('deadline', '', $_POST));
if ($prazo === null) {
    responder_json(array('status' => 'error', 'message' => 'Data limite inválida.'));
}

$novo = array(
    'titulo'                  => entrada('title', '', $_POST),
    'categoria'               => entrada('category', '', $_POST),
    'origem'                  => entrada('origin', '', $_POST),
    'data_limite'             => $prazo,
    'funcionario_responsavel' => entrada('employee', '', $_POST),
    'descricao'               => entrada('description', '', $_POST),
    'data_atualizacao'        => date('Y-m-d H:i:s'),
    'atualizado_por'          => entrada('updatedBy', $u['usuario'], $_POST),
    'nivel_de_prioridade'     => entrada('priority', '', $_POST),
    'revisor'                 => entrada('reviewer', '', $_POST),
);

if ($novo['titulo'] === '') {
    responder_json(array('status' => 'error', 'message' => 'Informe o título da tarefa.'));
}

try {
    $partes = array();
    foreach (array_keys($novo) as $c) { $partes[] = "`$c` = ?"; }
    $valores = array_values($novo);
    $valores[] = $id;
    db_exec('UPDATE tarefas SET ' . implode(', ', $partes) . ' WHERE id = ?', $valores);
} catch (Exception $e) {
    error_log('[tarefas] save_task_edit: ' . $e->getMessage());
    responder_json(array('status' => 'error', 'message' => 'Erro ao atualizar a tarefa.'), 500);
}

/* Anexos novos somam-se aos existentes — nunca substituem. */
$avisos = array();
if (!empty($_FILES['attachments']['name'][0])) {
    $tokenTarefa = entrada('taskToken', $t['token'], $_POST);
    $up = salvar_uploads('attachments', $tokenTarefa);
    $avisos = $up['erros'];
    if ($up['caminhos']) {
        db_exec('UPDATE tarefas SET caminho_anexo = ? WHERE id = ?',
            array(anexos_concatenar($t['caminho_anexo'], $up['caminhos']), $id));
        registrar_historico($id, 'anexo', count($up['caminhos']) . ' anexo(s) adicionado(s)');
    }
}

foreach (array(
    'titulo' => 'Título', 'data_limite' => 'Data limite',
    'funcionario_responsavel' => 'Responsável', 'revisor' => 'Revisor',
    'nivel_de_prioridade' => 'Prioridade',
) as $col => $rotulo) {
    if ((string) $novo[$col] !== (string) $t[$col]) {
        registrar_historico($id, 'edicao', $rotulo . ' alterado', $t[$col], $novo[$col]);
    }
}

responder_json(array(
    'status'  => 'success',
    'message' => 'Tarefa atualizada com sucesso.',
    'avisos'  => $avisos,
));
