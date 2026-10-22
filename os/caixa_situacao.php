<?php
// Situação do caixa de hoje do usuário logado — usada pela janela de pagamentos da O.S.
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');
require_once __DIR__ . '/caixa_pagamento_lib.php';
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');
try {
    echo json_encode(os_caixa_situacao($conn, (string) $_SESSION['username']), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok' => true, 'motivo' => 'indisponivel', 'mensagem' => ''], JSON_UNESCAPED_UNICODE);
}
