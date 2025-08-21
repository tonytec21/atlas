<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

try {
    $dini = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : null;
    $dfim = isset($_GET['data_final'])   ? $_GET['data_final']   : null;
    $func = isset($_GET['funcionario'])  ? $_GET['funcionario']  : 'todos';

    if (!$dini || !$dfim) {
        echo json_encode(['error' => 'Parâmetros "data_inicial" e "data_final" são obrigatórios.']);
        exit;
    }

    $conn = getDatabaseConnection();

    // WHERE por funcionário
    $W = function($alias) use ($func) {
        return ($func && $func !== 'todos') ? " AND {$alias}.funcionario = :func " : " ";
    };
    $bindBase = [':dini'=>$dini, ':dfim'=>$dfim];
    if ($func && $func !== 'todos') $bindBase[':func'] = $func;

    // ATOS LIQUIDADOS
    $sql = 'SELECT os.id AS ordem_servico_id, os.cliente, al.ato, al.descricao, al.quantidade_liquidada, al.total, al.funcionario, al.data
            FROM atos_liquidados al
            JOIN ordens_de_servico os ON al.ordem_servico_id = os.id
            WHERE DATE(al.data) BETWEEN :dini AND :dfim' . $W('al');
    $st = $conn->prepare($sql);
    $st->execute($bindBase);
    $atos = $st->fetchAll(PDO::FETCH_ASSOC);

    // ATOS MANUAIS
    $sql = 'SELECT os.id AS ordem_servico_id, os.cliente, aml.ato, aml.descricao, aml.quantidade_liquidada, aml.total, aml.funcionario, aml.data
            FROM atos_manuais_liquidados aml
            JOIN ordens_de_servico os ON aml.ordem_servico_id = os.id
            WHERE DATE(aml.data) BETWEEN :dini AND :dfim' . $W('aml');
    $st = $conn->prepare($sql);
    $st->execute($bindBase);
    $atos_manuais = $st->fetchAll(PDO::FETCH_ASSOC);

    // PAGAMENTOS
    $sql = 'SELECT os.id AS ordem_de_servico_id, os.cliente, po.forma_de_pagamento, po.total_pagamento, po.funcionario, po.data_pagamento
            FROM pagamento_os po
            JOIN ordens_de_servico os ON po.ordem_de_servico_id = os.id
            WHERE DATE(po.data_pagamento) BETWEEN :dini AND :dfim' . $W('po');
    $st = $conn->prepare($sql);
    $st->execute($bindBase);
    $pagamentos = $st->fetchAll(PDO::FETCH_ASSOC);

    // DEVOLUÇÕES
    $sql = 'SELECT os.id AS ordem_de_servico_id, os.cliente, do.forma_devolucao, do.total_devolucao, do.funcionario, do.data_devolucao
            FROM devolucao_os do
            JOIN ordens_de_servico os ON do.ordem_de_servico_id = os.id
            WHERE DATE(do.data_devolucao) BETWEEN :dini AND :dfim' . $W('do');
    $st = $conn->prepare($sql);
    $st->execute($bindBase);
    $devolucoes = $st->fetchAll(PDO::FETCH_ASSOC);

    // SAÍDAS/Despesas (status ativo)
    $sql = 'SELECT sd.titulo, sd.valor_saida, sd.forma_de_saida, sd.funcionario, sd.data, sd.data_caixa, sd.caminho_anexo
            FROM saidas_despesas sd
            WHERE sd.status="ativo" AND DATE(sd.data) BETWEEN :dini AND :dfim' . $W('sd');
    $st = $conn->prepare($sql);
    $st->execute($bindBase);
    $saidas = $st->fetchAll(PDO::FETCH_ASSOC);

    // DEPÓSITOS (status ativo)
    $sql = 'SELECT funcionario, data_caixa, data_cadastro, valor_do_deposito, tipo_deposito, caminho_anexo
            FROM deposito_caixa
            WHERE status="ativo" AND DATE(data_caixa) BETWEEN :dini AND :dfim';
    if ($func && $func !== 'todos') { $sql .= ' AND funcionario = :func'; }
    $st = $conn->prepare($sql);
    $st->execute($bindBase);
    $depositos = $st->fetchAll(PDO::FETCH_ASSOC);

    // Saldo Transportado (listagem informativa - todos os status)
    $sql = 'SELECT data_caixa, data_transporte, valor_transportado, funcionario, status
            FROM transporte_saldo_caixa
            WHERE DATE(data_caixa) BETWEEN :dini AND :dfim';
    if ($func && $func !== 'todos') { $sql .= ' AND funcionario = :func'; }
    $st = $conn->prepare($sql);
    $st->execute($bindBase);
    $saldoTransportado = $st->fetchAll(PDO::FETCH_ASSOC);

    // Totais utilitários
    $sum = function($arr, $key){
        $t = 0.0;
        foreach ($arr as $row) $t += (float)$row[$key];
        return $t;
    };

    $totalAtos = $sum($atos, 'total');
    $totalAtosManuais = $sum($atos_manuais, 'total');

    // Pagamentos: totais por tipo
    $totalRecebidoConta = 0.0; $totalRecebidoEspecie = 0.0;
    foreach ($pagamentos as $p) {
        $v = (float)$p['total_pagamento'];
        $fp = $p['forma_de_pagamento'];
        if (in_array($fp, ['PIX','Centrais Eletrônicas','Boleto','Transferência Bancária','Crédito','Débito'], true)) $totalRecebidoConta += $v;
        elseif ($fp === 'Espécie') $totalRecebidoEspecie += $v;
    }

    $totalDevolucoes = $sum($devolucoes, 'total_devolucao');

    $totalSaidasDespesas = $sum($saidas, 'valor_saida');
    $totalDepositoCaixa  = $sum($depositos, 'valor_do_deposito');

    // Saldo Inicial do PERÍODO (1º dia): saldo_inicial + saldo transportado "usado" (status != 'aberto') no 1º dia
    if ($func && $func !== 'todos') {
        $st = $conn->prepare('SELECT SUM(saldo_inicial) FROM caixa WHERE DATE(data_caixa) = :dini AND funcionario = :func');
        $st->execute([':dini'=>$dini, ':func'=>$func]);
    } else {
        $st = $conn->prepare('SELECT SUM(saldo_inicial) FROM caixa WHERE DATE(data_caixa) = :dini');
        $st->execute([':dini'=>$dini]);
    }
    $saldoInicialCaixa = (float)($st->fetchColumn() ?: 0);

    if ($func && $func !== 'todos') {
        $st = $conn->prepare("SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE DATE(data_caixa) = :dini AND funcionario = :func AND status <> 'aberto'");
        $st->execute([':dini'=>$dini, ':func'=>$func]);
    } else {
        $st = $conn->prepare("SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE DATE(data_caixa) = :dini AND status <> 'aberto'");
        $st->execute([':dini'=>$dini]);
    }
    $saldoTransUsadoPrimeiroDia = (float)($st->fetchColumn() ?: 0);

    $saldoInicial = $saldoInicialCaixa + $saldoTransUsadoPrimeiroDia;

    // Saldo transportado "em aberto" (no intervalo)
    if ($func && $func !== 'todos') {
        $st = $conn->prepare("SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE status = 'aberto' AND DATE(data_caixa) BETWEEN :dini AND :dfim AND funcionario = :func");
        $st->execute([':dini'=>$dini, ':dfim'=>$dfim, ':func'=>$func]);
    } else {
        $st = $conn->prepare("SELECT SUM(valor_transportado) FROM transporte_saldo_caixa WHERE status = 'aberto' AND DATE(data_caixa) BETWEEN :dini AND :dfim");
        $st->execute([':dini'=>$dini, ':dfim'=>$dfim]);
    }
    $totalSaldoTransportadoAberto = (float)($st->fetchColumn() ?: 0);

    // Total em Caixa (Período) conforme regra
    $totalEmCaixa = $saldoInicial + $totalRecebidoEspecie - $totalDevolucoes - $totalSaidasDespesas - $totalDepositoCaixa - $totalSaldoTransportadoAberto;

    echo json_encode([
        'atos' => $atos,
        'atosManuais' => $atos_manuais,
        'pagamentos' => $pagamentos,
        'devolucoes' => $devolucoes,
        'saidas' => $saidas,
        'depositos' => $depositos,
        'saldoTransportado' => $saldoTransportado, // listagem completa (todos status)

        'saldoInicial' => $saldoInicial,
        'totalAtos' => $totalAtos,
        'totalAtosManuais' => $totalAtosManuais,
        'totalRecebidoConta' => $totalRecebidoConta,
        'totalRecebidoEspecie' => $totalRecebidoEspecie,
        'totalDevolucoes' => $totalDevolucoes,
        'totalSaidasDespesas' => $totalSaidasDespesas,
        'totalDepositoCaixa' => $totalDepositoCaixa,
        'totalSaldoTransportado' => $totalSaldoTransportadoAberto, // apenas "em aberto"
        'totalEmCaixa' => $totalEmCaixa
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
