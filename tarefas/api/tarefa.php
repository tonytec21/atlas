<?php
/**
 * Atlas · Tarefas — detalhe completo de uma tarefa.
 *
 * Substitui e amplia o antigo view_task.php, mantendo o mesmo contrato de
 * saída (as chaves originais continuam presentes) e acrescentando anexos
 * normalizados, subtarefas, tarefa principal, checklist e histórico.
 *
 * GET token=... ou id=...
 */

require_once __DIR__ . '/../core/bootstrap.php';
api_iniciar();

$token = entrada('token');
$id    = entrada_int('id');

if ($token === '' && $id <= 0) {
    responder_erro('Informe o token ou o número da tarefa.');
}

$colOpcionais = '';
foreach (array('tags', 'apresentante', 'ia_resumo', 'ia_resumo_em', 'ia_risco', 'tempo_estimado', 'ordem_kanban') as $c) {
    if (db_tem_coluna('tarefas', $c)) {
        $colOpcionais .= ", t.`$c`";
    }
}

$sql = "SELECT t.id, t.token, t.titulo, t.descricao, t.status, t.nivel_de_prioridade,
               t.data_limite, t.data_criacao, t.data_conclusao, t.data_atualizacao,
               t.funcionario_responsavel, t.revisor, t.criado_por, t.atualizado_por,
               t.categoria, t.origem, t.caminho_anexo, t.numero_oficio,
               t.sub_categoria, t.id_tarefa_principal
               $colOpcionais,
               c.titulo AS categoria_titulo,
               o.titulo AS origem_titulo
          FROM tarefas t
          LEFT JOIN categorias c ON t.categoria = c.id
          LEFT JOIN origem o ON t.origem = o.id
         WHERE " . ($token !== '' ? 't.token = ?' : 't.id = ?') . '
         LIMIT 1';

try {
    $tarefa = db_one($sql, array($token !== '' ? $token : $id));
} catch (Exception $e) {
    error_log('[tarefas] api/tarefa: ' . $e->getMessage());
    responder_erro('Não foi possível consultar a tarefa.', 500);
}

if (!$tarefa) {
    responder_erro('Tarefa não encontrada.', 404);
}

$tarefaId = (int) $tarefa['id'];
$token    = (string) $tarefa['token'];

/* ---------------- Controle de acesso ----------------------------- */
if (!usuario_ve_tudo()) {
    $u = usuario_atual();
    $meu = ($tarefa['funcionario_responsavel'] === $u['nome'])
        || ($tarefa['revisor'] === $u['nome'])
        || ($tarefa['criado_por'] === $u['usuario'])
        || ($tarefa['status'] === 'Concluída');
    if (!$meu) {
        responder_erro('Você não tem permissão para visualizar esta tarefa.', 403);
    }
}

/* ---------------- Situação e anexos ------------------------------ */
$sit = situacao_prazo($tarefa['data_limite'], $tarefa['status']);
$tarefa['situacao']        = $sit['codigo'];
$tarefa['situacao_rotulo'] = $sit['rotulo'];
$tarefa['dias_restantes']  = $sit['dias'] === null ? null : round($sit['dias'], 1);
$tarefa['status_cor']      = cor_status($tarefa['status']);
$tarefa['data_limite_br']  = data_br($tarefa['data_limite']);
$tarefa['data_criacao_br'] = data_br($tarefa['data_criacao']);
$tarefa['data_conclusao_br'] = data_br($tarefa['data_conclusao']);
$tarefa['data_atualizacao_br'] = data_br($tarefa['data_atualizacao']);
$tarefa['e_subtarefa']     = ((string) $tarefa['sub_categoria'] === 'Sim');

$anexos = anexos_lista($tarefa['caminho_anexo']);
foreach ($anexos as &$a) {
    $a['tamanho_br'] = tamanho_humano($a['tamanho']);
}
unset($a);
$tarefa['anexos'] = $anexos;

/* ---------------- Comentários ------------------------------------ */
$comentarios = array();
try {
    $comentarios = db_all(
        'SELECT * FROM comentarios
          WHERE hash_tarefa = ? OR id_tarefa_principal = ?
          ORDER BY data_comentario ASC, id ASC',
        array($token, $tarefaId)
    );
} catch (Exception $e) {
    error_log('[tarefas] comentarios: ' . $e->getMessage());
}

foreach ($comentarios as &$c) {
    // Mantém a marca usada pelo módulo antigo para diferenciar a origem.
    $c['is_subtask'] = isset($c['id_tarefa_principal'])
        && (int) $c['id_tarefa_principal'] === $tarefaId
        && (string) $c['hash_tarefa'] !== $token;
    $c['data_br']        = data_br(isset($c['data_comentario']) ? $c['data_comentario'] : '');
    $c['atualizado_br']  = data_br(isset($c['data_atualizacao']) ? $c['data_atualizacao'] : '');
    $c['anexos']         = anexos_lista(isset($c['caminho_anexo']) ? $c['caminho_anexo'] : '');
    foreach ($c['anexos'] as &$a) {
        $a['tamanho_br'] = tamanho_humano($a['tamanho']);
    }
    unset($a);
}
unset($c);
$tarefa['comentarios'] = $comentarios;

/* ---------------- Subtarefas e tarefa principal ------------------ */
$tarefa['subtarefas'] = array();
try {
    $subs = db_all(
        "SELECT id, token, titulo, funcionario_responsavel, data_criacao, data_limite, status,
                nivel_de_prioridade
           FROM tarefas
          WHERE id_tarefa_principal = ? AND sub_categoria = 'Sim'
          ORDER BY id ASC",
        array($tarefaId)
    );
    foreach ($subs as &$s) {
        $s['data_limite_br']  = data_br($s['data_limite']);
        $s['data_criacao_br'] = data_br($s['data_criacao']);
        $s['status_cor']      = cor_status($s['status']);
        $ss = situacao_prazo($s['data_limite'], $s['status']);
        $s['situacao'] = $ss['codigo'];
    }
    unset($s);
    $tarefa['subtarefas'] = $subs;
} catch (Exception $e) {
    error_log('[tarefas] subtarefas: ' . $e->getMessage());
}

$tarefa['tarefa_principal'] = null;
if (!empty($tarefa['id_tarefa_principal'])) {
    try {
        $tp = db_one(
            'SELECT id, token, titulo, funcionario_responsavel, data_criacao, data_limite, status
               FROM tarefas WHERE id = ? LIMIT 1',
            array((int) $tarefa['id_tarefa_principal'])
        );
        if ($tp) {
            $tp['data_limite_br']  = data_br($tp['data_limite']);
            $tp['data_criacao_br'] = data_br($tp['data_criacao']);
            $tp['status_cor']      = cor_status($tp['status']);
            $tarefa['tarefa_principal'] = $tp;
        }
    } catch (Exception $e) {
        error_log('[tarefas] tarefa principal: ' . $e->getMessage());
    }
}

/* ---------------- Checklist -------------------------------------- */
$tarefa['checklist'] = array();
if (db_tem_tabela('tarefas_checklist')) {
    try {
        $tarefa['checklist'] = db_all(
            'SELECT id, descricao, concluido, ordem, origem, concluido_por, concluido_em
               FROM tarefas_checklist WHERE tarefa_id = ? ORDER BY ordem ASC, id ASC',
            array($tarefaId)
        );
        foreach ($tarefa['checklist'] as &$i) {
            $i['concluido'] = (int) $i['concluido'] === 1;
        }
        unset($i);
    } catch (Exception $e) {
        error_log('[tarefas] checklist: ' . $e->getMessage());
    }
}

/* ---------------- Histórico -------------------------------------- */
$tarefa['historico'] = array();
if (db_tem_tabela('tarefas_historico')) {
    try {
        $h = db_all(
            'SELECT acao, descricao, valor_anterior, valor_novo, usuario, criado_em
               FROM tarefas_historico WHERE tarefa_id = ?
              ORDER BY criado_em DESC, id DESC LIMIT 100',
            array($tarefaId)
        );
        foreach ($h as &$l) {
            $l['data_br'] = data_br($l['criado_em']);
        }
        unset($l);
        $tarefa['historico'] = $h;
    } catch (Exception $e) {
        error_log('[tarefas] historico: ' . $e->getMessage());
    }
}

/* ---------------- Documentos emitidos ---------------------------- */
$tarefa['recibo_gerado'] = false;
$tarefa['guia_gerada']   = false;

if (db_tem_tabela('recibos_de_entrega')) {
    try {
        $tarefa['recibo_gerado'] = (int) db_valor(
            'SELECT COUNT(*) FROM recibos_de_entrega WHERE task_id = ?', array($tarefaId), 0
        ) > 0;
    } catch (Exception $e) {
        // segue sem o dado
    }
}
if (db_tem_tabela('guia_de_recebimento')) {
    try {
        $tarefa['guia_gerada'] = (int) db_valor(
            'SELECT COUNT(*) FROM guia_de_recebimento WHERE task_id = ?', array($tarefaId), 0
        ) > 0;
    } catch (Exception $e) {
        // segue sem o dado
    }
}

/* ---------------- Permissões da tela ----------------------------- */
$u = usuario_atual();
$tarefa['permissoes'] = array(
    'editar'  => usuario_ve_tudo() || $tarefa['funcionario_responsavel'] === $u['nome']
                 || $tarefa['criado_por'] === $u['usuario'],
    'excluir' => usuario_ve_tudo(),
    'status'  => true,
);

responder_ok(array('tarefa' => $tarefa));
