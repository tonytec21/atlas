<?php
include(__DIR__ . '/db_connection.php');
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $modelo_id = $_GET['id'];
    try {
        $conn = getDatabaseConnection();

        // 1) Buscar as infos do modelo principal
        $stmtModelo = $conn->prepare("SELECT nome_modelo, descricao FROM modelos_de_orcamento WHERE id = :id");
        $stmtModelo->bindParam(':id', $modelo_id);
        $stmtModelo->execute();
        $modelo = $stmtModelo->fetch(PDO::FETCH_ASSOC);

        if (!$modelo) {
            echo json_encode(['error' => 'Modelo não encontrado.']);
            exit;
        }

        // 2) Buscar os itens do modelo
        $stmt = $conn->prepare("SELECT * FROM modelos_de_orcamento_itens WHERE modelo_id = :id");
        $stmt->bindParam(':id', $modelo_id);
        $stmt->execute();
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Retornar tudo no JSON
        echo json_encode([
            'nome_modelo'       => $modelo['nome_modelo'],
            'descricao_modelo'  => $modelo['descricao'],
            'itens'             => $itens
        ]);
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Erro ao carregar modelo: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'ID do modelo não fornecido.']);
}
