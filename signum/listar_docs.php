<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/config_assinatura.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $f = [
        'q'      => $_GET['q'] ?? '',
        'metodo' => $_GET['metodo'] ?? '',
        'de'     => preg_match('~^\d{4}-\d{2}-\d{2}$~', $_GET['de'] ?? '') ? $_GET['de'] : '',
        'ate'    => preg_match('~^\d{4}-\d{2}-\d{2}$~', $_GET['ate'] ?? '') ? $_GET['ate'] : '',
        'page'   => (int)($_GET['page'] ?? 1),
        'per'    => (int)($_GET['per'] ?? 20),
    ];
    $r = asg_listar_filtrado($f);
    $rows = array_map(function ($d) {
        return [
            'id'     => (int)$d['id'],
            'nome'   => $d['nome_original'],
            'titular'=> $d['titular'] ?: '',
            'metodo' => strtoupper($d['metodo'] ?? ''),
            'data'   => date('d/m/Y H:i', strtotime($d['assinado_em'])),
            'codigo' => $d['codigo'],
            'tam'    => asg_human($d['tamanho']),
        ];
    }, $r['rows']);
    echo json_encode(['status' => 'success', 'rows' => $rows, 'total' => $r['total'],
                      'page' => $r['page'], 'pages' => $r['pages'], 'per' => $r['per']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
