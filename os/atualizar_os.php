<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];
    $cliente = $_POST['cliente'];
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
