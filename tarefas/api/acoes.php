<?php
/**
 * Atlas · Tarefas — ações de escrita.
 *
 * Um único endpoint POST com o parâmetro `acao` decidindo a operação.
 * Todas exigem login e token CSRF válido.
 *
 * Cada ação grava o que mudou em `tarefas_historico`, de modo que a tela de
 * detalhe passa a mostrar quem fez o quê e quando — algo que o módulo
 * anterior não registrava.
 */

require_once __DIR__ . '/../core/bootstrap.php';
api_iniciar();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_erro('Método não permitido.', 405);
}

csrf_validar();

$acao = entrada('acao', '', $_POST);
$u    = usuario_atual();

/** Carrega a tarefa ou encerra com erro. */
function exigir_tarefa($id)
{
    $t = db_one('SELECT * FROM tarefas WHERE id = ? LIMIT 1', array((int) $id));
    if (!$t) {
        responder_erro('Tarefa não encontrada.', 404);
    }
    return $t;
}

/** O usuário pode alterar esta tarefa? */
function pode_editar(array $t)
{
    if (usuario_ve_tudo()) {
        return true;
    }
    $u = usuario_atual();
    return $t['funcionario_responsavel'] === $u['nome']
        || $t['revisor'] === $u['nome']
        || $t['criado_por'] === $u['usuario'];
}

switch ($acao) {

/* ================================================================== */
/* Criar tarefa (e subtarefa)                                         */
/* ================================================================== */
case 'criar':
case 'criar_subtarefa':

    $titulo = entrada('title', entrada('titulo', '', $_POST), $_POST);
    if ($titulo === '') {
        responder_erro('Informe o título da tarefa.');
    }

    $eSub = ($acao === 'criar_subtarefa');
    $idPrincipal = entrada_int('id_tarefa_principal', 0, $_POST);
    if ($eSub && $idPrincipal <= 0) {
        responder_erro('Informe a tarefa principal da subtarefa.');
    }

    $token = md5(uniqid((string) mt_rand(), true));
    $prazo = data_para_mysql(entrada('deadline', entrada('data_limite', '', $_POST), $_POST));

    $caminhoAnexo = '';
    $errosUpload  = array();

    /* Subtarefa pode herdar os anexos da tarefa principal. */
    if ($eSub && entrada('compartilharAnexos', '', $_POST) !== '') {
        $caminhoAnexo = (string) db_valor(
            'SELECT caminho_anexo FROM tarefas WHERE id = ?', array($idPrincipal), ''
        );
    } else {
        $up = salvar_uploads('attachments', $token);
        $caminhoAnexo = implode(';', $up['caminhos']);
        $errosUpload  = $up['erros'];
    }

    $campos = array(
        'token'                   => $token,
        'titulo'                  => $titulo,
        'categoria'               => entrada('category', entrada('categoria', '', $_POST), $_POST),
        'origem'                  => entrada('origin', entrada('origem', '', $_POST), $_POST),
        'descricao'               => entrada('description', entrada('descricao', '', $_POST), $_POST),
        'data_limite'             => $prazo,
        'funcionario_responsavel' => entrada('employee', entrada('funcionario', '', $_POST), $_POST),
        'criado_por'              => $u['usuario'],
        'data_criacao'            => date('Y-m-d H:i:s'),
        'caminho_anexo'           => $caminhoAnexo,
        'nivel_de_prioridade'     => entrada('priority', entrada('prioridade', 'Média', $_POST), $_POST),
        'revisor'                 => entrada('reviewer', entrada('revisor', '', $_POST), $_POST),
    );

    if ($eSub) {
        $campos['sub_categoria']       = 'Sim';
        $campos['id_tarefa_principal'] = $idPrincipal;
    }

    foreach (array('tags' => 'tags', 'apresentante' => 'apresentante') as $post => $col) {
        $v = entrada($post, '', $_POST);
        if ($v !== '' && db_tem_coluna('tarefas', $col)) {
            $campos[$col] = $v;
        }
    }

    $cols = array_keys($campos);
    $sql = 'INSERT INTO tarefas (`' . implode('`, `', $cols) . '`) VALUES ('
         . implode(', ', array_fill(0, count($cols), '?')) . ')';

    try {
        db_exec($sql, array_values($campos));
        $novoId = (int) db()->lastInsertId();
    } catch (Exception $e) {
        error_log('[tarefas] criar: ' . $e->getMessage());
        responder_erro('Não foi possível salvar a tarefa.', 500);
    }

    registrar_historico($novoId, 'criacao', $eSub ? 'Subtarefa criada' : 'Tarefa criada');

    /* Checklist sugerido pela IA na criação, quando solicitado. */
    if (entrada('gerar_checklist', '', $_POST) === '1' && db_tem_tabela('tarefas_checklist')) {
        require_once __DIR__ . '/../core/gemini.php';
        if (ia_disponivel()) {
            $r = gemini_gerar_json(
                "Tarefa de cartório extrajudicial:\nTítulo: {$campos['titulo']}\n"
                . "Descrição: {$campos['descricao']}\n\n"
                . 'Liste de 4 a 8 etapas objetivas de conferência para concluir esta tarefa. '
                . 'Responda em JSON: {"itens":["etapa 1","etapa 2"]}',
                array('recurso' => 'checklist', 'max_tokens' => 900)
            );
            if ($r['success'] && !empty($r['dados']['itens'])) {
                $ordem = 0;
                foreach ((array) $r['dados']['itens'] as $item) {
                    $texto = trim((string) $item);
                    if ($texto === '') {
                        continue;
                    }
                    db_exec(
                        'INSERT INTO tarefas_checklist (tarefa_id, descricao, ordem, origem, criado_por, criado_em)
                         VALUES (?, ?, ?, ?, ?, NOW())',
                        array($novoId, mb_substr($texto, 0, 300), $ordem++, 'ia', $u['usuario'])
                    );
                }
            }
        }
    }

    responder_ok(array(
        'id'      => $novoId,
        'token'   => $token,
        'avisos'  => $errosUpload,
        'mensagem' => $eSub ? 'Subtarefa criada com sucesso.' : 'Tarefa criada com sucesso.',
    ));

/* ================================================================== */
/* Editar tarefa                                                      */
/* ================================================================== */
case 'editar':

    $id = entrada_int('taskId', entrada_int('id', 0, $_POST), $_POST);
    $t  = exigir_tarefa($id);
    if (!pode_editar($t)) {
        responder_erro('Você não tem permissão para editar esta tarefa.', 403);
    }

    $titulo = entrada('title', entrada('titulo', '', $_POST), $_POST);
    if ($titulo === '') {
        responder_erro('Informe o título da tarefa.');
    }

    $prazo = data_para_mysql(entrada('deadline', entrada('data_limite', '', $_POST), $_POST));

    $set = array(
        'titulo'                  => $titulo,
        'categoria'               => entrada('category', entrada('categoria', '', $_POST), $_POST),
        'origem'                  => entrada('origin', entrada('origem', '', $_POST), $_POST),
        'data_limite'             => $prazo,
        'funcionario_responsavel' => entrada('employee', entrada('funcionario', '', $_POST), $_POST),
        'descricao'               => entrada('description', entrada('descricao', '', $_POST), $_POST),
        'nivel_de_prioridade'     => entrada('priority', entrada('prioridade', '', $_POST), $_POST),
        'revisor'                 => entrada('reviewer', entrada('revisor', '', $_POST), $_POST),
        'data_atualizacao'        => date('Y-m-d H:i:s'),
        'atualizado_por'          => $u['usuario'],
    );

    foreach (array('tags', 'apresentante') as $col) {
        if (db_tem_coluna('tarefas', $col) && isset($_POST[$col])) {
            $set[$col] = entrada($col, '', $_POST);
        }
    }

    $partes = array();
    foreach (array_keys($set) as $c) {
        $partes[] = "`$c` = ?";
    }
    $valores = array_values($set);
    $valores[] = $id;

    try {
        db_exec('UPDATE tarefas SET ' . implode(', ', $partes) . ' WHERE id = ?', $valores);
    } catch (Exception $e) {
        error_log('[tarefas] editar: ' . $e->getMessage());
        responder_erro('Não foi possível atualizar a tarefa.', 500);
    }

    /* Anexos novos entram somando-se aos existentes, nunca substituindo. */
    $avisos = array();
    if (!empty($_FILES['attachments']['name'][0])) {
        $up = salvar_uploads('attachments', $t['token']);
        $avisos = $up['erros'];
        if ($up['caminhos']) {
            $novo = anexos_concatenar($t['caminho_anexo'], $up['caminhos']);
            db_exec('UPDATE tarefas SET caminho_anexo = ? WHERE id = ?', array($novo, $id));
            registrar_historico($id, 'anexo', count($up['caminhos']) . ' anexo(s) adicionado(s)');
        }
    }

    /* Registra apenas os campos que realmente mudaram. */
    foreach (array(
        'titulo' => 'Título', 'data_limite' => 'Data limite',
        'funcionario_responsavel' => 'Responsável', 'revisor' => 'Revisor',
        'nivel_de_prioridade' => 'Prioridade',
    ) as $col => $rotulo) {
        if (isset($set[$col]) && (string) $set[$col] !== (string) $t[$col]) {
            registrar_historico($id, 'edicao', $rotulo . ' alterado', $t[$col], $set[$col]);
        }
    }

    responder_ok(array('mensagem' => 'Tarefa atualizada com sucesso.', 'avisos' => $avisos));

/* ================================================================== */
/* Status                                                             */
/* ================================================================== */
case 'status':

    $token  = entrada('taskToken', entrada('token', '', $_POST), $_POST);
    $status = entrada('status', '', $_POST);

    if ($token === '' || $status === '') {
        responder_erro('Informe a tarefa e o novo status.');
    }
    if (!array_key_exists($status, tarefas_status_catalogo())) {
        responder_erro('Status inválido.');
    }

    $t = db_one('SELECT * FROM tarefas WHERE token = ? LIMIT 1', array($token));
    if (!$t) {
        responder_erro('Tarefa não encontrada.', 404);
    }

    $conclusao = in_array($status, tarefas_status_conclui(), true) ? date('Y-m-d H:i:s') : null;

    $sql = 'UPDATE tarefas SET status = ?, data_conclusao = ?';
    $par = array($status, $conclusao);
    if (db_tem_coluna('tarefas', 'concluido_por')) {
        $sql .= ', concluido_por = ?';
        $par[] = $conclusao === null ? null : $u['nome'];
    }
    $sql .= ' WHERE token = ?';
    $par[] = $token;

    try {
        db_exec($sql, $par);
    } catch (Exception $e) {
        error_log('[tarefas] status: ' . $e->getMessage());
        responder_erro('Não foi possível atualizar o status.', 500);
    }

    registrar_historico((int) $t['id'], 'status', 'Status alterado', $t['status'], $status);

    responder_ok(array(
        'mensagem'  => 'Status atualizado.',
        'status'    => $status,
        'cor'       => cor_status($status),
        'conclusao' => $conclusao === null ? '' : data_br($conclusao),
    ));

/* ================================================================== */
/* Kanban: mover cartão                                               */
/* ================================================================== */
case 'mover_kanban':

    $id     = entrada_int('id', 0, $_POST);
    $status = entrada('status', '', $_POST);
    $ordem  = entrada_int('ordem', 0, $_POST);

    $t = exigir_tarefa($id);
    if (!array_key_exists($status, tarefas_status_catalogo())) {
        responder_erro('Status inválido.');
    }

    $mudouStatus = ((string) $t['status'] !== $status);
    $conclusao = in_array($status, tarefas_status_conclui(), true) ? date('Y-m-d H:i:s') : null;

    try {
        if ($mudouStatus) {
            db_exec(
                'UPDATE tarefas SET status = ?, data_conclusao = ? WHERE id = ?',
                array($status, $conclusao, $id)
            );
            registrar_historico($id, 'status', 'Movida no Kanban', $t['status'], $status);
        }
        if (db_tem_coluna('tarefas', 'ordem_kanban')) {
            db_exec('UPDATE tarefas SET ordem_kanban = ? WHERE id = ?', array($ordem, $id));
        }
    } catch (Exception $e) {
        error_log('[tarefas] mover_kanban: ' . $e->getMessage());
        responder_erro('Não foi possível mover a tarefa.', 500);
    }

    responder_ok(array('status' => $status, 'mudou' => $mudouStatus));

/* ================================================================== */
/* Ações em lote                                                      */
/* ================================================================== */
case 'lote':

    $ids = entrada('ids', array(), $_POST);
    if (is_string($ids)) {
        $ids = array_filter(array_map('intval', explode(',', $ids)));
    }
    $ids = array_values(array_filter(array_map('intval', (array) $ids)));

    if (!$ids) {
        responder_erro('Selecione ao menos uma tarefa.');
    }
    if (count($ids) > 200) {
        responder_erro('Selecione no máximo 200 tarefas por vez.');
    }

    $operacao = entrada('operacao', '', $_POST);
    $valor    = entrada('valor', '', $_POST);
    $marc     = implode(',', array_fill(0, count($ids), '?'));
    $afetadas = 0;

    try {
        if ($operacao === 'status') {
            if (!array_key_exists($valor, tarefas_status_catalogo())) {
                responder_erro('Status inválido.');
            }
            $conclusao = in_array($valor, tarefas_status_conclui(), true) ? date('Y-m-d H:i:s') : null;
            $afetadas = db_exec(
                "UPDATE tarefas SET status = ?, data_conclusao = ? WHERE id IN ($marc)",
                array_merge(array($valor, $conclusao), $ids)
            );
            foreach ($ids as $i) {
                registrar_historico($i, 'status', 'Alteração em lote', null, $valor);
            }
        } elseif ($operacao === 'responsavel') {
            if ($valor === '') {
                responder_erro('Selecione o responsável.');
            }
            $afetadas = db_exec(
                "UPDATE tarefas SET funcionario_responsavel = ?, data_atualizacao = NOW(), atualizado_por = ?
                  WHERE id IN ($marc)",
                array_merge(array($valor, $u['usuario']), $ids)
            );
            foreach ($ids as $i) {
                registrar_historico($i, 'edicao', 'Responsável definido em lote', null, $valor);
            }
        } elseif ($operacao === 'prioridade') {
            if (!array_key_exists($valor, tarefas_prioridades())) {
                responder_erro('Prioridade inválida.');
            }
            $afetadas = db_exec(
                "UPDATE tarefas SET nivel_de_prioridade = ? WHERE id IN ($marc)",
                array_merge(array($valor), $ids)
            );
        } else {
            responder_erro('Operação em lote desconhecida.');
        }
    } catch (Exception $e) {
        error_log('[tarefas] lote: ' . $e->getMessage());
        responder_erro('Não foi possível aplicar a alteração em lote.', 500);
    }

    responder_ok(array('afetadas' => $afetadas, 'mensagem' => $afetadas . ' tarefa(s) atualizada(s).'));

/* ================================================================== */
/* Assumir tarefa                                                     */
/* ================================================================== */
case 'assumir':

    $id = entrada_int('id', 0, $_POST);
    $t  = exigir_tarefa($id);

    db_exec(
        'UPDATE tarefas SET funcionario_responsavel = ?, data_atualizacao = NOW(), atualizado_por = ? WHERE id = ?',
        array($u['nome'], $u['usuario'], $id)
    );
    registrar_historico($id, 'edicao', 'Tarefa assumida', $t['funcionario_responsavel'], $u['nome']);

    responder_ok(array('mensagem' => 'Você agora é o responsável por esta tarefa.', 'responsavel' => $u['nome']));

/* ================================================================== */
/* Excluir tarefa                                                     */
/* ================================================================== */
case 'excluir':

    if (!usuario_ve_tudo()) {
        responder_erro('Somente administradores podem excluir tarefas.', 403);
    }

    $id = entrada_int('id', 0, $_POST);
    $t  = exigir_tarefa($id);

    $subs = (int) db_valor(
        "SELECT COUNT(*) FROM tarefas WHERE id_tarefa_principal = ? AND sub_categoria = 'Sim'",
        array($id), 0
    );
    if ($subs > 0 && entrada('confirmar_subtarefas', '', $_POST) !== '1') {
        responder_erro('Esta tarefa possui ' . $subs . ' subtarefa(s). Confirme para prosseguir.', 409,
            array('subtarefas' => $subs));
    }

    try {
        db_exec('DELETE FROM tarefas WHERE id = ?', array($id));
    } catch (Exception $e) {
        error_log('[tarefas] excluir: ' . $e->getMessage());
        responder_erro('Não foi possível excluir a tarefa.', 500);
    }

    registrar_historico($id, 'exclusao', 'Tarefa excluída: ' . $t['titulo']);
    responder_ok(array('mensagem' => 'Tarefa excluída.'));

/* ================================================================== */
/* Comentários                                                        */
/* ================================================================== */
case 'comentar':

    $token = entrada('taskToken', entrada('token', '', $_POST), $_POST);
    $texto = entrada('commentDescription', entrada('comentario', '', $_POST), $_POST);

    if ($token === '') {
        responder_erro('Tarefa não informada.');
    }
    if ($texto === '' && empty($_FILES['commentAttachments']['name'][0])) {
        responder_erro('Escreva um comentário ou anexe um arquivo.');
    }

    $t = db_one('SELECT id, id_tarefa_principal FROM tarefas WHERE token = ? LIMIT 1', array($token));
    if (!$t) {
        responder_erro('Tarefa não encontrada.', 404);
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
        $comentarioId = (int) db()->lastInsertId();
    } catch (Exception $e) {
        error_log('[tarefas] comentar: ' . $e->getMessage());
        responder_erro('Não foi possível salvar o comentário.', 500);
    }

    registrar_historico((int) $t['id'], 'comentario', 'Comentário adicionado');

    responder_ok(array('id' => $comentarioId, 'mensagem' => 'Comentário adicionado.', 'avisos' => $up['erros']));

case 'editar_comentario':

    $id    = entrada_int('commentId', entrada_int('id', 0, $_POST), $_POST);
    $texto = entrada('editCommentDescription', entrada('comentario', '', $_POST), $_POST);
    $token = entrada('taskToken', entrada('token', '', $_POST), $_POST);

    $c = db_one('SELECT * FROM comentarios WHERE id = ? LIMIT 1', array($id));
    if (!$c) {
        responder_erro('Comentário não encontrado.', 404);
    }
    if (!usuario_ve_tudo() && $c['funcionario'] !== $u['usuario']) {
        responder_erro('Você só pode editar os próprios comentários.', 403);
    }

    db_exec(
        'UPDATE comentarios SET comentario = ?, data_atualizacao = ? WHERE id = ?',
        array($texto, date('Y-m-d H:i:s'), $id)
    );

    $avisos = array();
    if (!empty($_FILES['editCommentAttachments']['name'][0])) {
        $up = salvar_uploads('editCommentAttachments', $token !== '' ? $token : $c['hash_tarefa']);
        $avisos = $up['erros'];
        if ($up['caminhos']) {
            db_exec(
                'UPDATE comentarios SET caminho_anexo = ? WHERE id = ?',
                array(anexos_concatenar($c['caminho_anexo'], $up['caminhos']), $id)
            );
        }
    }

    responder_ok(array('mensagem' => 'Comentário atualizado.', 'avisos' => $avisos));

case 'excluir_comentario':

    $id = entrada_int('id', 0, $_POST);
    $c  = db_one('SELECT * FROM comentarios WHERE id = ? LIMIT 1', array($id));
    if (!$c) {
        responder_erro('Comentário não encontrado.', 404);
    }
    if (!usuario_ve_tudo() && $c['funcionario'] !== $u['usuario']) {
        responder_erro('Você só pode excluir os próprios comentários.', 403);
    }

    db_exec('DELETE FROM comentarios WHERE id = ?', array($id));
    responder_ok(array('mensagem' => 'Comentário excluído.'));

/* ================================================================== */
/* Anexos                                                             */
/* ================================================================== */
case 'excluir_anexo':

    $tarefaId = entrada_int('taskId', entrada_int('tarefa_id', 0, $_POST), $_POST);
    $alvo     = entrada('file', entrada('arquivo', '', $_POST), $_POST);

    $t = exigir_tarefa($tarefaId);
    if (!pode_editar($t)) {
        responder_erro('Sem permissão para remover anexos desta tarefa.', 403);
    }

    $restantes = array();
    $removido  = null;
    foreach (preg_split('/[;\r\n]+/', (string) $t['caminho_anexo']) as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        // Compara pelo nome do arquivo — os caminhos antigos variam de formato.
        if (basename(str_replace('\\', '/', $p)) === basename(str_replace('\\', '/', $alvo))
            || $p === $alvo) {
            $removido = $p;
            continue;
        }
        $restantes[] = $p;
    }

    if ($removido === null) {
        responder_erro('Anexo não encontrado nesta tarefa.', 404);
    }

    db_exec('UPDATE tarefas SET caminho_anexo = ? WHERE id = ?',
        array(implode(';', $restantes), $tarefaId));

    // Só apaga do disco se o arquivo estiver mesmo dentro da pasta de anexos.
    $lista = anexos_lista($removido);
    if ($lista) {
        $abs = realpath(TAREFAS_DIR . '/' . $lista[0]['rel']);
        $base = realpath(TAREFAS_DIR_ARQUIVOS);
        if ($abs && $base && strpos($abs, $base) === 0 && is_file($abs)) {
            @unlink($abs);
        }
    }

    registrar_historico($tarefaId, 'anexo', 'Anexo removido: ' . basename($removido));
    responder_ok(array('mensagem' => 'Anexo excluído.'));

case 'excluir_anexo_comentario':

    $comentarioId = entrada_int('commentId', entrada_int('id', 0, $_POST), $_POST);
    $alvo = entrada('file', entrada('arquivo', '', $_POST), $_POST);

    $c = db_one('SELECT * FROM comentarios WHERE id = ? LIMIT 1', array($comentarioId));
    if (!$c) {
        responder_erro('Comentário não encontrado.', 404);
    }
    if (!usuario_ve_tudo() && $c['funcionario'] !== $u['usuario']) {
        responder_erro('Você só pode alterar os próprios comentários.', 403);
    }

    $restantes = array();
    $removido  = null;
    foreach (preg_split('/[;\r\n]+/', (string) $c['caminho_anexo']) as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        if (basename(str_replace('\\', '/', $p)) === basename(str_replace('\\', '/', $alvo)) || $p === $alvo) {
            $removido = $p;
            continue;
        }
        $restantes[] = $p;
    }

    db_exec('UPDATE comentarios SET caminho_anexo = ? WHERE id = ?',
        array(implode(';', $restantes), $comentarioId));

    if ($removido !== null) {
        $lista = anexos_lista($removido);
        if ($lista) {
            $abs  = realpath(TAREFAS_DIR . '/' . $lista[0]['rel']);
            $base = realpath(TAREFAS_DIR_ARQUIVOS);
            if ($abs && $base && strpos($abs, $base) === 0 && is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    responder_ok(array('mensagem' => 'Anexo excluído.'));

/* ================================================================== */
/* Ofício                                                             */
/* ================================================================== */
case 'vincular_oficio':

    $token  = entrada('taskToken', entrada('token', '', $_POST), $_POST);
    $numero = entrada('numeroOficio', entrada('numero', '', $_POST), $_POST);

    if ($token === '' || $numero === '') {
        responder_erro('Informe o número do ofício.');
    }

    $t = db_one('SELECT id, numero_oficio FROM tarefas WHERE token = ? LIMIT 1', array($token));
    if (!$t) {
        responder_erro('Tarefa não encontrada.', 404);
    }

    // Confere se o ofício existe, quando a tabela estiver disponível.
    if (db_tem_tabela('oficios')) {
        $existe = db_one('SELECT numero FROM oficios WHERE numero = ? LIMIT 1', array($numero));
        if (!$existe) {
            responder_erro('Ofício nº ' . $numero . ' não encontrado no módulo de Ofícios.', 404);
        }
    }

    db_exec('UPDATE tarefas SET numero_oficio = ? WHERE token = ?', array($numero, $token));
    registrar_historico((int) $t['id'], 'oficio', 'Ofício vinculado', $t['numero_oficio'], $numero);

    responder_ok(array('mensagem' => 'Ofício vinculado com sucesso.', 'numero' => $numero));

/* ================================================================== */
/* Checklist                                                          */
/* ================================================================== */
case 'checklist_add':

    if (!db_tem_tabela('tarefas_checklist')) {
        responder_erro('Execute a migração para habilitar o checklist.');
    }

    $tarefaId  = entrada_int('tarefa_id', 0, $_POST);
    $descricao = entrada('descricao', '', $_POST);

    exigir_tarefa($tarefaId);
    if ($descricao === '') {
        responder_erro('Descreva o item do checklist.');
    }

    $ordem = (int) db_valor(
        'SELECT COALESCE(MAX(ordem), -1) + 1 FROM tarefas_checklist WHERE tarefa_id = ?',
        array($tarefaId), 0
    );

    db_exec(
        'INSERT INTO tarefas_checklist (tarefa_id, descricao, ordem, origem, criado_por, criado_em)
         VALUES (?, ?, ?, ?, ?, NOW())',
        array($tarefaId, mb_substr($descricao, 0, 300), $ordem, 'manual', $u['usuario'])
    );

    responder_ok(array('id' => (int) db()->lastInsertId(), 'ordem' => $ordem));

case 'checklist_marcar':

    if (!db_tem_tabela('tarefas_checklist')) {
        responder_erro('Checklist indisponível.');
    }

    $itemId    = entrada_int('id', 0, $_POST);
    $concluido = entrada('concluido', '0', $_POST) === '1';

    db_exec(
        'UPDATE tarefas_checklist
            SET concluido = ?, concluido_por = ?, concluido_em = ?
          WHERE id = ?',
        array($concluido ? 1 : 0, $concluido ? $u['usuario'] : null,
              $concluido ? date('Y-m-d H:i:s') : null, $itemId)
    );

    responder_ok(array('concluido' => $concluido));

case 'checklist_excluir':

    if (!db_tem_tabela('tarefas_checklist')) {
        responder_erro('Checklist indisponível.');
    }
    db_exec('DELETE FROM tarefas_checklist WHERE id = ?', array(entrada_int('id', 0, $_POST)));
    responder_ok(array('mensagem' => 'Item removido.'));

/* ================================================================== */
default:
    responder_erro('Ação não reconhecida: ' . $acao, 400);
}
