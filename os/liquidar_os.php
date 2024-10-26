<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];

    // Verifique se a conexão está definida
    if (!isset($conn)) {
        die(json_encode(['error' => 'Erro ao conectar ao banco de dados']));
    }

    try {
        // Iniciar transação
        $conn->begin_transaction();

        // Buscar todos os itens da OS
        $stmt = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE ordem_servico_id = ?");
        $stmt->bind_param("i", $os_id);
        $stmt->execute();
        $itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($itens as $item) {
            $quantidadeRestante = $item['quantidade'] - $item['quantidade_liquidada'];

            if ($quantidadeRestante > 0) {
                $status = ($quantidadeRestante + $item['quantidade_liquidada'] >= $item['quantidade']) 
                          ? 'liquidado' : 'parcialmente liquidado';

                $emolumentos_valor = 0;
                $ferc_valor = 0;
                $fadep_valor = 0;
                $femp_valor = 0;
                $total_valor = 0;

                if (!empty($item['ato']) && !in_array($item['ato'], ['0', '00', '9999'])) {
                    // Buscar valores na tabela emolumentos
                    $emol_query = $conn->prepare("SELECT * FROM tabela_emolumentos WHERE ato = ?");
                    $emol_query->bind_param("s", $item['ato']);
                    $emol_query->execute();
                    $emol_result = $emol_query->get_result();

                    if ($emol_result->num_rows > 0) {
                        $emolumentos = $emol_result->fetch_assoc();
                        $emolumentos_valor = floatval($emolumentos['EMOLUMENTOS']) * $quantidadeRestante;
                        $ferc_valor = floatval($emolumentos['FERC']) * $quantidadeRestante;
                        $fadep_valor = floatval($emolumentos['FADEP']) * $quantidadeRestante;
                        $femp_valor = floatval($emolumentos['FEMP']) * $quantidadeRestante;
                        $total_valor = floatval($emolumentos['TOTAL']) * $quantidadeRestante;
                    } else {
                        // Usar valores do item como fallback
                        $emolumentos_valor = floatval($item['emolumentos']) * $quantidadeRestante;
                        $ferc_valor = floatval($item['ferc']) * $quantidadeRestante;
                        $fadep_valor = floatval($item['fadep']) * $quantidadeRestante;
                        $femp_valor = floatval($item['femp']) * $quantidadeRestante;
                        $total_valor = floatval($item['total']) * $quantidadeRestante;
                    }
                } else {
                    // Ato inválido (0 ou 00), usar valores do item
                    $emolumentos_valor = floatval($item['emolumentos']) * $quantidadeRestante;
                    $ferc_valor = floatval($item['ferc']) * $quantidadeRestante;
                    $fadep_valor = floatval($item['fadep']) * $quantidadeRestante;
                    $femp_valor = floatval($item['femp']) * $quantidadeRestante;
                    $total_valor = floatval($item['total']) * $quantidadeRestante;
                }

                // Aplicar desconto legal
                $desconto_legal = floatval($item['desconto_legal'] ?? 0);
                if ($desconto_legal > 0) {
                    $factor = (1 - $desconto_legal / 100);
                    $emolumentos_valor *= $factor;
                    $ferc_valor *= $factor;
                    $fadep_valor *= $factor;
                    $femp_valor *= $factor;
                    $total_valor *= $factor;
                }

                // Salvar na tabela correspondente
                if (!in_array($item['ato'], ['0', '00', '9999'])) {
                    $stmt_insert = $conn->prepare(
                        "INSERT INTO atos_liquidados 
                        (ordem_servico_id, ato, quantidade_liquidada, desconto_legal, descricao, emolumentos, 
                        ferc, fadep, femp, total, funcionario, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt_insert->bind_param(
                        "isisssssssss",
                        $item['ordem_servico_id'], $item['ato'], $quantidadeRestante, $item['desconto_legal'], 
                        $item['descricao'], $emolumentos_valor, $ferc_valor, $fadep_valor, $femp_valor, 
                        $total_valor, $_SESSION['username'], $status
                    );
                    $stmt_insert->execute();
                } else {
                    $stmt_insert = $conn->prepare(
                        "INSERT INTO atos_manuais_liquidados 
                        (ordem_servico_id, ato, quantidade_liquidada, desconto_legal, descricao, emolumentos, 
                        ferc, fadep, femp, total, funcionario, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt_insert->bind_param(
                        "isisssssssss",
                        $item['ordem_servico_id'], $item['ato'], $quantidadeRestante, $item['desconto_legal'], 
                        $item['descricao'], $emolumentos_valor, $ferc_valor, $fadep_valor, $femp_valor, 
                        $total_valor, $_SESSION['username'], $status
                    );
                    $stmt_insert->execute();
                }

                // Atualizar o item na tabela ordens_de_servico_itens
                $stmt_update = $conn->prepare(
                    "UPDATE ordens_de_servico_itens 
                    SET quantidade_liquidada = ?, status = ? 
                    WHERE id = ?"
                );
                $stmt_update->bind_param("isi", $quantidadeRestante, $status, $item['id']);
                $stmt_update->execute();
            }
        }

        // Confirmar transação
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => 'Erro ao liquidar atos: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>
