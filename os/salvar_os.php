<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cliente = $_POST['cliente'];
    $cpf_cliente = $_POST['cpf_cliente'];
    $total_os = str_replace(',', '.', $_POST['total_os']);
    $itens = $_POST['itens'];
    $descricao_os = $_POST['descricao_os'];
    $observacoes = $_POST['observacoes'];
    $criado_por = $_SESSION['username'];

    try {
        $conn = getDatabaseConnection();

        // Inicia a transação
        $conn->beginTransaction();

        // Insere a OS na tabela `ordens_de_servico`
        $stmt = $conn->prepare("INSERT INTO ordens_de_servico (cliente, cpf_cliente, total_os, descricao_os, observacoes, criado_por) VALUES (:cliente, :cpf_cliente, :total_os, :descricao_os, :observacoes, :criado_por)");
        $stmt->bindParam(':cliente', $cliente);
        $stmt->bindParam(':cpf_cliente', $cpf_cliente);
        $stmt->bindParam(':total_os', $total_os);
        $stmt->bindParam(':descricao_os', $descricao_os);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':criado_por', $criado_por);
        $stmt->execute();

        // Obtém o ID da OS inserida
        $os_id = $conn->lastInsertId();

        // Insere os itens na tabela `ordens_de_servico_itens`
        $stmt = $conn->prepare("INSERT INTO ordens_de_servico_itens (ordem_servico_id, ato, quantidade, desconto_legal, descricao, emolumentos, ferc, fadep, femp, total) VALUES (:ordem_servico_id, :ato, :quantidade, :desconto_legal, :descricao, :emolumentos, :ferc, :fadep, :femp, :total)");

        foreach ($itens as $item) {
            $ordem_servico_id = $os_id;
            $ato = $item['ato'];
            $quantidade = $item['quantidade'];
            $desconto_legal = $item['desconto_legal'];
            $descricao = $item['descricao'];
            $emolumentos = str_replace(',', '.', $item['emolumentos']);
            $ferc = str_replace(',', '.', $item['ferc']);
            $fadep = str_replace(',', '.', $item['fadep']);
            $femp = str_replace(',', '.', $item['femp']);
            $total = str_replace(',', '.', $item['total']);

            $stmt->bindParam(':ordem_servico_id', $ordem_servico_id);
            $stmt->bindParam(':ato', $ato);
            $stmt->bindParam(':quantidade', $quantidade);
            $stmt->bindParam(':desconto_legal', $desconto_legal);
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':emolumentos', $emolumentos);
            $stmt->bindParam(':ferc', $ferc);
            $stmt->bindParam(':fadep', $fadep);
            $stmt->bindParam(':femp', $femp);
            $stmt->bindParam(':total', $total);
            $stmt->execute();
        }

        // Confirma a transação
        $conn->commit();

        // Modificação para retornar o ID da OS criada
        echo json_encode(['success' => true, 'id' => $os_id]);
    } catch (PDOException $e) {
        // Desfaz a transação em caso de erro
        $conn->rollBack();
        echo json_encode(['error' => 'Erro ao salvar a Ordem de Serviço: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>