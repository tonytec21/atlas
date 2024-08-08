<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

try {
    $funcionarios = $_GET['funcionarios'];
    $data = $_GET['data'];
    $tipo = $_GET['tipo'];

    $conn = getDatabaseConnection();

    if ($tipo === 'unificado') {
        // Atos Liquidados
        $sql = 'SELECT os.id as ordem_servico_id, os.cliente, al.ato, al.descricao, al.quantidade_liquidada, al.total, al.funcionario, al.data
                FROM atos_liquidados al
                JOIN ordens_de_servico os ON al.ordem_servico_id = os.id
                WHERE DATE(al.data) = :data';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $atos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pagamentos
        $sql = 'SELECT os.id as ordem_de_servico_id, os.cliente, po.forma_de_pagamento, po.total_pagamento, po.funcionario, po.data_pagamento
                FROM pagamento_os po
                JOIN ordens_de_servico os ON po.ordem_de_servico_id = os.id
                WHERE DATE(po.data_pagamento) = :data';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Devoluções
        $sql = 'SELECT os.id as ordem_de_servico_id, os.cliente, do.forma_devolucao, do.total_devolucao, do.funcionario, do.data_devolucao
                FROM devolucao_os do
                JOIN ordens_de_servico os ON do.ordem_de_servico_id = os.id
                WHERE DATE(do.data_devolucao) = :data';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $devolucoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Saídas e Despesas
        $sql = 'SELECT sd.titulo, sd.valor_saida, sd.forma_de_saida, sd.funcionario, sd.data, sd.data_caixa, sd.caminho_anexo
                FROM saidas_despesas sd
                WHERE DATE(sd.data) = :data AND sd.status = "ativo"';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $saidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Depósitos
        $sql = 'SELECT funcionario, data_caixa, data_cadastro, valor_do_deposito, tipo_deposito, caminho_anexo
                FROM deposito_caixa
                WHERE DATE(data_caixa) = :data AND status = "ativo"';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $depositos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Saldo Transportado
        $sql = 'SELECT data_caixa, data_transporte, valor_transportado, funcionario, status
                FROM transporte_saldo_caixa
                WHERE DATE(data_caixa) = :data';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $saldoTransportado = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Atos Liquidados
        $sql = 'SELECT os.id as ordem_servico_id, os.cliente, al.ato, al.descricao, al.quantidade_liquidada, al.total, al.funcionario, al.data
                FROM atos_liquidados al
                JOIN ordens_de_servico os ON al.ordem_servico_id = os.id
                WHERE al.funcionario = :funcionario AND DATE(al.data) = :data';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':funcionario', $funcionarios);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $atos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pagamentos
        $sql = 'SELECT os.id as ordem_de_servico_id, os.cliente, po.forma_de_pagamento, po.total_pagamento, po.funcionario, po.data_pagamento
                FROM pagamento_os po
                JOIN ordens_de_servico os ON po.ordem_de_servico_id = os.id
                WHERE po.funcionario = :funcionario AND DATE(po.data_pagamento) = :data';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':funcionario', $funcionarios);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Devoluções
        $sql = 'SELECT os.id as ordem_de_servico_id, os.cliente, do.forma_devolucao, do.total_devolucao, do.funcionario, do.data_devolucao
                FROM devolucao_os do
                JOIN ordens_de_servico os ON do.ordem_de_servico_id = os.id
                WHERE do.funcionario = :funcionario AND DATE(do.data_devolucao) = :data';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':funcionario', $funcionarios);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $devolucoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Saídas e Despesas
        $sql = 'SELECT sd.titulo, sd.valor_saida, sd.forma_de_saida, sd.funcionario, sd.data, sd.data_caixa, sd.caminho_anexo
                FROM saidas_despesas sd
                WHERE sd.funcionario = :funcionario AND DATE(sd.data) = :data AND sd.status = "ativo"';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':funcionario', $funcionarios);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $saidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Depósitos
        $sql = 'SELECT funcionario, data_caixa, data_cadastro, valor_do_deposito, tipo_deposito, caminho_anexo
                FROM deposito_caixa
                WHERE funcionario = :funcionario AND DATE(data_caixa) = :data AND status = "ativo"';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':funcionario', $funcionarios);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $depositos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Saldo Transportado
        $sql = 'SELECT data_caixa, data_transporte, valor_transportado, funcionario, status
                FROM transporte_saldo_caixa
                WHERE DATE(data_caixa) = :data AND funcionario = :funcionario';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->bindParam(':funcionario', $funcionarios);
        $stmt->execute();
        $saldoTransportado = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $totalAtos = array_reduce($atos, function($carry, $item) {
        return $carry + floatval($item['total']);
    }, 0.0);

    $totalRecebidoConta = array_reduce($pagamentos, function($carry, $item) {
        if (in_array($item['forma_de_pagamento'], ['PIX', 'Transferência Bancária', 'Crédito', 'Débito'])) {
            return $carry + floatval($item['total_pagamento']);
        }
        return $carry;
    }, 0.0);

    $totalRecebidoEspecie = array_reduce($pagamentos, function($carry, $item) {
        if ($item['forma_de_pagamento'] === 'Espécie') {
            return $carry + floatval($item['total_pagamento']);
        }
        return $carry;
    }, 0.0);

    $totalDevolucoes = array_reduce($devolucoes, function($carry, $item) {
        return $carry + floatval($item['total_devolucao']);
    }, 0.0);

    $totalDevolvidoEspecie = array_reduce($devolucoes, function($carry, $item) {
        if ($item['forma_devolucao'] === 'Espécie') {
            return $carry + floatval($item['total_devolucao']);
        }
        return $carry;
    }, 0.0);

    $totalSaidasDespesas = array_reduce($saidas, function($carry, $item) {
        return $carry + floatval($item['valor_saida']);
    }, 0.0);

    $totalDepositoCaixa = array_reduce($depositos, function($carry, $item) {
        return $carry + floatval($item['valor_do_deposito']);
    }, 0.0);

    $totalSaldoTransportado = array_reduce($saldoTransportado, function($carry, $item) {
        return $carry + floatval($item['valor_transportado']);
    }, 0.0);

    $stmt = $conn->prepare('SELECT saldo_inicial FROM caixa WHERE DATE(data_caixa) = :data AND funcionario = :funcionario');
    $stmt->bindParam(':data', $data);
    $stmt->bindParam(':funcionario', $funcionarios);
    $stmt->execute();
    $caixa = $stmt->fetch(PDO::FETCH_ASSOC);
    $saldoInicial = $caixa ? floatval($caixa['saldo_inicial']) : 0.0;

    // Debugging logs
    error_log("Saldo Inicial: " . $saldoInicial);
    error_log("Total Recebido em Espécie: " . $totalRecebidoEspecie);
    error_log("Total Devolvido em Espécie: " . $totalDevolvidoEspecie);
    error_log("Total Saídas e Despesas: " . $totalSaidasDespesas);
    error_log("Total Depósito do Caixa: " . $totalDepositoCaixa);
    error_log("Total Saldo Transportado: " . $totalSaldoTransportado);

    $totalEmCaixa = $saldoInicial + $totalRecebidoEspecie - $totalDevolvidoEspecie - $totalSaidasDespesas - $totalDepositoCaixa - $totalSaldoTransportado;

    echo json_encode([
        'atos' => $atos,
        'pagamentos' => $pagamentos,
        'devolucoes' => $devolucoes,
        'saidas' => $saidas,
        'depositos' => $depositos,
        'saldoTransportado' => $saldoTransportado,
        'totalAtos' => $totalAtos,
        'totalRecebidoConta' => $totalRecebidoConta,
        'totalRecebidoEspecie' => $totalRecebidoEspecie,
        'totalDevolucoes' => $totalDevolucoes,
        'totalEmCaixa' => $totalEmCaixa,
        'totalSaidasDespesas' => $totalSaidasDespesas,
        'totalDepositoCaixa' => $totalDepositoCaixa,
        'saldoInicial' => $saldoInicial,
        'totalSaldoTransportado' => $totalSaldoTransportado
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
