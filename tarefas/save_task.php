<?php
/**
 * Atlas · Tarefas — compatibilidade: criação de tarefa.
 *
 * Mesmo contrato do módulo antigo (POST do formulário de criar-tarefa.php,
 * resposta JSON com success/message/token/redirect). O que mudou por dentro:
 * os anexos passam por validação de extensão e tamanho, e a criação fica
 * registrada no histórico.
 */

require_once __DIR__ . '/core/bootstrap.php';
api_iniciar();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(array('success' => false, 'message' => 'Método de requisição inválido'), 405);
}

$u = usuario_atual();

$titulo = entrada('title', '', $_POST);
if ($titulo === '') {
    responder_json(array('success' => false, 'message' => 'Informe o título da tarefa.'));
}

$token = md5(uniqid((string) mt_rand(), true));
$up = salvar_uploads('attachments', $token);

$campos = array(
    'token'                   => $token,
    'titulo'                  => $titulo,
    'categoria'               => entrada('category', '', $_POST),
    'origem'                  => entrada('origin', '', $_POST),
    'descricao'               => entrada('description', '', $_POST),
    'data_limite'             => data_para_mysql(entrada('deadline', '', $_POST)),
    'funcionario_responsavel' => entrada('employee', '', $_POST),
    'criado_por'              => entrada('createdBy', $u['usuario'], $_POST),
    'data_criacao'            => date('Y-m-d H:i:s'),
    'caminho_anexo'           => implode(';', $up['caminhos']),
    'nivel_de_prioridade'     => entrada('priority', 'Média', $_POST),
    'revisor'                 => entrada('reviewer', '', $_POST),
);

foreach (array('tags', 'apresentante') as $col) {
    $v = entrada($col, '', $_POST);
    if ($v !== '' && db_tem_coluna('tarefas', $col)) {
        $campos[$col] = $v;
    }
}

try {
    $cols = array_keys($campos);
    db_exec(
        'INSERT INTO tarefas (`' . implode('`, `', $cols) . '`) VALUES ('
        . implode(', ', array_fill(0, count($cols), '?')) . ')',
        array_values($campos)
    );
    $novoId = (int) db()->lastInsertId();
} catch (Exception $e) {
    error_log('[tarefas] save_task: ' . $e->getMessage());
    responder_json(array('success' => false, 'message' => 'Erro ao salvar a tarefa.'), 500);
}

registrar_historico($novoId, 'criacao', 'Tarefa criada');

responder_json(array(
    'success'  => true,
    'message'  => 'Tarefa salva com sucesso!',
    'id'       => $novoId,
    'token'    => $token,
    'avisos'   => $up['erros'],
    'redirect' => 'index.php?token=' . $token,
));
