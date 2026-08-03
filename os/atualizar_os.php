<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
$issCfg        = json_decode(file_get_contents(__DIR__ . '/iss_config.json'), true);
$issAtivo      = !empty($issCfg['ativo']);
$issPercentual = isset($issCfg['percentual']) ? (float)$issCfg['percentual'] : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];
    $cliente = mb_strtoupper(trim($_POST['cliente']), 'UTF-8');
    $cpf_cliente = $_POST['cpf_cliente'];
    $total_os = str_replace(',', '.', $_POST['total_os']);
    /* Base de cálculo do NÍVEL DA O.S. — campo LEGADO.
       A base agora é informada POR ATO. O campo do topo virou somente leitura
       (O.S. antigas) ou nem existe (O.S. novas), então o POST pode chegar
       vazio ou ausente. Nesse caso a coluna NÃO é tocada: gravar 0,00 apagaria
       a base das O.S. lançadas antes da mudança, que só têm o valor ali. */
    $base_calculo = isset($_POST['base_calculo']) ? trim((string) $_POST['base_calculo']) : '';
    $atualizarBase = ($base_calculo !== '');
    if ($atualizarBase) {
        /* O valor pode chegar em dois formatos: já normalizado pelo JS
           ("280000.00") ou como o usuário digitou ("280.000,00"). A vírgula é
           o que distingue os dois — sem ela, o ponto JÁ É o separador decimal
           e não pode ser removido. */
        if (strpos($base_calculo, ',') !== false) {
            $base_calculo = str_replace('.', '', $base_calculo);   // separador de milhar
            $base_calculo = str_replace(',', '.', $base_calculo);  // decimal
        }
        if (!is_numeric($base_calculo)) {
            $atualizarBase = false;
        }
    }
    $descricao_os = $_POST['descricao_os'];
    $observacoes = $_POST['observacoes'];

    try {
        $conn = getDatabaseConnection();

        // Inicia a transação
        $conn->beginTransaction();

        // Atualiza a OS na tabela `ordens_de_servico`
        $sqlOs = "UPDATE ordens_de_servico SET cliente = :cliente, cpf_cliente = :cpf_cliente, "
               . "total_os = :total_os, descricao_os = :descricao_os, observacoes = :observacoes"
               . ($atualizarBase ? ", base_de_calculo = :base_calculo" : "")
               . " WHERE id = :id";
        $stmt = $conn->prepare($sqlOs);
        $stmt->bindParam(':cliente', $cliente);
        $stmt->bindParam(':cpf_cliente', $cpf_cliente);
        $stmt->bindParam(':total_os', $total_os);
        $stmt->bindParam(':descricao_os', $descricao_os);
        $stmt->bindParam(':observacoes', $observacoes);
        if ($atualizarBase) {
            $stmt->bindParam(':base_calculo', $base_calculo);
        }
        $stmt->bindParam(':id', $os_id);
        $stmt->execute();

        /* ------------------------------------------------------------------
        ISS: o valor já LIQUIDADO é congelado (não aumenta nem reduz).
        - Calcula o ISS total devido sobre TODOS os emolumentos atuais.
        - Subtrai o ISS já liquidado (linhas ISS com quantidade_liquidada > 0).
        - O restante é lançado numa linha de ISS AINDA NÃO liquidada; se todas
          as linhas de ISS já estiverem liquidadas (ex.: novos atos incluídos
          depois da liquidação), cria-se uma NOVA linha de ISS para a diferença.
        ------------------------------------------------------------------*/
        if ($issAtivo) {
            $issDescricao = isset($issCfg['descricao']) ? $issCfg['descricao'] : 'ISS sobre Emolumentos';

            /* 1. Soma dos emolumentos dos demais itens (exclui 'ISS') */
            $somaEmol = $conn->prepare("
                SELECT COALESCE(SUM(emolumentos),0) AS total
                FROM   ordens_de_servico_itens
                WHERE  ordem_servico_id = :os_id
                AND  ato <> 'ISS'
            ");
            $somaEmol->bindParam(':os_id', $os_id);
            $somaEmol->execute();
            $totalEmol = (float)$somaEmol->fetchColumn();

            /* 2. ISS total devido sobre TODOS os emolumentos */
            $baseISS        = $totalEmol * 0.88;
            $issDevidoTotal = round($baseISS * ($issPercentual / 100), 2);

            /* 3. ISS já LIQUIDADO -> congelado (não pode aumentar nem reduzir) */
            $qLiq = $conn->prepare("
                SELECT COALESCE(SUM(total),0)
                FROM   ordens_de_servico_itens
                WHERE  ordem_servico_id = :os_id
                AND  ato = 'ISS'
                AND  COALESCE(quantidade_liquidada,0) > 0
            ");
            $qLiq->bindParam(':os_id', $os_id);
            $qLiq->execute();
            $issLiquidado = (float)$qLiq->fetchColumn();

            /* 4. ISS que ainda pode ser ajustado (sobre emolumentos ainda não cobertos) */
            $issAjustavel = round($issDevidoTotal - $issLiquidado, 2);
            if ($issAjustavel < 0) {
                $issAjustavel = 0; // nunca estorna ISS já liquidado
            }

            /* 5. Há uma linha de ISS AINDA NÃO liquidada para receber esse valor? */
            $qFind = $conn->prepare("
                SELECT id
                FROM   ordens_de_servico_itens
                WHERE  ordem_servico_id = :os_id
                AND  ato = 'ISS'
                AND  COALESCE(quantidade_liquidada,0) = 0
                ORDER BY id ASC
                LIMIT 1
            ");
            $qFind->bindParam(':os_id', $os_id);
            $qFind->execute();
            $issRowId = $qFind->fetchColumn();

            if ($issRowId) {
                /* 5a. Atualiza a linha de ISS ainda ajustável (NÃO toca nas liquidadas) */
                $updISS = $conn->prepare("
                    UPDATE ordens_de_servico_itens
                    SET emolumentos = :valor_emol,
                        total       = :valor_total
                    WHERE id = :id
                ");
                $updISS->bindParam(':valor_emol',  $issAjustavel);
                $updISS->bindParam(':valor_total', $issAjustavel);
                $updISS->bindParam(':id',          $issRowId);
                $updISS->execute();
            } elseif ($issLiquidado > 0 && $issAjustavel > 0) {
                /* 5b. Só cria nova linha quando JÁ EXISTE ISS liquidado (congelado) e
                       surgiu ISS novo a cobrar (atos incluídos após a liquidação).
                       Mantém o comportamento original de NÃO criar ISS do zero. */
                $insISS = $conn->prepare("
                    INSERT INTO ordens_de_servico_itens
                        (ordem_servico_id, ato, quantidade, desconto_legal, descricao,
                         emolumentos, ferc, fadep, femp, ferrfis, total, quantidade_liquidada)
                    VALUES
                        (:os_id, 'ISS', 1, 0, :descricao,
                         :valor_emol, 0, 0, 0, 0, :valor_total, 0)
                ");
                $insISS->bindParam(':os_id',       $os_id);
                $insISS->bindParam(':descricao',   $issDescricao);
                $insISS->bindParam(':valor_emol',  $issAjustavel);
                $insISS->bindParam(':valor_total', $issAjustavel);
                $insISS->execute();
            }
        }

        /* ------------------------------------------------------------------
        Recalcula o total da OS a partir dos próprios itens. Garante que o
        total_os fique consistente mesmo quando o servidor cria uma nova
        linha de ISS (que o total enviado pelo cliente ainda não enxergava).
        ------------------------------------------------------------------*/
        $recalcTotal = $conn->prepare("
            SELECT COALESCE(SUM(total),0)
            FROM   ordens_de_servico_itens
            WHERE  ordem_servico_id = :os_id
        ");
        $recalcTotal->bindParam(':os_id', $os_id);
        $recalcTotal->execute();
        $totalOSrecalc = (float)$recalcTotal->fetchColumn();

        $updTotal = $conn->prepare("UPDATE ordens_de_servico SET total_os = :total_os WHERE id = :id");
        $updTotal->bindParam(':total_os', $totalOSrecalc);
        $updTotal->bindParam(':id',       $os_id);
        $updTotal->execute();

        // Confirma a transação
        $conn->commit();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        // Desfaz a transação em caso de erro
        $conn->rollBack();
        echo json_encode(['error' => 'Erro ao atualizar a Ordem de Serviço: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>