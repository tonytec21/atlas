<?php
/** API · Listagem filtrada do acervo. */
require_once __DIR__ . '/../bootstrap.php';
arq_exige_login();

$filtros = [
    'q'          => isset($_GET['q']) ? (string) $_GET['q'] : '',
    'atribuicao' => isset($_GET['atribuicao']) ? (string) $_GET['atribuicao'] : '',
    'categoria'  => isset($_GET['categoria']) ? (string) $_GET['categoria'] : '',
    'cpf'        => isset($_GET['cpf']) ? (string) $_GET['cpf'] : '',
    'nome'       => isset($_GET['nome']) ? (string) $_GET['nome'] : '',
    'livro'      => isset($_GET['livro']) ? (string) $_GET['livro'] : '',
    'folha'      => isset($_GET['folha']) ? (string) $_GET['folha'] : '',
    'termo'      => isset($_GET['termo']) ? (string) $_GET['termo'] : '',
    'protocolo'  => isset($_GET['protocolo']) ? (string) $_GET['protocolo'] : '',
    'matricula'  => isset($_GET['matricula']) ? (string) $_GET['matricula'] : '',
    'descricao'  => isset($_GET['descricao']) ? (string) $_GET['descricao'] : '',
    'data'       => isset($_GET['data']) ? (string) $_GET['data'] : '',
    'de'         => isset($_GET['de']) ? (string) $_GET['de'] : '',
    'ate'        => isset($_GET['ate']) ? (string) $_GET['ate'] : '',
    'com_anexo'  => isset($_GET['com_anexo']) ? (string) $_GET['com_anexo'] : '',
    'ordenar'    => isset($_GET['ordenar']) ? (string) $_GET['ordenar'] : 'data_ato',
    'direcao'    => isset($_GET['direcao']) ? (string) $_GET['direcao'] : 'desc',
];

$todos = arq_filtrar($filtros);

$pagina    = max(1, (int) (isset($_GET['pagina']) ? $_GET['pagina'] : 1));
$porPagina = (int) (isset($_GET['por_pagina']) ? $_GET['por_pagina'] : 24);
$porPagina = max(6, min(120, $porPagina));

$total  = count($todos);
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina  = min($pagina, $paginas);
$itens   = array_slice($todos, ($pagina - 1) * $porPagina, $porPagina);

// Selos da página atual (uma consulta só).
$selos = arq_contagem_selos(array_column($itens, 'id'));
foreach ($itens as $i => $it) {
    $itens[$i]['selos'] = isset($selos[$it['id']]) ? $selos[$it['id']] : 0;
    unset($itens[$i]['busca']); // não precisa trafegar
}

// Somatórios do resultado inteiro (não só da página).
$bytes = 0;
$anexos = 0;
foreach ($todos as $t) { $bytes += (int) $t['anexos_bytes']; $anexos += (int) $t['anexos_qtd']; }

arq_ok([
    'itens'   => $itens,
    'pagina'  => $pagina,
    'paginas' => $paginas,
    'total'   => $total,
    'resumo'  => [
        'anexos'        => $anexos,
        'bytes'         => $bytes,
        'bytes_legivel' => arq_formatar_bytes($bytes),
    ],
    'ids' => array_column($todos, 'id'),
]);
