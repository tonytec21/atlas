<?php
/* =============================================================================
   ATLAS - MODULO DE OFICIOS
   exportar_oficios.php - Exporta o resultado da busca em CSV (Excel/pt-BR)
   -----------------------------------------------------------------------------
   Usa exatamente os mesmos filtros do endpoint buscar_oficios.php, porem sem
   paginacao (limite de seguranca configuravel).
============================================================================= */

include(__DIR__ . '/session_check.php');
checkSession();

require_once __DIR__ . '/busca_helper.php';

const OFB_EXPORT_LIMITE = 5000;

try {
    $p = ofb_normalizar_params($_GET);

    // Exportacao sempre traz o conjunto completo do filtro
    $p['pagina']     = 1;
    $p['por_pagina'] = OFB_EXPORT_LIMITE;

    // Forca o modo "com filtro" para nao cair no limite de 20 da tela inicial
    if (!ofb_tem_filtro($p)) {
        $p['data_ini'] = '1900-01-01';
    }

    $resultado = ofb_buscar($p, OFB_EXPORT_LIMITE);

    if (empty($resultado['ok'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Falha ao gerar a exportacao.';
        exit;
    }

    $comAnexo = array_flip(ofb_numeros_com_anexo());

    $nomeArquivo = 'oficios_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $saida = fopen('php://output', 'w');

    // BOM para o Excel reconhecer UTF-8
    fwrite($saida, "\xEF\xBB\xBF");

    $cabecalho = [
        'Numero', 'Data', 'Assunto', 'Destinatario', 'Cargo do destinatario',
        'Tratamento', 'Assinante', 'Cargo do assinante', 'Dados complementares',
        'Assinado', 'Assinado por', 'Assinado em', 'Edicao travada', 'Possui anexo',
    ];
    fputcsv($saida, $cabecalho, ';');

    foreach ($resultado['linhas'] as $row) {
        $numero  = (string)($row['numero'] ?? '');
        $dataIso = (string)($row['data'] ?? '');
        $dataBr  = '';
        if ($dataIso !== '' && $dataIso !== '0000-00-00') {
            $ts = strtotime($dataIso);
            if ($ts) $dataBr = date('d/m/Y', $ts);
        }

        $assinadoEm = (string)($row['assinado_em'] ?? '');
        if ($assinadoEm !== '' && $assinadoEm !== '0000-00-00 00:00:00') {
            $ts = strtotime($assinadoEm);
            if ($ts) $assinadoEm = date('d/m/Y H:i', $ts);
        } else {
            $assinadoEm = '';
        }

        fputcsv($saida, [
            $numero,
            $dataBr,
            (string)($row['assunto'] ?? ''),
            (string)($row['destinatario'] ?? ''),
            (string)($row['cargo'] ?? ''),
            (string)($row['tratamento'] ?? ''),
            (string)($row['assinante'] ?? ''),
            (string)($row['cargo_assinante'] ?? ''),
            // Remove quebras de linha para nao estourar as celulas
            trim(preg_replace('~\s+~u', ' ', strip_tags((string)($row['dados_complementares'] ?? '')))),
            !empty($row['assinado']) ? 'Sim' : 'Nao',
            (string)($row['assinado_por'] ?? ''),
            $assinadoEm,
            ((int)($row['status'] ?? 0) === 1) ? 'Sim' : 'Nao',
            isset($comAnexo[$numero]) ? 'Sim' : 'Nao',
        ], ';');
    }

    fclose($saida);

} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Erro ao exportar: ' . $e->getMessage();
}
