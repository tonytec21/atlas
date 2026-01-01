<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = $_POST['item_id'];
    $quantidade_liquidar = intval($_POST['quantidade_liquidar']);

    if (!isset($conn)) {
        die("Erro ao conectar ao banco de dados");
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

        // Buscar item atual
        $stmt = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();

        if ($item['quantidade_liquidada'] + $quantidade_liquidar > $item['quantidade']) {
            throw new Exception('Quantidade a liquidar excede a quantidade total.');
        }

        $nova_quantidade_liquidada = $item['quantidade_liquidada'] + $quantidade_liquidar;
        $status = ($nova_quantidade_liquidada >= $item['quantidade']) ? 'liquidado' : 'parcialmente liquidado';

        // Buscar data de criação da OS
        $os_query = $conn->prepare("SELECT data_criacao FROM ordens_de_servico WHERE id = ?");
        $os_query->bind_param("i", $item['ordem_servico_id']);
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

        // Inicializar valores
        $emolumentos_valor = 0;
        $ferc_valor = 0;
        $fadep_valor = 0;
        $femp_valor = 0;
        $ferrfis_valor = 0;
        $total_valor = 0;

        // Verificar se o ato é válido
        // Detectar marcação "(ato isento)" no código do ato
        $atoIsento = isset($item['ato']) && stripos($item['ato'], '(isento)') !== false;

        // Verificar se o ato é válido e NÃO é isento
        if (!$atoIsento && !empty($item['ato']) && !in_array($item['ato'], ['0', '00', '9999', 'ISS'])) {
            // Buscar valores na tabela apropriada
            $emolumentos_query = $conn->prepare("SELECT * FROM $tabela_emolumentos WHERE ato = ?");
            $emolumentos_query->bind_param("s", $item['ato']);
            $emolumentos_query->execute();
            $emolumentos_result = $emolumentos_query->get_result();

            if ($emolumentos_result->num_rows > 0) {
                $emolumentos = $emolumentos_result->fetch_assoc();

                $emolumentos_valor = isset($emolumentos['EMOLUMENTOS']) ? floatval($emolumentos['EMOLUMENTOS']) * $quantidade_liquidar : 0;
                $ferc_valor = isset($emolumentos['FERC']) ? floatval($emolumentos['FERC']) * $quantidade_liquidar : 0;
                $fadep_valor = isset($emolumentos['FADEP']) ? floatval($emolumentos['FADEP']) * $quantidade_liquidar : 0;
                $femp_valor = isset($emolumentos['FEMP']) ? floatval($emolumentos['FEMP']) * $quantidade_liquidar : 0;
                $ferrfis_valor = isset($emolumentos['FERRFIS']) ? floatval($emolumentos['FERRFIS']) * $quantidade_liquidar : 0;
                $total_valor = isset($emolumentos['TOTAL']) ? floatval($emolumentos['TOTAL']) * $quantidade_liquidar : 0;
            } else {
                $emolumentos_valor = floatval($item['emolumentos']) * $quantidade_liquidar;
                $ferc_valor = floatval($item['ferc']) * $quantidade_liquidar;
                $fadep_valor = floatval($item['fadep']) * $quantidade_liquidar;
                $femp_valor = floatval($item['femp']) * $quantidade_liquidar;
                $ferrfis_valor = isset($item['ferrfis']) ? floatval($item['ferrfis']) * $quantidade_liquidar : 0;
                $total_valor = floatval($item['total']) * $quantidade_liquidar;
            }

            $desconto_legal = isset($item['desconto_legal']) ? floatval($item['desconto_legal']) : 0;

            if ($desconto_legal > 0) {
                $emolumentos_valor *= (1 - $desconto_legal / 100);
                $ferc_valor *= (1 - $desconto_legal / 100);
                $fadep_valor *= (1 - $desconto_legal / 100);
                $femp_valor *= (1 - $desconto_legal / 100);
                $ferrfis_valor *= (1 - $desconto_legal / 100);
                $total_valor *= (1 - $desconto_legal / 100);
            }

            if ($emolumentos_valor > 0 || $ferc_valor > 0 || $fadep_valor > 0 || $femp_valor > 0 || $ferrfis_valor > 0 || $total_valor > 0) {
                $stmt = $conn->prepare("INSERT INTO atos_liquidados (ordem_servico_id, ato, quantidade_liquidada, desconto_legal, descricao, emolumentos, ferc, fadep, femp, ferrfis, total, funcionario, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isissssssssss",
                    $item['ordem_servico_id'],
                    $item['ato'],
                    $quantidade_liquidar,
                    $item['desconto_legal'],
                    $item['descricao'],
                    $emolumentos_valor,
                    $ferc_valor,
                    $fadep_valor,
                    $femp_valor,
                    $ferrfis_valor,
                    $total_valor,
                    $_SESSION['username'],
                    $status
                );
                $stmt->execute();
            }
        } else {
            // Ato inválido OU isento -> inserir na tabela atos_manuais_liquidados
            $emolumentos_valor = floatval($item['emolumentos']) * $quantidade_liquidar;
            $ferc_valor = floatval($item['ferc']) * $quantidade_liquidar;
            $fadep_valor = floatval($item['fadep']) * $quantidade_liquidar;
            $femp_valor = floatval($item['femp']) * $quantidade_liquidar;
            $ferrfis_valor = isset($item['ferrfis']) ? floatval($item['ferrfis']) * $quantidade_liquidar : 0;
            $total_valor = floatval($item['total']) * $quantidade_liquidar;

            $stmt = $conn->prepare("INSERT INTO atos_manuais_liquidados (ordem_servico_id, ato, quantidade_liquidada, desconto_legal, descricao, emolumentos, ferc, fadep, femp, ferrfis, total, funcionario, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isissssssssss",
                $item['ordem_servico_id'],
                $item['ato'],
                $quantidade_liquidar,
                $item['desconto_legal'],
                $item['descricao'],
                $emolumentos_valor,
                $ferc_valor,
                $fadep_valor,
                $femp_valor,
                $ferrfis_valor,
                $total_valor,
                $_SESSION['username'],
                $status
            );
            $stmt->execute();
        }

        $stmt = $conn->prepare("UPDATE ordens_de_servico_itens SET quantidade_liquidada = ?, status = ? WHERE id = ?");
        $stmt->bind_param("isi", $nova_quantidade_liquidada, $status, $item_id);
        $stmt->execute();

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => 'Erro ao liquidar ato: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>