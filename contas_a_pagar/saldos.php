<?php
require_once __DIR__ . '/session_check.php'; checkSession();
require_once __DIR__ . '/guard_acesso.php'; cap_guard('json');
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $s = cap_saldos(); $out = [];
    foreach ($s as $cod => $v) $out[$cod] = $v + ['saldo_fmt'=>cap_money($v['saldo']), 'nome'=>cap_nome_conta($cod)];
    echo json_encode(['success'=>true,'saldos'=>$out,'caixa_ok'=>cap_tem_deposito_caixa()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE); }
