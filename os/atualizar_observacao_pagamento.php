<?php
/**
 * atualizar_observacao_pagamento.php
 * Permite incluir/alterar a observação de um pagamento já lançado na O.S.
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');
require_once __DIR__ . '/pagamento_observacao_config.php';
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método inválido']);
    exit;
}

if (!isset($conn)) {
    echo json_encode(['error' => 'Erro ao conectar ao banco de dados']);
    exit;
}

$pagamento_id = isset($_POST['pagamento_id']) ? (int)$_POST['pagamento_id'] : 0;
$observacao   = po_obs_normalizar($_POST['observacao'] ?? '');

if ($pagamento_id <= 0) {
    echo json_encode(['error' => 'Pagamento inválido']);
    exit;
}

try {
    if (!po_obs_garantir_coluna($conn)) {
        echo json_encode(['error' => 'Não foi possível criar a coluna de observação na tabela pagamento_os.']);
        exit;
    }

    $obsParam = ($observacao === '') ? null : $observacao;

    $stmt = $conn->prepare("UPDATE pagamento_os SET observacao = ? WHERE id = ?");
    $stmt->bind_param("si", $obsParam, $pagamento_id);
    $stmt->execute();

    echo json_encode([
        'success'    => true,
        'observacao' => $observacao
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao salvar observação: ' . $e->getMessage()]);
}
