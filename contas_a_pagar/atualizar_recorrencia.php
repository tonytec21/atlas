<?php
include(__DIR__ . '/session_check.php');
include(__DIR__ . '/db_connection.php');

// Atualizar a data de vencimento e status das contas recorrentes
function atualizarRecorrencia($conn) {
    // Seleciona todas as contas recorrentes que foram pagas
    $sql = "SELECT * FROM contas_pagas WHERE recorrencia != 'Nenhuma'";
    $result = $conn->query($sql);

    while ($conta = $result->fetch_assoc()) {
        $nova_data_vencimento = null;
        $data_vencimento_atual = new DateTime($conta['data_vencimento']);
        $hoje = new DateTime();

        // Verifica o tipo de recorrência e ajusta a nova data de vencimento
        switch ($conta['recorrencia']) {
            case 'Mensal':
                $nova_data_vencimento = $data_vencimento_atual->modify('+1 month');
                break;
            case 'Semanal':
                $nova_data_vencimento = $data_vencimento_atual->modify('+1 week');
                break;
            case 'Anual':
                $nova_data_vencimento = $data_vencimento_atual->modify('+1 year');
                break;
        }

        // Se a nova data de vencimento for no futuro, mover a conta de volta para contas_a_pagar
        if ($nova_data_vencimento > $hoje) {
            $sql_update = "INSERT INTO contas_a_pagar (titulo, valor, data_vencimento, descricao, recorrencia, caminho_anexo, funcionario, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente')";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param(
                'sdssss',
                $conta['titulo'],
                $conta['valor'],
                $nova_data_vencimento->format('Y-m-d'),
                $conta['descricao'],
                $conta['recorrencia'],
                $conta['caminho_anexo'],
                $conta['funcionario']
            );

            if ($stmt->execute()) {
                // Remove a conta da tabela contas_pagas
                $sql_delete = "DELETE FROM contas_pagas WHERE id = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                $stmt_delete->bind_param('i', $conta['id']);
                $stmt_delete->execute();
            }
        }
    }
}

atualizarRecorrencia($conn);
?>
