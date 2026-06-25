<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];

    if (!isset($conn)) {
        die(json_encode(['error' => 'Erro ao conectar ao banco de dados']));
    }

    try {
        // Iniciar transação
        $conn->begin_transaction();

        // ========== VERIFICAR E ADICIONAR COLUNA FERRFIS SE NÃO EXISTIR ==========
        // Tabela atos_liquidados
        $checkColumn = $conn->query("SHOW COLUMNS FROM atos_liquidados LIKE 'ferrfis'");
        if ($checkColumn->num_rows == 0) {
            $conn->query("ALTER TABLE atos_liquidados ADD COLUMN ferrfis DECIMAL(10,2) DEFAULT 0.00 AFTER femp");
        }

        // Tabela atos_manuais_liquidados
        $checkColumn2 = $conn->query("SHOW COLUMNS FROM atos_manuais_liquidados LIKE 'ferrfis'");
        if ($checkColumn2->num_rows == 0) {
            $conn->query("ALTER TABLE atos_manuais_liquidados ADD COLUMN ferrfis DECIMAL(10,2) DEFAULT 0.00 AFTER femp");
        }
        // ========================================================================

        // Buscar todos os itens da OS
        $stmt = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = ?");
        $stmt->bind_param("i", $os_id);
        $stmt->execute();
        $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Obter o ano de criação da OS
        $os_query = $conn->prepare("SELECT data_criacao FROM ordens_de_servico WHERE id = ?");
        $os_query->bind_param("i", $os_id);
        $os_query->execute();
        $os_result = $os_query->get_result();

        if ($os_result->num_rows > 0) {
            $os_data = $os_result->fetch_assoc();
            $ano_criacao = date('Y', strtotime($os_data['data_criacao']));
            $ano_atual = date('Y');
            
            // Se for do ano corrente, usa tabela_emolumentos; caso contrário, usa tabela_emolumentos_[ANO]
            $tabela_emolumentos = ($ano_criacao == $ano_atual) ? 'tabela_emolumentos' : 'tabela_emolumentos_' . $ano_criacao;
        } else {
            throw new Exception('Ordem de Serviço não encontrada.');
        }

        foreach ($itens as $item) {
            $quantidadeRestante = $item['quantidade'] - $item['quantidade_liquidada'];

            if ($quantidadeRestante > 0) {
                $status = ($quantidadeRestante + $item['quantidade_liquidada'] >= $item['quantidade']) 
                        ? 'liquidado' : 'parcialmente liquidado';

                // Detectar marcação "(ato isento)" no código do ato
                // (mantido: usado mais abaixo para decidir a tabela de destino)
                $atoIsento = isset($item['ato']) && stripos($item['ato'], '(isento)') !== false;

                // ===================================================================
                // LIQUIDAÇÃO == ORÇAMENTO (correção da diferença de 1 centavo)
                // -------------------------------------------------------------------
                // Os campos do item (ordens_de_servico_itens) já estão GRAVADOS com o
                // desconto legal aplicado e arredondados EXATAMENTE como saem no
                // orçamento (imprimir_os.php). Antes, a liquidação re-buscava o valor
                // cheio na tabela de emolumentos e reaplicava o desconto; o
                // arredondamento do meio-centavo (x,xx5) no MySQL (DECIMAL, half-up)
                // divergia do toFixed() do JS do orçamento (que arredonda p/ baixo),
                // gerando 1 centavo de diferença em atos como 16.1 e 16.3.18.
                //
                // Agora derivamos os valores DOS PRÓPRIOS CAMPOS DO ITEM. Para suportar
                // liquidação parcial sem perder/ganhar centavos, usamos rateio
                // cumulativo: a parcela desta liquidação é (acumulado até agora) menos
                // (o que já foi liquidado). Assim a soma das parcelas fecha sempre com
                // o total do orçamento.
                // ===================================================================
                $qtdTotal       = floatval($item['quantidade']);
                $qtdJaLiquidada = floatval($item['quantidade_liquidada']);
                $qtdNova        = $qtdJaLiquidada + $quantidadeRestante;

                $rateioLiquidacao = function ($valorTotalItem) use ($qtdTotal, $qtdJaLiquidada, $qtdNova) {
                    $valorTotalItem = floatval($valorTotalItem);
                    if ($qtdTotal <= 0) {
                        return round($valorTotalItem, 2);
                    }
                    $acumuladoAgora = round($valorTotalItem * $qtdNova        / $qtdTotal, 2);
                    $jaLiquidado    = round($valorTotalItem * $qtdJaLiquidada / $qtdTotal, 2);
                    return round($acumuladoAgora - $jaLiquidado, 2);
                };

                $emolumentos_valor = $rateioLiquidacao($item['emolumentos']);
                $ferc_valor        = $rateioLiquidacao($item['ferc']);
                $fadep_valor       = $rateioLiquidacao($item['fadep']);
                $femp_valor        = $rateioLiquidacao($item['femp']);
                $ferrfis_valor     = $rateioLiquidacao($item['ferrfis'] ?? 0);
                $total_valor       = $rateioLiquidacao($item['total']);

                if (!$atoIsento && !in_array($item['ato'], ['0', '00', '9999', 'ISS'])) {
                    $stmt_insert = $conn->prepare(
                        "INSERT INTO atos_liquidados 
                        (ordem_servico_id, ato, quantidade_liquidada, desconto_legal, descricao, emolumentos, 
                        ferc, fadep, femp, ferrfis, total, funcionario, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt_insert->bind_param(
                        "isissssssssss",
                        $item['ordem_servico_id'], $item['ato'], $quantidadeRestante, $item['desconto_legal'], 
                        $item['descricao'], $emolumentos_valor, $ferc_valor, $fadep_valor, $femp_valor, 
                        $ferrfis_valor, $total_valor, $_SESSION['username'], $status
                    );
                    $stmt_insert->execute();
                } else {
                    // Isento OU inválido => atos_manuais_liquidados
                    $stmt_insert = $conn->prepare(
                        "INSERT INTO atos_manuais_liquidados 
                        (ordem_servico_id, ato, quantidade_liquidada, desconto_legal, descricao, emolumentos, 
                        ferc, fadep, femp, ferrfis, total, funcionario, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt_insert->bind_param(
                        "isissssssssss",
                        $item['ordem_servico_id'], $item['ato'], $quantidadeRestante, $item['desconto_legal'], 
                        $item['descricao'], $emolumentos_valor, $ferc_valor, $fadep_valor, $femp_valor, 
                        $ferrfis_valor, $total_valor, $_SESSION['username'], $status
                    );
                    $stmt_insert->execute();
                }

                $stmt_update = $conn->prepare(
                    "UPDATE ordens_de_servico_itens 
                    SET quantidade_liquidada = ?, status = ? 
                    WHERE id = ?"
                );
                $stmt_update->bind_param("isi", $quantidadeRestante, $status, $item['id']);
                $stmt_update->execute();
            }
        }

        $conn->commit();

        // ===== Rastreio: O.S. inteira liquidada -> 'emitida' (best-effort) =====
        try {
            require_once(__DIR__ . '/../pedidos_certidao/os_rastreio_lib.php');
            $pdoRastreio = os_rastreio_pdo();
            $usuarioR = isset($_SESSION['username']) ? $_SESSION['username'] : 'sistema';
            os_rastreio_sync_liquidacao($pdoRastreio, (int)$os_id, $usuarioR);
        } catch (Throwable $eR) {
            error_log('[liquidar_os][rastreio] ' . $eR->getMessage());
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => 'Erro ao liquidar atos: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>