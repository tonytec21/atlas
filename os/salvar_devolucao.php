<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($conn)) {
            throw new Exception('Erro ao conectar ao banco de dados.');
        }

        // Captura dos dados
        $os_id            = intval($_POST['os_id'] ?? 0);
        $cliente          = trim($_POST['cliente'] ?? '');
        $total_os         = floatval($_POST['total_os'] ?? 0);
        $total_devolucao  = floatval($_POST['total_devolucao'] ?? 0);
        $forma_devolucao  = trim($_POST['forma_devolucao'] ?? '');
        $funcionario      = trim($_POST['funcionario'] ?? '');
        $status           = 'Devolvido';
        $data_devolucao   = date('Y-m-d H:i:s');
        $hoje             = date('Y-m-d');

        if ($os_id <= 0)            { throw new Exception('Ordem de Serviço inválida.'); }
        if ($total_devolucao <= 0)  { throw new Exception('Informe um valor de devolução válido.'); }
        if ($forma_devolucao === '') { throw new Exception('Selecione a forma de devolução.'); }

        // ===== Regras específicas para devolução em ESPÉCIE =====
        if ($forma_devolucao === 'Espécie') {
            // Centavos devem terminar em 0 ou 5
            $centavos = ((int) round($total_devolucao * 100)) % 100;
            if ($centavos % 5 !== 0) {
                throw new Exception('Em espécie, os centavos devem terminar em 0 ou 5.');
            }

            // Precisa de caixa aberto hoje
            $cx = $conn->prepare("SELECT saldo_inicial FROM caixa WHERE DATE(data_caixa) = ? AND funcionario = ? LIMIT 1");
            $cx->bind_param("ss", $hoje, $funcionario);
            $cx->execute();
            $cxRes = $cx->get_result();
            if (!$cxRes || $cxRes->num_rows === 0) {
                throw new Exception('Você não possui caixa aberto hoje. Não é possível realizar uma devolução em espécie.');
            }
            $saldoInicial = (float) ($cxRes->fetch_assoc()['saldo_inicial'] ?? 0);

            // Helper para somar via query preparada
            $somar = function ($sql) use ($conn, $funcionario, $hoje) {
                $st = $conn->prepare($sql);
                $st->bind_param("ss", $funcionario, $hoje);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                return (float) ($row['s'] ?? 0);
            };

            $recebidoEspecie  = $somar("SELECT COALESCE(SUM(total_pagamento),0) s FROM pagamento_os WHERE funcionario=? AND DATE(data_pagamento)=? AND forma_de_pagamento='Espécie'");
            $devolvidoEspecie = $somar("SELECT COALESCE(SUM(total_devolucao),0) s FROM devolucao_os WHERE funcionario=? AND DATE(data_devolucao)=? AND forma_devolucao='Espécie'");
            $saidas           = $somar("SELECT COALESCE(SUM(valor_saida),0) s FROM saidas_despesas WHERE funcionario=? AND DATE(data)=? AND status='ativo'");
            $depositos        = $somar("SELECT COALESCE(SUM(valor_do_deposito),0) s FROM deposito_caixa WHERE funcionario=? AND DATE(data_caixa)=? AND status='ativo'");

            $saldoEspecie = $saldoInicial + $recebidoEspecie - $devolvidoEspecie - $saidas - $depositos;

            if ($total_devolucao > $saldoEspecie + 0.001) {
                throw new Exception('Saldo em espécie insuficiente no caixa para esta devolução. Disponível em espécie: R$ ' . number_format($saldoEspecie, 2, ',', '.') . '.');
            }
        }

        // Inserção
        $query = $conn->prepare("
            INSERT INTO devolucao_os
            (ordem_de_servico_id, cliente, total_os, total_devolucao, forma_devolucao, data_devolucao, funcionario, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$query) {
            throw new Exception('Erro na preparação da query: ' . $conn->error);
        }
        $query->bind_param("issdssss", $os_id, $cliente, $total_os, $total_devolucao, $forma_devolucao, $data_devolucao, $funcionario, $status);

        if (!$query->execute()) {
            throw new Exception('Erro ao salvar devolução: ' . $query->error);
        }

        echo json_encode(['success' => true, 'devolucao_id' => $conn->insert_id, 'data_devolucao' => $data_devolucao]);
        $query->close();
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
}
?>
