<?php
/* =============================================================================
   ATLAS - MODULO DE OFICIOS
   busca_sugestoes.php - Autocomplete da barra de busca
   -----------------------------------------------------------------------------
   GET: termo (minimo 2 caracteres)
   Retorna sugestoes agrupadas por campo (numero, assunto, destinatario,
   assinante) com a contagem de ocorrencias e a consulta pronta para aplicar.
============================================================================= */

include(__DIR__ . '/session_check.php');
checkSession();

require_once __DIR__ . '/busca_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $termo = isset($_GET['termo']) ? trim((string)$_GET['termo']) : '';

    // Se o usuario ja esta digitando um operador (ex.: "dest:pref"),
    // sugere somente dentro daquele campo.
    if (preg_match('~([a-zA-Z_]+):\s*([^:]*)$~u', $termo, $m)) {
        $termoBusca = trim($m[2]);
    } else {
        $termoBusca = $termo;
    }

    $sugestoes = ofb_sugestoes($termoBusca, 6);

    echo json_encode([
        'ok'        => true,
        'termo'     => $termoBusca,
        'sugestoes' => $sugestoes,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'ok'        => false,
        'sugestoes' => [],
        'erro'      => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
