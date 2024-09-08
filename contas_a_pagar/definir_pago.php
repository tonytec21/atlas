<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Verifica se o ID foi enviado via POST
if (isset($_POST['id'])) {
    $id = $_POST['id'];

    // Inicia uma transação para garantir consistência
    $conn->begin_transaction();

    try {
        // 1. Atualiza o status da conta para "Pago" na tabela contas_a_pagar
        $update_sql = "UPDATE contas_a_pagar SET status = 'Pago' WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param('i', $id);

        if (!$stmt->execute()) {
            throw new Exception("Erro ao atualizar o status da conta.");
        }

        // 2. Copia os dados da conta para a tabela contas_pagas
        $select_sql = "SELECT * FROM contas_a_pagar WHERE id = ?";
        $stmt = $conn->prepare($select_sql);
        $stmt->bind_param('i', $id);

        if (!$stmt->execute()) {
            throw new Exception("Erro ao buscar dados da conta.");
        }

        $result = $stmt->get_result();
        $conta = $result->fetch_assoc();

        // Insere os dados na tabela contas_pagas
        $insert_sql = "INSERT INTO contas_pagas (titulo, valor, data_vencimento, descricao, recorrencia, caminho_anexo, funcionario, status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param(
            'sdssssss',
            $conta['titulo'],
            $conta['valor'],
            $conta['data_vencimento'],
            $conta['descricao'],
            $conta['recorrencia'],
            $conta['caminho_anexo'],
            $conta['funcionario'],
            $conta['status']
        );

        if (!$stmt->execute()) {
            throw new Exception("Erro ao copiar a conta para a tabela de contas pagas.");
        }

        // Se tudo deu certo, confirma a transação
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Conta marcada como paga e copiada para contas pagas.']);
    } catch (Exception $e) {
        // Em caso de erro, faz o rollback da transação
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID da conta não informado.']);
}
?>
