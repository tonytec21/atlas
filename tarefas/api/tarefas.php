<?php
/**
 * Atlas · Tarefas — API de consulta.
 *
 * Endpoint único que alimenta todas as visões da tela (cards, kanban,
 * calendário, tabela) e também os indicadores do painel.
 *
 * Diferenças relevantes em relação ao search_tasks.php antigo:
 *   · consultas preparadas (o original concatenava os filtros na SQL);
 *   · ordenação e paginação feitas pelo banco, não no navegador;
 *   · uma única consulta traz a contagem de comentários e anexos, em vez de
 *     uma consulta por tarefa dentro do laço;
 *   · resposta já traz o rótulo pronto de status, prioridade e situação.
 *
 * Parâmetros aceitos (todos opcionais):
 *   protocol, title, category, employee, revisor, status, description,
 *   priority, origin, dateStart, dateEnd, tags, situacao, texto,
 *   visao (cards|kanban|calendario|tabela), ordenar, direcao,
 *   pagina, por_pagina, start, end (calendário), incluir_encerradas
 */

require_once __DIR__ . '/../core/bootstrap.php';
api_iniciar();

$u = usuario_atual();

/* ---------------------- Leitura dos filtros ---------------------- */
$f = array(
    'protocolo'   => entrada('protocol', entrada('protocolo')),
    'titulo'      => entrada('title', entrada('titulo')),
    'categoria'   => entrada('category', entrada('categoria')),
    'funcionario' => entrada('employee', entrada('funcionario')),
    'revisor'     => entrada('revisor'),
    'status'      => entrada('status'),
    'descricao'   => entrada('description', entrada('descricao')),
    'prioridade'  => entrada('priority', entrada('prioridade')),
    'origem'      => entrada('origin', entrada('origem')),
    'inicio'      => entrada('dateStart', entrada('inicio')),
    'fim'         => entrada('dateEnd', entrada('fim')),
    'tags'        => entrada('tags'),
    'situacao'    => entrada('situacao'),
    'texto'       => entrada('texto'),
    'oficio'      => entrada('oficio'),
    'apresentante' => entrada('apresentante'),
);

$visao   = entrada('visao', 'cards');
$ordenar = entrada('ordenar', 'protocolo');
$direcao = strtolower(entrada('direcao', 'desc')) === 'asc' ? 'ASC' : 'DESC';
$pagina  = max(1, entrada_int('pagina', 1));
$porPagina = entrada_int('por_pagina', 24);
$porPagina = max(6, min(200, $porPagina));

$temFiltro = false;
foreach ($f as $v) {
    if (is_string($v) && trim($v) !== '') {
        $temFiltro = true;
        break;
    }
}

/* ---------------------- Montagem da consulta --------------------- */
$where  = array('1=1');
$params = array();

/*
 * Controle de acesso — mesma regra do módulo original.
 * Exceção preservada: qualquer usuário pode localizar UMA tarefa pelo número
 * do protocolo geral, para acompanhar o andamento. Como é igualdade exata de
 * ID, não expõe a carteira de terceiros.
 */
$buscaPorProtocolo = trim((string) $f['protocolo']) !== '';

if (!usuario_ve_tudo() && !$buscaPorProtocolo) {
    $where[] = "(t.status = 'Concluída' OR t.funcionario_responsavel = ? OR t.revisor = ? OR t.criado_por = ?)";
    $params[] = $u['nome'];
    $params[] = $u['nome'];
    $params[] = $u['usuario'];
}

if ($buscaPorProtocolo) {
    $where[] = 't.id = ?';
    $params[] = (int) $f['protocolo'];
}
if ($f['titulo'] !== '') {
    $where[] = 't.titulo LIKE ?';
    $params[] = '%' . $f['titulo'] . '%';
}
if ($f['categoria'] !== '') {
    $where[] = 't.categoria = ?';
    $params[] = $f['categoria'];
}
if ($f['origem'] !== '') {
    $where[] = 't.origem = ?';
    $params[] = $f['origem'];
}
if ($f['funcionario'] !== '') {
    $where[] = 't.funcionario_responsavel LIKE ?';
    $params[] = '%' . $f['funcionario'] . '%';
}
if ($f['revisor'] !== '') {
    $where[] = 't.revisor LIKE ?';
    $params[] = '%' . $f['revisor'] . '%';
}
if ($f['descricao'] !== '') {
    $where[] = 't.descricao LIKE ?';
    $params[] = '%' . $f['descricao'] . '%';
}
if ($f['prioridade'] !== '') {
    $where[] = 't.nivel_de_prioridade = ?';
    $params[] = $f['prioridade'];
}
if ($f['oficio'] !== '') {
    $where[] = 't.numero_oficio LIKE ?';
    $params[] = '%' . $f['oficio'] . '%';
}
if ($f['apresentante'] !== '' && db_tem_coluna('tarefas', 'apresentante')) {
    $where[] = 't.apresentante LIKE ?';
    $params[] = '%' . $f['apresentante'] . '%';
}
if ($f['tags'] !== '' && db_tem_coluna('tarefas', 'tags')) {
    $where[] = 't.tags LIKE ?';
    $params[] = '%' . $f['tags'] . '%';
}

/* Busca livre: varre os campos textuais mais úteis de uma vez. */
if ($f['texto'] !== '') {
    $termo = '%' . $f['texto'] . '%';
    $bloco = '(t.titulo LIKE ? OR t.descricao LIKE ? OR t.funcionario_responsavel LIKE ?'
           . ' OR t.numero_oficio LIKE ? OR CAST(t.id AS CHAR) = ?';
    $params[] = $termo;
    $params[] = $termo;
    $params[] = $termo;
    $params[] = $termo;
    $params[] = trim($f['texto']);
    if (db_tem_coluna('tarefas', 'apresentante')) {
        $bloco .= ' OR t.apresentante LIKE ?';
        $params[] = $termo;
    }
    if (db_tem_coluna('tarefas', 'tags')) {
        $bloco .= ' OR t.tags LIKE ?';
        $params[] = $termo;
    }
    $where[] = $bloco . ')';
}

/* Status: aceita vários separados por vírgula. */
if ($f['status'] !== '') {
    $lista = array_filter(array_map('trim', explode(',', $f['status'])));
    if ($lista) {
        $where[] = 't.status IN (' . implode(',', array_fill(0, count($lista), '?')) . ')';
        foreach ($lista as $s) {
            $params[] = $s;
        }
    }
} elseif (!$temFiltro && !in_array($visao, array('calendario', 'kanban'), true)
          && entrada('incluir_encerradas') !== '1') {
    /*
     * Carregamento inicial sem filtro: mostra apenas a fila de trabalho,
     * exatamente como o módulo antigo fazia. Kanban e calendário precisam
     * das colunas encerradas, então ficam de fora desse corte.
     */
    $enc = tarefas_status_encerrados();
    $where[] = 't.status NOT IN (' . implode(',', array_fill(0, count($enc), '?')) . ')';
    foreach ($enc as $s) {
        $params[] = $s;
    }
}

/* Intervalo da data limite. */
if ($f['inicio'] !== '' && $f['fim'] !== '') {
    $where[] = 'DATE(t.data_limite) BETWEEN ? AND ?';
    $params[] = $f['inicio'];
    $params[] = $f['fim'];
} elseif ($f['inicio'] !== '') {
    $where[] = 'DATE(t.data_limite) >= ?';
    $params[] = $f['inicio'];
} elseif ($f['fim'] !== '') {
    $where[] = 'DATE(t.data_limite) <= ?';
    $params[] = $f['fim'];
}

/* Janela visível do calendário. */
if ($visao === 'calendario') {
    $ini = substr(entrada('start'), 0, 10);
    $fim = substr(entrada('end'), 0, 10);
    if ($ini !== '' && $fim !== '') {
        $where[] = 'DATE(t.data_limite) BETWEEN ? AND ?';
        $params[] = $ini;
        $params[] = $fim;
    }
}

/* Situação do prazo — calculada em SQL para funcionar com paginação. */
switch ($f['situacao']) {
    case 'vencida':
        $where[] = "t.data_limite < NOW() AND t.status NOT IN ('Concluída','Cancelada','Finalizado sem prática do ato','Aguardando Retirada')";
        break;
    case 'hoje':
        $where[] = "DATE(t.data_limite) = CURDATE() AND t.status NOT IN ('Concluída','Cancelada','Finalizado sem prática do ato','Aguardando Retirada')";
        break;
    case 'semana':
        $where[] = "t.data_limite BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND t.status NOT IN ('Concluída','Cancelada','Finalizado sem prática do ato','Aguardando Retirada')";
        break;
    case 'minhas':
        $where[] = '(t.funcionario_responsavel = ? OR t.revisor = ?)';
        $params[] = $u['nome'];
        $params[] = $u['nome'];
        break;
    case 'sem_responsavel':
        $where[] = "(t.funcionario_responsavel IS NULL OR t.funcionario_responsavel = '')";
        break;
}

$sqlWhere = implode(' AND ', $where);

/* ---------------------- Ordenação -------------------------------- */
$mapaOrdem = array(
    'protocolo'  => 't.id',
    'data'       => 't.data_limite',
    'criacao'    => 't.data_criacao',
    'funcionario' => 't.funcionario_responsavel',
    'titulo'     => 't.titulo',
    'status'     => 't.status',
    'prioridade' => "FIELD(t.nivel_de_prioridade,'Baixa','Média','Alta','Crítica')",
    'kanban'     => db_tem_coluna('tarefas', 'ordem_kanban') ? 't.ordem_kanban' : 't.id',
);
$colunaOrdem = isset($mapaOrdem[$ordenar]) ? $mapaOrdem[$ordenar] : 't.id';

/* ---------------------- Total ------------------------------------ */
$total = (int) db_valor(
    "SELECT COUNT(*) FROM tarefas t WHERE $sqlWhere",
    $params,
    0
);

/* ---------------------- Consulta principal ----------------------- */
$colOpcionais = '';
foreach (array('tags', 'apresentante', 'ia_resumo', 'ia_risco', 'ordem_kanban') as $c) {
    if (db_tem_coluna('tarefas', $c)) {
        $colOpcionais .= ", t.`$c`";
    }
}

$limite = '';
if ($visao !== 'calendario') {
    $offset = ($pagina - 1) * $porPagina;
    $limite = ' LIMIT ' . (int) $porPagina . ' OFFSET ' . (int) $offset;
} else {
    $limite = ' LIMIT 2000';
}

$sql = "SELECT t.id, t.token, t.titulo, t.descricao, t.status, t.nivel_de_prioridade,
               t.data_limite, t.data_criacao, t.data_conclusao, t.data_atualizacao,
               t.funcionario_responsavel, t.revisor, t.criado_por, t.atualizado_por,
               t.categoria, t.origem, t.caminho_anexo, t.numero_oficio,
               t.sub_categoria, t.id_tarefa_principal
               $colOpcionais,
               c.titulo AS categoria_titulo,
               o.titulo AS origem_titulo,
               (SELECT COUNT(*) FROM comentarios cm
                 WHERE cm.hash_tarefa = t.token OR cm.id_tarefa_principal = t.id) AS total_comentarios,
               (SELECT COUNT(*) FROM tarefas s
                 WHERE s.id_tarefa_principal = t.id AND s.sub_categoria = 'Sim') AS total_subtarefas
          FROM tarefas t
          LEFT JOIN categorias c ON t.categoria = c.id
          LEFT JOIN origem o ON t.origem = o.id
         WHERE $sqlWhere
         ORDER BY $colunaOrdem $direcao, t.id DESC
         $limite";

try {
    $linhas = db_all($sql, $params);
} catch (Exception $e) {
    error_log('[tarefas] api/tarefas: ' . $e->getMessage());
    responder_erro('Não foi possível consultar as tarefas.', 500);
}

/* ---------------------- Enriquecimento --------------------------- */
$tarefas = array();
foreach ($linhas as $t) {
    $sit = situacao_prazo($t['data_limite'], $t['status']);
    $anexos = anexos_lista($t['caminho_anexo']);

    $t['situacao']        = $sit['codigo'];
    $t['situacao_rotulo'] = $sit['rotulo'];
    $t['dias_restantes']  = $sit['dias'] === null ? null : round($sit['dias'], 1);
    $t['status_cor']      = cor_status($t['status']);
    $t['status_slug']     = slug($t['status']);
    $t['prioridade_slug'] = slug($t['nivel_de_prioridade']);
    $t['prioridade_peso'] = peso_prioridade($t['nivel_de_prioridade']);
    $t['data_limite_br']  = data_br($t['data_limite']);
    $t['data_criacao_br'] = data_br($t['data_criacao']);
    $t['data_conclusao_br'] = data_br($t['data_conclusao']);
    $t['total_anexos']    = count($anexos);
    $t['e_subtarefa']     = ((string) $t['sub_categoria'] === 'Sim');
    $t['total_comentarios'] = (int) $t['total_comentarios'];
    $t['total_subtarefas']  = (int) $t['total_subtarefas'];

    // O resumo completo dos anexos só vai no detalhe, para não pesar a lista.
    unset($t['caminho_anexo']);

    $tarefas[] = $t;
}

/* ---------------------- Formato calendário ----------------------- */
if ($visao === 'calendario') {
    $eventos = array();
    foreach ($tarefas as $t) {
        if (empty($t['data_limite'])) {
            continue;
        }
        $ts = strtotime((string) $t['data_limite']);
        if ($ts === false) {
            continue;
        }
        $eventos[] = array(
            'id'     => (string) $t['id'],
            'titulo' => $t['titulo'],
            'inicio' => date('Y-m-d\TH:i:s', $ts),
            'data'   => date('Y-m-d', $ts),
            'hora'   => date('H:i', $ts),
            'token'  => $t['token'],
            'status' => $t['status'],
            'status_slug' => $t['status_slug'],
            'cor'    => $t['status_cor'],
            'situacao' => $t['situacao'],
            'prioridade' => $t['nivel_de_prioridade'],
            'funcionario' => $t['funcionario_responsavel'],
            'categoria' => $t['categoria_titulo'],
        );
    }
    responder_ok(array('eventos' => $eventos, 'total' => count($eventos)));
}

/* ---------------------- Formato kanban --------------------------- */
if ($visao === 'kanban') {
    $catalogo = tarefas_status_catalogo();
    $colunas  = array();
    foreach ($catalogo as $nome => $meta) {
        if (empty($meta['kanban'])) {
            continue;
        }
        $colunas[$nome] = array(
            'status'  => $nome,
            'cor'     => $meta['cor'],
            'slug'    => slug($nome),
            'tarefas' => array(),
        );
    }
    // Coluna extra para status fora do catálogo (dados históricos).
    foreach ($tarefas as $t) {
        $s = (string) $t['status'];
        if (!isset($colunas[$s])) {
            $colunas[$s] = array('status' => $s, 'cor' => cor_status($s), 'slug' => slug($s), 'tarefas' => array());
        }
        $colunas[$s]['tarefas'][] = $t;
    }
    responder_ok(array('colunas' => array_values($colunas), 'total' => $total));
}

/* ---------------------- Resposta padrão -------------------------- */
responder_ok(array(
    'tarefas'     => $tarefas,
    'total'       => $total,
    'pagina'      => $pagina,
    'por_pagina'  => $porPagina,
    'paginas'     => (int) ceil($total / max(1, $porPagina)),
    'tem_filtro'  => $temFiltro,
));
