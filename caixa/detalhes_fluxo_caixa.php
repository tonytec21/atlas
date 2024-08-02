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

    $conditions = 'DATE(data) = :data';
    $params = [':data' => $data];

    if ($tipo === 'individual') {
        $conditions .= ' AND funcionario = :funcionario';
        $params[':funcionario'] = $funcionarios;
    }

    // Atos Liquidados
    $sql = 'SELECT os.id as ordem_servico_id, os.cliente, al.ato, al.descricao, al.quantidade_liquidada, al.total
            FROM atos_liquidados al
            JOIN ordens_de_servico os ON al.ordem_servico_id = os.id
            WHERE al.funcionario = :funcionario AND DATE(al.data) = :data';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':funcionario', $funcionarios);
    $stmt->bindParam(':data', $data);
    $stmt->execute();
    $atos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pagamentos
    $sql = 'SELECT os.id as ordem_de_servico_id, os.cliente, po.forma_de_pagamento, po.total_pagamento
            FROM pagamento_os po
            JOIN ordens_de_servico os ON po.ordem_de_servico_id = os.id
            WHERE po.funcionario = :funcionario AND DATE(po.data_pagamento) = :data';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':funcionario', $funcionarios);
    $stmt->bindParam(':data', $data);
    $stmt->execute();
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Devoluções
    $sql = 'SELECT os.id as ordem_de_servico_id, os.cliente, do.forma_devolucao, do.total_devolucao
            FROM devolucao_os do
            JOIN ordens_de_servico os ON do.ordem_de_servico_id = os.id
            WHERE do.funcionario = :funcionario AND DATE(do.data_devolucao) = :data';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':funcionario', $funcionarios);
    $stmt->bindParam(':data', $data);
    $stmt->execute();
    $devolucoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Saídas e Despesas
    $sql = 'SELECT sd.titulo, sd.valor_saida, sd.forma_de_saida
            FROM saidas_despesas sd
            WHERE sd.funcionario = :funcionario AND DATE(sd.data) = :data';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':funcionario', $funcionarios);
    $stmt->bindParam(':data', $data);
    $stmt->execute();
    $saidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalAtos = array_reduce($atos, function($carry, $item) {
        return $carry + $item['total'];
    }, 0);

    $totalRecebidoConta = array_reduce($pagamentos, function($carry, $item) {
        if (in_array($item['forma_de_pagamento'], ['PIX', 'Transferência Bancária', 'Crédito', 'Débito'])) {
            return $carry + $item['total_pagamento'];
        }
        return $carry;
    }, 0);

    $totalRecebidoEspecie = array_reduce($pagamentos, function($carry, $item) {
        if ($item['forma_de_pagamento'] === 'Espécie') {
            return $carry + $item['total_pagamento'];
        }
        return $carry;
    }, 0);

    $totalDevolucoes = array_reduce($devolucoes, function($carry, $item) {
        return $carry + $item['total_devolucao'];
    }, 0);

    $totalDevolvidoEspecie = array_reduce($devolucoes, function($carry, $item) {
        if ($item['forma_devolucao'] === 'Espécie') {
            return $carry + $item['total_devolucao'];
        }
        return $carry;
    }, 0);

    $totalSaidasDespesas = array_reduce($saidas, function($carry, $item) {
        return $carry + $item['valor_saida'];
    }, 0);

    $totalEmCaixa = $totalRecebidoEspecie - $totalDevolvidoEspecie - $totalSaidasDespesas;

    echo json_encode([
        'atos' => $atos,
        'pagamentos' => $pagamentos,
        'devolucoes' => $devolucoes,
        'saidas' => $saidas,
        'totalAtos' => $totalAtos,
        'totalRecebidoConta' => $totalRecebidoConta,
        'totalRecebidoEspecie' => $totalRecebidoEspecie,
        'totalDevolucoes' => $totalDevolucoes,
        'totalEmCaixa' => $totalEmCaixa,
        'totalSaidasDespesas' => $totalSaidasDespesas
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
