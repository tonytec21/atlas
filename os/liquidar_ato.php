<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = $_POST['item_id'];
    $quantidade_liquidar = intval($_POST['quantidade_liquidar']);

    // Verifique se a conexão está definida
    if (!isset($conn)) {
        die("Erro ao conectar ao banco de dados");
    }

    try {
        // Iniciar transação
        $conn->begin_transaction();

        // Buscar item atual
        $stmt = $conn->prepare("SELECT * FROM ordens_de_servico_itens WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();

        // Verificar se a quantidade a liquidar é válida
        if ($item['quantidade_liquidada'] + $quantidade_liquidar > $item['quantidade']) {
            throw new Exception('Quantidade a liquidar excede a quantidade total.');
        }

        $nova_quantidade_liquidada = $item['quantidade_liquidada'] + $quantidade_liquidar;
        $status = ($nova_quantidade_liquidada >= $item['quantidade']) ? 'liquidado' : 'parcialmente liquidado';

        // Inicializar valores para emolumentos, ferc, fadep, femp, total
        $emolumentos_valor = 0;
        $ferc_valor = 0;
        $fadep_valor = 0;
        $femp_valor = 0;
        $total_valor = 0;

        // Se o campo "ato" for válido, buscar valores na tabela emolumentos
        if (!empty($item['ato']) && !in_array($item['ato'], ['0', '00'])) {
            $emolumentos_query = $conn->prepare("SELECT * FROM tabela_emolumentos WHERE ato = ?");
            $emolumentos_query->bind_param("s", $item['ato']);
            $emolumentos_query->execute();
            $emolumentos_result = $emolumentos_query->get_result();

            if ($emolumentos_result->num_rows > 0) {
                $emolumentos = $emolumentos_result->fetch_assoc();
            
                $emolumentos_valor = isset($emolumentos['EMOLUMENTOS']) ? floatval($emolumentos['EMOLUMENTOS']) * $quantidade_liquidar : 0;
                $ferc_valor = isset($emolumentos['FERC']) ? floatval($emolumentos['FERC']) * $quantidade_liquidar : 0;
                $fadep_valor = isset($emolumentos['FADEP']) ? floatval($emolumentos['FADEP']) * $quantidade_liquidar : 0;
                $femp_valor = isset($emolumentos['FEMP']) ? floatval($emolumentos['FEMP']) * $quantidade_liquidar : 0;
                $total_valor = isset($emolumentos['TOTAL']) ? floatval($emolumentos['TOTAL']) * $quantidade_liquidar : 0;
            } else {
                // Usar valores da tabela ordens_de_servico_itens como fallback
                $emolumentos_valor = floatval($item['emolumentos']) * $quantidade_liquidar;
                $ferc_valor = floatval($item['ferc']) * $quantidade_liquidar;
                $fadep_valor = floatval($item['fadep']) * $quantidade_liquidar;
                $femp_valor = floatval($item['femp']) * $quantidade_liquidar;
                $total_valor = floatval($item['total']) * $quantidade_liquidar;
            }
            
            // Aplicar desconto legal, se houver
            $desconto_legal = isset($item['desconto_legal']) ? floatval($item['desconto_legal']) : 0;
            
            if ($desconto_legal > 0) {
                $emolumentos_valor *= (1 - $desconto_legal / 100);
                $ferc_valor *= (1 - $desconto_legal / 100);
                $fadep_valor *= (1 - $desconto_legal / 100);
                $femp_valor *= (1 - $desconto_legal / 100);
                $total_valor *= (1 - $desconto_legal / 100);
            }
            
            // Verificar valores antes de inserir
            if ($emolumentos_valor > 0 || $ferc_valor > 0 || $fadep_valor > 0 || $femp_valor > 0 || $total_valor > 0) {
                // Adicionar item liquidado na tabela atos_liquidados
                $stmt = $conn->prepare("INSERT INTO atos_liquidados (ordem_servico_id, ato, quantidade_liquidada, desconto_legal, descricao, emolumentos, ferc, fadep, femp, total, funcionario, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isisssssssss", 
                    $item['ordem_servico_id'], 
                    $item['ato'], 
                    $quantidade_liquidar, 
                    $item['desconto_legal'], 
                    $item['descricao'], 
                    $emolumentos_valor, 
                    $ferc_valor, 
                    $fadep_valor, 
                    $femp_valor, 
                    $total_valor, 
                    $_SESSION['username'], 
                    $status
                );
                $stmt->execute();
            }            
        }

        // Atualizar item
        $stmt = $conn->prepare("UPDATE ordens_de_servico_itens SET quantidade_liquidada = ?, status = ? WHERE id = ?");
        $stmt->bind_param("isi", $nova_quantidade_liquidada, $status, $item_id);
        $stmt->execute();

        // Confirmar transação
        $conn->commit();

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $conn->rollback();
        echo json_encode(['error' => 'Erro ao liquidar ato: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>
