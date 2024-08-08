<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

try {
    $funcionarios = $_GET['funcionarios'];
    $data = $_GET['data'];
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'individual';

    $conn = getDatabaseConnection();

    // Seleciona os depósitos
    $sql = 'SELECT funcionario, data_caixa, data_cadastro, valor_do_deposito, tipo_deposito, caminho_anexo
            FROM deposito_caixa
            WHERE ' . ($tipo === 'unificado' ? '' : 'funcionario = :funcionario AND ') . 'DATE(data_caixa) = :data AND status = "ativo"';
    $stmt = $conn->prepare($sql);
    if ($tipo !== 'unificado') {
        $stmt->bindParam(':funcionario', $funcionarios);
    }
    $stmt->bindParam(':data', $data);
    $stmt->execute();
    $depositos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalDepositoCaixa = array_reduce($depositos, function($carry, $item) {
        return $carry + floatval($item['valor_do_deposito']);
    }, 0.0);

    // Seleciona o saldo inicial
    $stmt = $conn->prepare('SELECT saldo_inicial FROM caixa WHERE DATE(data_caixa) = :data' . ($tipo === 'unificado' ? '' : ' AND funcionario = :funcionario'));
    if ($tipo !== 'unificado') {
        $stmt->bindParam(':funcionario', $funcionarios);
    }
    $stmt->bindParam(':data', $data);
    $stmt->execute();
    $caixa = $stmt->fetch(PDO::FETCH_ASSOC);
    $saldoInicial = $caixa ? floatval($caixa['saldo_inicial']) : 0.0;

    // Seleciona o total recebido em espécie
    $stmt = $conn->prepare('SELECT SUM(total_pagamento) as total_recebido_especie
                            FROM pagamento_os
                            WHERE forma_de_pagamento = "Espécie" AND DATE(data_pagamento) = :data' . ($tipo === 'unificado' ? '' : ' AND funcionario = :funcionario'));
    if ($tipo !== 'unificado') {
        $stmt->bindParam(':funcionario', $funcionarios);
    }
    $stmt->bindParam(':data', $data);
    $stmt->execute();
    $totalRecebidoEspecie = $stmt->fetchColumn();
    $totalRecebidoEspecie = $totalRecebidoEspecie ? floatval($totalRecebidoEspecie) : 0.0;

    // Seleciona o total devolvido em espécie
    $stmt = $conn->prepare('SELECT SUM(total_devolucao) as total_devolvido_especie
                            FROM devolucao_os
                            WHERE forma_devolucao = "Espécie" AND DATE(data_devolucao) = :data' . ($tipo === 'unificado' ? '' : ' AND funcionario = :funcionario'));
    if ($tipo !== 'unificado') {
        $stmt->bindParam(':funcionario', $funcionarios);
    }
    $stmt->bindParam(':data', $data);
    $stmt->execute();
    $totalDevolvidoEspecie = $stmt->fetchColumn();
    $totalDevolvidoEspecie = $totalDevolvidoEspecie ? floatval($totalDevolvidoEspecie) : 0.0;

    // Seleciona o total de saídas e despesas
    $stmt = $conn->prepare('SELECT SUM(valor_saida) as total_saidas_despesas
                            FROM saidas_despesas
                            WHERE DATE(data) = :data AND status = "ativo"' . ($tipo === 'unificado' ? '' : ' AND funcionario = :funcionario'));
    if ($tipo !== 'unificado') {
        $stmt->bindParam(':funcionario', $funcionarios);
    }
    $stmt->bindParam(':data', $data);
    $stmt->execute();
    $totalSaidasDespesas = $stmt->fetchColumn();
    $totalSaidasDespesas = $totalSaidasDespesas ? floatval($totalSaidasDespesas) : 0.0;

    // Seleciona o total de saldo transportado
    $stmt = $conn->prepare('SELECT SUM(valor_transportado) as total_saldo_transportado
                            FROM transporte_saldo_caixa
                            WHERE DATE(data_caixa) = :data' . ($tipo === 'unificado' ? '' : ' AND funcionario = :funcionario'));
    if ($tipo !== 'unificado') {
        $stmt->bindParam(':funcionario', $funcionarios);
    }
    $stmt->bindParam(':data', $data);
    $stmt->execute();
    $totalSaldoTransportado = $stmt->fetchColumn();
    $totalSaldoTransportado = $totalSaldoTransportado ? floatval($totalSaldoTransportado) : 0.0;

    $totalEmCaixa = $saldoInicial + $totalRecebidoEspecie - $totalDevolvidoEspecie - $totalSaidasDespesas - $totalDepositoCaixa - $totalSaldoTransportado;

    echo json_encode([
        'depositos' => $depositos,
        'saldoInicial' => $saldoInicial,
        'totalRecebidoEspecie' => $totalRecebidoEspecie,
        'totalDevolvidoEspecie' => $totalDevolvidoEspecie,
        'totalSaidasDespesas' => $totalSaidasDespesas,
        'totalDepositoCaixa' => $totalDepositoCaixa,
        'totalSaldoTransportado' => $totalSaldoTransportado,
        'totalEmCaixa' => $totalEmCaixa
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
