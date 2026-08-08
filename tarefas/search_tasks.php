<?php
/**
 * Atlas · Tarefas — compatibilidade: search_tasks.php.
 *
 * Este endpoint era consumido pela tela antiga e pode estar sendo chamado por
 * outros módulos do Atlas. Foi mantido com o MESMO contrato de saída (array
 * JSON puro de tarefas, ou array de eventos quando format=fc), mas agora a
 * consulta é preparada e sem o N+1 de comentários da versão anterior.
 *
 * Para telas novas prefira api/tarefas.php, que devolve muito mais dados.
 */

require_once __DIR__ . '/core/bootstrap.php';
api_iniciar();

$u = usuario_atual();
$isFC = strtolower(entrada('format')) === 'fc';

$protocol    = entrada('protocol');
$title       = entrada('title');
$category    = entrada('category');
$employee    = entrada('employee');
$revisor     = entrada('revisor');
$status      = entrada('status');
$description = entrada('description');
$priority    = entrada('priority');
$origin      = entrada('origin');
$dateStart   = entrada('dateStart');
$dateEnd     = entrada('dateEnd');

$where  = array('1=1');
$params = array();

/*
 * Regra de visibilidade preservada da versão original, inclusive a exceção:
 * qualquer usuário pode localizar UMA tarefa buscando pelo número exato do
 * protocolo geral, para acompanhar o andamento sem ver a carteira alheia.
 */
$buscaPorProtocolo = ($protocol !== '');

if (!usuario_ve_tudo() && !$buscaPorProtocolo) {
    $where[] = "(t.status = 'Concluída' OR t.funcionario_responsavel = ? OR t.revisor = ?)";
    $params[] = $u['nome'];
    $params[] = $u['nome'];
}

if ($buscaPorProtocolo)  { $where[] = 't.id = ?';                        $params[] = (int) $protocol; }
if ($title !== '')       { $where[] = 't.titulo LIKE ?';                 $params[] = '%' . $title . '%'; }
if ($category !== '')    { $where[] = 't.categoria = ?';                 $params[] = $category; }
if ($employee !== '')    { $where[] = 't.funcionario_responsavel LIKE ?'; $params[] = '%' . $employee . '%'; }
if ($revisor !== '')     { $where[] = 't.revisor LIKE ?';                $params[] = '%' . $revisor . '%'; }
if ($description !== '') { $where[] = 't.descricao LIKE ?';              $params[] = '%' . $description . '%'; }
if ($priority !== '')    { $where[] = 't.nivel_de_prioridade = ?';       $params[] = $priority; }
if ($origin !== '')      { $where[] = 't.origem = ?';                    $params[] = $origin; }

if ($status !== '') {
    $where[] = 't.status = ?';
    $params[] = $status;
} elseif (!$isFC && $protocol === '' && $title === '' && $category === '' && $employee === ''
          && $revisor === '' && $description === '' && $priority === '' && $origin === ''
          && $dateStart === '' && $dateEnd === '') {
    // Carregamento inicial: apenas a fila de trabalho, como na versão antiga.
    $enc = tarefas_status_encerrados();
    $where[] = 't.status NOT IN (' . implode(',', array_fill(0, count($enc), '?')) . ')';
    foreach ($enc as $s) { $params[] = $s; }
}

if ($dateStart !== '' && $dateEnd !== '') {
    $where[] = 'DATE(t.data_limite) BETWEEN ? AND ?';
    $params[] = $dateStart;
    $params[] = $dateEnd;
} elseif ($dateStart !== '') {
    $where[] = 'DATE(t.data_limite) >= ?';
    $params[] = $dateStart;
} elseif ($dateEnd !== '') {
    $where[] = 'DATE(t.data_limite) <= ?';
    $params[] = $dateEnd;
}

if ($isFC) {
    $ini = substr(entrada('start'), 0, 10);
    $fim = substr(entrada('end'), 0, 10);
    if ($ini !== '' && $fim !== '') {
        $where[] = 'DATE(t.data_limite) BETWEEN ? AND ?';
        $params[] = $ini;
        $params[] = $fim;
    }
}

$sql = 'SELECT t.*, c.titulo AS categoria_titulo, o.titulo AS origem_titulo
          FROM tarefas t
          LEFT JOIN categorias c ON t.categoria = c.id
          LEFT JOIN origem o ON t.origem = o.id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY t.id DESC
         LIMIT 3000';

try {
    $linhas = db_all($sql, $params);
} catch (Exception $e) {
    error_log('[tarefas] search_tasks: ' . $e->getMessage());
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(array());
    exit;
}

/* ---------------------- Saída para o calendário ------------------ */
if ($isFC) {
    $eventos = array();
    foreach ($linhas as $row) {
        $start = '';
        if (!empty($row['data_limite'])) {
            $ts = strtotime((string) $row['data_limite']);
            if ($ts !== false) { $start = date('Y-m-d\TH:i:s', $ts); }
        }
        $eventos[] = array(
            'id'     => (string) $row['id'],
            'title'  => $row['titulo'],
            'start'  => $start,
            'allDay' => false,
            'extendedProps' => array(
                'status'      => slug($row['status']),
                'token'       => $row['token'],
                'funcionario' => $row['funcionario_responsavel'],
                'categoria'   => $row['categoria_titulo'],
                'origem'      => $row['origem_titulo'],
            ),
        );
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode($eventos, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------------- Saída padrão ----------------------------- */
/*
 * A versão antiga fazia uma consulta de comentários por tarefa dentro do
 * laço. Aqui buscamos todos de uma vez e distribuímos em memória.
 */
$comentariosPorToken = array();
if ($linhas) {
    $tokens = array();
    foreach ($linhas as $l) {
        if (!empty($l['token'])) { $tokens[] = $l['token']; }
    }
    if ($tokens) {
        $marc = implode(',', array_fill(0, count($tokens), '?'));
        try {
            foreach (db_all("SELECT * FROM comentarios WHERE hash_tarefa IN ($marc)", $tokens) as $c) {
                $comentariosPorToken[$c['hash_tarefa']][] = $c;
            }
        } catch (Exception $e) {
            error_log('[tarefas] search_tasks comentarios: ' . $e->getMessage());
        }
    }
}

$tarefas = array();
foreach ($linhas as $row) {
    $tk = isset($row['token']) ? $row['token'] : '';
    $row['comentarios'] = isset($comentariosPorToken[$tk]) ? $comentariosPorToken[$tk] : array();
    $tarefas[] = $row;
}

while (ob_get_level() > 0) { ob_end_clean(); }
echo json_encode($tarefas, JSON_UNESCAPED_UNICODE);
