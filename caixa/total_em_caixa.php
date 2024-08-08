<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

try {
    $funcionario = $_GET['funcionario'];
    $data_caixa = $_GET['data_caixa'];

    $conn = getDatabaseConnection();

    // Saldo Inicial
    $stmt = $conn->prepare('SELECT saldo_inicial FROM caixa WHERE DATE(data_caixa) = :data_caixa');
    $stmt->bindParam(':data_caixa', $data_caixa);
    $stmt->execute();
    $saldoInicial = $stmt->fetchColumn();

    // Atos Liquidados
    $sql = 'SELECT total FROM atos_liquidados WHERE funcionario = :funcionario AND DATE(data) = :data';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->bindParam(':data', $data_caixa);
    $stmt->execute();
    $totalAtos = array_reduce($stmt->fetchAll(PDO::FETCH_ASSOC), function($carry, $item) {
        return $carry + $item['total'];
    }, 0);

    // Pagamentos
    $sql = 'SELECT total_pagamento, forma_de_pagamento FROM pagamento_os WHERE funcionario = :funcionario AND DATE(data_pagamento) = :data';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->bindParam(':data', $data_caixa);
    $stmt->execute();
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRecebidoConta = 0;
    $totalRecebidoEspecie = 0;
    foreach ($pagamentos as $pagamento) {
        if (in_array($pagamento['forma_de_pagamento'], ['PIX', 'Transferência Bancária', 'Crédito', 'Débito'])) {
            $totalRecebidoConta += $pagamento['total_pagamento'];
        } else if ($pagamento['forma_de_pagamento'] === 'Espécie') {
            $totalRecebidoEspecie += $pagamento['total_pagamento'];
        }
    }

    // Devoluções
    $sql = 'SELECT total_devolucao, forma_devolucao FROM devolucao_os WHERE funcionario = :funcionario AND DATE(data_devolucao) = :data';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->bindParam(':data', $data_caixa);
    $stmt->execute();
    $devolucoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalDevolvidoEspecie = 0;
    foreach ($devolucoes as $devolucao) {
        if ($devolucao['forma_devolucao'] === 'Espécie') {
            $totalDevolvidoEspecie += $devolucao['total_devolucao'];
        }
    }

    // Saídas e Despesas
    $sql = 'SELECT valor_saida FROM saidas_despesas WHERE funcionario = :funcionario AND DATE(data) = :data AND status = "ativo"';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->bindParam(':data', $data_caixa);
    $stmt->execute();
    $totalSaidasDespesas = array_reduce($stmt->fetchAll(PDO::FETCH_ASSOC), function($carry, $item) {
        return $carry + $item['valor_saida'];
    }, 0);

    // Depósitos
    $sql = 'SELECT valor_do_deposito FROM deposito_caixa WHERE funcionario = :funcionario AND DATE(data_caixa) = :data AND status = "ativo"';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->bindParam(':data', $data_caixa);
    $stmt->execute();
    $totalDepositoCaixa = array_reduce($stmt->fetchAll(PDO::FETCH_ASSOC), function($carry, $item) {
        return $carry + $item['valor_do_deposito'];
    }, 0);

    $totalEmCaixa = $saldoInicial + $totalRecebidoEspecie - $totalDevolvidoEspecie - $totalSaidasDespesas - $totalDepositoCaixa;

    echo json_encode(['totalEmCaixa' => $totalEmCaixa]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>