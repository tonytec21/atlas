<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');
date_default_timezone_set('America/Sao_Paulo');

// IMPORTANTE: não definir Content-Type: application/json aqui — o jQuery passaria
// a auto-parsear a resposta e a verificação manual de JSON no cliente falharia.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método de solicitação inválido.']);
    exit;
}

try {
    if (!isset($conn)) {
        throw new Exception('Erro ao conectar ao banco de dados.');
    }

    $os_id         = intval($_POST['os_id'] ?? 0);
    $cliente       = trim((string)($_POST['cliente'] ?? ''));
    $forma_repasse = trim((string)($_POST['forma_repasse'] ?? ''));
    $data_os       = trim((string)($_POST['data_os'] ?? ''));
    $funcionario   = trim((string)($_POST['funcionario'] ?? ($_SESSION['username'] ?? 'sistema')));
    // O cliente envia o valor já como número (ponto decimal)
    $total_repasse = (float)($_POST['total_repasse'] ?? 0);

    if ($os_id <= 0)                 throw new Exception('O.S. inválida.');
    if ($forma_repasse === '')       throw new Exception('Selecione a forma de repasse.');
    if ($total_repasse <= 0)         throw new Exception('Informe um valor de repasse válido.');

    // ===== Recalcula o saldo REAL da O.S. no servidor (fonte de verdade) =====
    // total_os (autoritativo, da própria O.S.)
    $stOs = $conn->prepare("SELECT total_os FROM ordens_de_servico WHERE id = ? LIMIT 1");
    $stOs->bind_param("i", $os_id);
    $stOs->execute();
    $resOs = $stOs->get_result();
    if (!$resOs || $resOs->num_rows === 0) throw new Exception('Ordem de Serviço não encontrada.');
    $total_os_val = (float)$resOs->fetch_assoc()['total_os'];

    // somatórios
    $getSum = function($sql, $id) use ($conn) {
        $st = $conn->prepare($sql);
        $st->bind_param("i", $id);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        return (float)($r['s'] ?? 0);
    };
    $total_pag = $getSum("SELECT COALESCE(SUM(total_pagamento),0) s FROM pagamento_os WHERE ordem_de_servico_id = ?", $os_id);
    $total_dev = $getSum("SELECT COALESCE(SUM(total_devolucao),0) s FROM devolucao_os WHERE ordem_de_servico_id = ?", $os_id);
    $total_rep = $getSum("SELECT COALESCE(SUM(total_repasse),0) s FROM repasse_credor WHERE ordem_de_servico_id = ?", $os_id);

    $saldo_disponivel = ($total_pag - $total_dev) - $total_os_val - $total_rep;

    if ($total_repasse > $saldo_disponivel + 0.01) {
        throw new Exception('O valor do repasse (R$ ' . number_format($total_repasse, 2, ',', '.') .
            ') excede o saldo disponível da O.S. (R$ ' . number_format(max(0, $saldo_disponivel), 2, ',', '.') . ').');
    }

    $status       = 'ativo';
    $data_repasse = date('Y-m-d H:i:s');

    $inTx = false;
    $conn->begin_transaction();
    $inTx = true;
    $repasse_query = $conn->prepare("INSERT INTO repasse_credor (ordem_de_servico_id, cliente, total_os, total_repasse, forma_repasse, data_repasse, data_os, funcionario, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $repasse_query->bind_param("issdsssss", $os_id, $cliente, $total_os_val, $total_repasse, $forma_repasse, $data_repasse, $data_os, $funcionario, $status);
    if (!$repasse_query->execute()) {
        throw new Exception('Erro ao salvar repasse no banco de dados.');
    }
    $conn->commit();
    $inTx = false;

    echo json_encode(['success' => true, 'total_repasse' => $total_repasse, 'saldo_restante' => ($saldo_disponivel - $total_repasse)]);
} catch (Throwable $e) {
    if (!empty($inTx)) { @$conn->rollback(); }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
