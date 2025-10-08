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

        // Atos Manuais Liquidados
        $sql = 'SELECT os.id as ordem_servico_id, os.cliente, aml.ato, aml.descricao, aml.quantidade_liquidada, aml.total, aml.funcionario, aml.data
                FROM atos_manuais_liquidados aml
                JOIN ordens_de_servico os ON aml.ordem_servico_id = os.id
                WHERE DATE(aml.data) = :data';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $atos_manuais = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        // SELOS (Unificado): mapear r.usuario (nome completo) -> f.usuario por join com funcionarios.nome_completo
        $sql = "
            SELECT 
                f.usuario        AS funcionario,
                r.numero_selo    AS numero_selo,
                r.ato            AS ato,
                r.tipo           AS tipo,
                r.selagem        AS selagem,
                r.emolumentos    AS emolumentos,
                r.ferj           AS ferj,
                r.fadep          AS fadep,
                r.ferc           AS ferc,
                r.femp           AS femp,  
                r.total          AS total
            FROM relatorios_analiticos r
            INNER JOIN funcionarios f 
                    ON TRIM(LOWER(f.nome_completo)) = TRIM(LOWER(r.usuario))
            WHERE DATE(r.selagem) = :data
            AND r.cancelado = 0
            AND r.isento    = 0
            AND r.diferido  = 0
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $selos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // NOVO: ATOS ISENTOS (Unificado) — mesmo join, mas com isento = 1
        $sql = "
            SELECT 
                f.usuario        AS funcionario,
                r.numero_selo    AS numero_selo,
                r.ato            AS ato,
                r.tipo           AS tipo,
                r.selagem        AS selagem,
                r.emolumentos    AS emolumentos,
                r.ferj           AS ferj,
                r.fadep          AS fadep,
                r.ferc           AS ferc,
                r.femp           AS femp,  
                r.total          AS total
            FROM relatorios_analiticos r
            INNER JOIN funcionarios f 
                    ON TRIM(LOWER(f.nome_completo)) = TRIM(LOWER(r.usuario))
            WHERE DATE(r.selagem) = :data
            AND r.cancelado = 0
            AND r.isento    = 1
            AND r.diferido  = 0
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $atos_isentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        // Atos Manuais Liquidados
        $sql = 'SELECT os.id as ordem_servico_id, os.cliente, aml.ato, aml.descricao, aml.quantidade_liquidada, aml.total, aml.funcionario, aml.data
                FROM atos_manuais_liquidados aml
                JOIN ordens_de_servico os ON aml.ordem_servico_id = os.id
                WHERE aml.funcionario = :funcionario AND DATE(aml.data) = :data';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':funcionario', $funcionarios);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        $atos_manuais = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        // SELOS (Individual): join pelo nome completo -> filtra pelo f.usuario = :funcionario
        $sql = "
            SELECT 
                f.usuario        AS funcionario,
                r.numero_selo    AS numero_selo,
                r.ato            AS ato,
                r.tipo           AS tipo,
                r.selagem        AS selagem,
                r.emolumentos    AS emolumentos,
                r.ferj           AS ferj,
                r.fadep          AS fadep,
                r.ferc           AS ferc,
                r.femp           AS femp,                                
                r.total          AS total
            FROM relatorios_analiticos r
            INNER JOIN funcionarios f 
                    ON TRIM(LOWER(f.nome_completo)) = TRIM(LOWER(r.usuario))
            WHERE DATE(r.selagem) = :data
            AND f.usuario = :funcionario
            AND r.cancelado = 0
            AND r.isento    = 0
            AND r.diferido  = 0
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->bindParam(':funcionario', $funcionarios);
        $stmt->execute();
        $selos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // NOVO: ATOS ISENTOS (Individual) — mesmo join, filtrando o funcionário, com isento = 1
        $sql = "
            SELECT 
                f.usuario        AS funcionario,
                r.numero_selo    AS numero_selo,
                r.ato            AS ato,
                r.tipo           AS tipo,
                r.selagem        AS selagem,
                r.emolumentos    AS emolumentos,
                r.ferj           AS ferj,
                r.fadep          AS fadep,
                r.ferc           AS ferc,
                r.femp           AS femp,                                
                r.total          AS total
            FROM relatorios_analiticos r
            INNER JOIN funcionarios f 
                    ON TRIM(LOWER(f.nome_completo)) = TRIM(LOWER(r.usuario))
            WHERE DATE(r.selagem) = :data
            AND f.usuario = :funcionario
            AND r.cancelado = 0
            AND r.isento    = 1
            AND r.diferido  = 0
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':data', $data);
        $stmt->bindParam(':funcionario', $funcionarios);
        $stmt->execute();
        $atos_isentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $totalAtos = array_reduce($atos, function($carry, $item) {
        return $carry + floatval($item['total']);
    }, 0.0);

    // Total de Atos Manuais Liquidados
    $totalAtosManuais = array_reduce($atos_manuais, function($carry, $item) {
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

    // Total em Selos (somando selo_valor)
    $totalSelos = array_reduce(isset($selos) ? $selos : [], function($carry, $item) {
        return $carry + floatval($item['total']);
    }, 0.0);

    // NOVO: Total em Atos Isentos (somente amostragem — não entra em total recebido)
    $totalAtosIsentos = array_reduce(isset($atos_isentos) ? $atos_isentos : [], function($carry, $item) {
        return $carry + floatval($item['total']);
    }, 0.0);


    if ($tipo === 'unificado') {
        // Para o caixa unificado, somamos os saldos iniciais de todos os caixas na data especificada
        $stmt = $conn->prepare('SELECT SUM(saldo_inicial) as saldo_inicial FROM caixa WHERE DATE(data_caixa) = :data');
        $stmt->bindParam(':data', $data);
    } else {
        // Para o caixa individual, pegamos o saldo inicial específico do funcionário
        $stmt = $conn->prepare('SELECT saldo_inicial FROM caixa WHERE DATE(data_caixa) = :data AND funcionario = :funcionario');
        $stmt->bindParam(':data', $data);
        $stmt->bindParam(':funcionario', $funcionarios);
    }    
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
    error_log("Total em Selos: " . $totalSelos);
    error_log("Total Atos Isentos: " . $totalAtosIsentos);

    $totalEmCaixa = $saldoInicial + $totalRecebidoEspecie - $totalDevolvidoEspecie - $totalSaidasDespesas - $totalDepositoCaixa - $totalSaldoTransportado;

    echo json_encode([
        'atos' => $atos,
        'atosManuais' => $atos_manuais,
        'pagamentos' => $pagamentos,
        'devolucoes' => $devolucoes,
        'saidas' => $saidas,
        'depositos' => $depositos,
        'saldoTransportado' => $saldoTransportado,
        'selos' => isset($selos) ? $selos : [],

        // NOVO: retornar também a lista dos Atos Isentos (opcional para uso futuro no front)
        'atosIsentos' => isset($atos_isentos) ? $atos_isentos : [],

        'totalAtos' => $totalAtos,
        'totalAtosManuais' => $totalAtosManuais,
        'totalRecebidoConta' => $totalRecebidoConta,
        'totalRecebidoEspecie' => $totalRecebidoEspecie,
        'totalDevolucoes' => $totalDevolucoes,
        'totalEmCaixa' => $totalEmCaixa,
        'totalSaidasDespesas' => $totalSaidasDespesas,
        'totalDepositoCaixa' => $totalDepositoCaixa,
        'saldoInicial' => $saldoInicial,
        'totalSaldoTransportado' => $totalSaldoTransportado,
        'totalSelos' => $totalSelos,

        // NOVO: total dos Atos Isentos (usado no card do front)
        'totalAtosIsentos' => isset($totalAtosIsentos) ? $totalAtosIsentos : 0.0
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
