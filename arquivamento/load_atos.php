<?php
/** Legado: lista o acervo no formato antigo (array simples de atos). */
require_once __DIR__ . '/_compat.php';
arq_exige_login();
arq_compat_avisar('load_atos.php');

$filtros = [];
foreach (['categoria','cpf','nome','livro','folha','termo','protocolo','matricula','atribuicao','q'] as $k) {
    $filtros[$k] = isset($_GET[$k]) ? (string) $_GET[$k] : '';
}

$saida = [];
foreach (arq_filtrar($filtros) as $it) {
    $ato = arq_obter($it['id']);
    if (!$ato) { continue; }
    $ato['anexos'] = array_map(function ($a) { return $a['nome']; }, $ato['anexos']);
    $saida[] = $ato;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($saida, JSON_UNESCAPED_UNICODE);
