<?php
/* =============================================================================
   ATLAS - MODULO DE OFICIOS
   buscar_oficios.php - Endpoint JSON da busca avancada
   -----------------------------------------------------------------------------
   Parametros (GET):
     q             busca inteligente (sintaxe com operadores)
     modo          e | ou            (juncao dos termos livres)
     numero, assunto, destinatario, assinante, cargo,
     dados_complementares, corpo     (filtros avancados)
     data_ini, data_fim              (Y-m-d ou d/m/Y)
     periodo                         (hoje|7dias|30dias|mes|ano|...)
     assinado, travado, anexo        (sim|nao|vazio)
     buscar_corpo                    (1 = inclui o corpo do oficio na busca)
     ordem                           (relevancia|data|numero|assunto|...)
     dir                             (asc|desc)
     pagina, por_pagina
     facetas                         (1 = devolve tambem as facetas)
============================================================================= */

include(__DIR__ . '/session_check.php');
checkSession();

require_once __DIR__ . '/busca_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $p = ofb_normalizar_params($_GET);

    // Sem filtro nenhum: devolve os 20 mais recentes
    $resultado = ofb_buscar($p, 20);

    if (empty($resultado['ok'])) {
        echo json_encode([
            'ok'   => false,
            'erro' => 'Falha ao executar a consulta.',
            'detalhe' => $resultado['erro'] ?? '',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ---- Enriquecimento das linhas ---- */
    $comAnexo = array_flip(ofb_numeros_com_anexo());

    $linhas = [];
    foreach ($resultado['linhas'] as $row) {
        $numero = (string)($row['numero'] ?? '');
        $dataBr = '';
        $dataIso = (string)($row['data'] ?? '');
        if ($dataIso !== '' && $dataIso !== '0000-00-00') {
            $ts = strtotime($dataIso);
            if ($ts) {
                $dataBr  = date('d/m/Y', $ts);
                $dataIso = date('Y-m-d', $ts);
            }
        }

        $linhas[] = [
            'id'                   => (int)($row['id'] ?? 0),
            'numero'               => $numero,
            'data'                 => $dataIso,
            'data_br'              => $dataBr,
            'assunto'              => (string)($row['assunto'] ?? ''),
            'destinatario'         => (string)($row['destinatario'] ?? ''),
            'cargo'                => (string)($row['cargo'] ?? ''),
            'assinante'            => (string)($row['assinante'] ?? ''),
            'cargo_assinante'      => (string)($row['cargo_assinante'] ?? ''),
            'tratamento'           => (string)($row['tratamento'] ?? ''),
            'dados_complementares' => (string)($row['dados_complementares'] ?? ''),
            'status'               => (int)($row['status'] ?? 0),
            'assinado'             => !empty($row['assinado']) ? 1 : 0,
            'assinado_por'         => (string)($row['assinado_por'] ?? ''),
            'assinado_em'          => (string)($row['assinado_em'] ?? ''),
            'tem_anexo'            => isset($comAnexo[$numero]) ? 1 : 0,
            'score'                => (float)($row['_score'] ?? 0),
        ];
    }

    $saida = [
        'ok'          => true,
        'linhas'      => $linhas,
        'total'       => (int)$resultado['total'],
        'pagina'      => (int)$resultado['pagina'],
        'paginas'     => (int)$resultado['paginas'],
        'por_pagina'  => (int)$resultado['por_pagina'],
        'tem_filtro'  => (bool)$resultado['tem_filtro'],
        'tempo_ms'    => $resultado['tempo_ms'],
        'chips'       => ofb_chips_ativos($p),
        'termos'      => array_map(function ($t) { return $t['termo']; }, $p['_parsed']['livres']),
        'nao_reconhecidos' => $p['_parsed']['nao_reconhecidos'],
        'filtros'     => [
            'q'            => $p['q'],
            'modo'         => $p['modo'],
            'data_ini'     => $p['data_ini'],
            'data_fim'     => $p['data_fim'],
            'assinado'     => $p['assinado'],
            'travado'      => $p['travado'],
            'anexo'        => $p['anexo'],
            'ordem'        => $p['ordem'],
            'dir'          => $p['dir'],
            'buscar_corpo' => $p['buscar_corpo'] ? 1 : 0,
        ],
    ];

    if (!empty($_GET['facetas'])) {
        $saida['facetas'] = ofb_facetas($p, 8);
    }

    echo json_encode($saida, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'   => false,
        'erro' => 'Erro interno na busca.',
        'detalhe' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
