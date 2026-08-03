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
    $base_calculo = str_replace(',', '.', $_POST['base_calculo']);
    $descricao_os = $_POST['descricao_os'];
    $observacoes = $_POST['observacoes'];

    try {
        $conn = getDatabaseConnection();

        // Inicia a transação
        $conn->beginTransaction();

        // Atualiza a OS na tabela `ordens_de_servico`
        $stmt = $conn->prepare("UPDATE ordens_de_servico SET cliente = :cliente, cpf_cliente = :cpf_cliente, total_os = :total_os, descricao_os = :descricao_os, observacoes = :observacoes, base_de_calculo = :base_calculo WHERE id = :id");
        $stmt->bindParam(':cliente', $cliente);
        $stmt->bindParam(':cpf_cliente', $cpf_cliente);
        $stmt->bindParam(':total_os', $total_os);
        $stmt->bindParam(':descricao_os', $descricao_os);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':base_calculo', $base_calculo);
        $stmt->bindParam(':id', $os_id);
        $stmt->execute();

        /* ------------------------------------------------------------------
        Se o ISS estiver ativado na configuração, recalcule e atualize
        a linha correspondente (ato = 'ISS') sem criar linhas novas.
        ------------------------------------------------------------------*/
        if ($issAtivo) {
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

            /* 2. Cálculo do ISS */
            $baseISS  = $totalEmol * 0.88;
            $valorISS = $baseISS * ($issPercentual / 100);

            /* 3. Atualiza a linha existente (se houver)             */
            $updISS = $conn->prepare("
                UPDATE ordens_de_servico_itens
                SET emolumentos = :valor_emol,
                    total       = :valor_total
                WHERE ordem_servico_id = :os_id
                AND ato = 'ISS'
                LIMIT 1
            ");
            $updISS->bindParam(':valor_emol',  $valorISS);
            $updISS->bindParam(':valor_total', $valorISS);
            $updISS->bindParam(':os_id',       $os_id);
            $updISS->execute();
        }

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