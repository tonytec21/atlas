<?php
/**
 * Atlas · Tarefas — exportação dos resultados em CSV.
 *
 * Usa exatamente os mesmos filtros da tela, respeitando a regra de
 * visibilidade do usuário. O arquivo sai com BOM UTF-8 e separador ponto e
 * vírgula, que é o que o Excel em português abre sem pedir configuração.
 */

require_once __DIR__ . '/../core/bootstrap.php';
exigir_login();

$u = usuario_atual();

$where  = array('1=1');
$params = array();

if (!usuario_ve_tudo()) {
    $where[] = "(t.status = 'Concluída' OR t.funcionario_responsavel = ? OR t.revisor = ? OR t.criado_por = ?)";
    $params[] = $u['nome'];
    $params[] = $u['nome'];
    $params[] = $u['usuario'];
}

$mapa = array(
    'protocol'    => array('t.id = ?', 'int'),
    'title'       => array('t.titulo LIKE ?', 'like'),
    'category'    => array('t.categoria = ?', 'txt'),
    'origin'      => array('t.origem = ?', 'txt'),
    'employee'    => array('t.funcionario_responsavel LIKE ?', 'like'),
    'revisor'     => array('t.revisor LIKE ?', 'like'),
    'status'      => array('t.status = ?', 'txt'),
    'priority'    => array('t.nivel_de_prioridade = ?', 'txt'),
    'description' => array('t.descricao LIKE ?', 'like'),
    'oficio'      => array('t.numero_oficio LIKE ?', 'like'),
);

foreach ($mapa as $campo => $def) {
    $v = entrada($campo);
    if ($v === '') { continue; }
    $where[] = $def[0];
    if ($def[1] === 'like') {
        $params[] = '%' . $v . '%';
    } elseif ($def[1] === 'int') {
        $params[] = (int) $v;
    } else {
        $params[] = $v;
    }
}

$texto = entrada('texto');
if ($texto !== '') {
    $where[] = '(t.titulo LIKE ? OR t.descricao LIKE ? OR t.funcionario_responsavel LIKE ?)';
    $params[] = '%' . $texto . '%';
    $params[] = '%' . $texto . '%';
    $params[] = '%' . $texto . '%';
}

$ini = entrada('dateStart');
$fim = entrada('dateEnd');
if ($ini !== '' && $fim !== '') {
    $where[] = 'DATE(t.data_limite) BETWEEN ? AND ?';
    $params[] = $ini;
    $params[] = $fim;
} elseif ($ini !== '') {
    $where[] = 'DATE(t.data_limite) >= ?';
    $params[] = $ini;
} elseif ($fim !== '') {
    $where[] = 'DATE(t.data_limite) <= ?';
    $params[] = $fim;
}

switch (entrada('situacao')) {
    case 'vencida':
        $where[] = "t.data_limite < NOW() AND t.status NOT IN ('Concluída','Cancelada','Finalizado sem prática do ato','Aguardando Retirada')";
        break;
    case 'hoje':
        $where[] = 'DATE(t.data_limite) = CURDATE()';
        break;
    case 'semana':
        $where[] = 't.data_limite BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)';
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

$ordem = entrada('ordenar', 'protocolo');
$mapaOrdem = array(
    'protocolo' => 't.id', 'data' => 't.data_limite', 'criacao' => 't.data_criacao',
    'funcionario' => 't.funcionario_responsavel', 'titulo' => 't.titulo', 'status' => 't.status',
    'prioridade' => "FIELD(t.nivel_de_prioridade,'Baixa','Média','Alta','Crítica')",
);
$colOrdem = isset($mapaOrdem[$ordem]) ? $mapaOrdem[$ordem] : 't.id';
$dir = strtolower(entrada('direcao', 'desc')) === 'asc' ? 'ASC' : 'DESC';

$sql = 'SELECT t.id, t.titulo, c.titulo AS categoria, o.titulo AS origem, t.status,
               t.nivel_de_prioridade, t.data_criacao, t.data_limite, t.data_conclusao,
               t.funcionario_responsavel, t.revisor, t.criado_por, t.numero_oficio,
               t.sub_categoria, t.id_tarefa_principal, t.descricao
          FROM tarefas t
          LEFT JOIN categorias c ON t.categoria = c.id
          LEFT JOIN origem o ON t.origem = o.id
         WHERE ' . implode(' AND ', $where) . "
         ORDER BY $colOrdem $dir
         LIMIT 20000";

try {
    $linhas = db_all($sql, $params);
} catch (Exception $e) {
    error_log('[tarefas] exportar: ' . $e->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Não foi possível gerar a exportação.';
    exit;
}

$nome = 'tarefas-' . date('Y-m-d-His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nome . '"');
header('Pragma: no-cache');

$saida = fopen('php://output', 'w');

// BOM para o Excel reconhecer o UTF-8 e não quebrar os acentos.
fwrite($saida, "\xEF\xBB\xBF");

fputcsv($saida, array(
    'Protocolo', 'Título', 'Categoria', 'Origem', 'Status', 'Prioridade',
    'Situação do prazo', 'Criada em', 'Prazo', 'Concluída em',
    'Responsável', 'Revisor', 'Criada por', 'Ofício', 'Subtarefa',
    'Tarefa principal', 'Descrição',
), ';');

foreach ($linhas as $l) {
    $sit = situacao_prazo($l['data_limite'], $l['status']);
    fputcsv($saida, array(
        $l['id'],
        $l['titulo'],
        $l['categoria'],
        $l['origem'],
        $l['status'],
        $l['nivel_de_prioridade'],
        $sit['rotulo'],
        data_br($l['data_criacao']),
        data_br($l['data_limite']),
        data_br($l['data_conclusao']),
        $l['funcionario_responsavel'],
        $l['revisor'],
        $l['criado_por'],
        $l['numero_oficio'],
        ((string) $l['sub_categoria'] === 'Sim') ? 'Sim' : 'Não',
        $l['id_tarefa_principal'],
        preg_replace('/\s+/u', ' ', (string) $l['descricao']),
    ), ';');
}

fclose($saida);
