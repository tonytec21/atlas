<?php
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

$data = isset($_GET['data']) ? $_GET['data'] : null;

try {
    if (!$data) {
        echo json_encode(['error' => 'Parâmetro "data" é obrigatório.']);
        exit;
    }

    $conn = getDatabaseConnection();

    // Lista todos os depósitos (ativos) da data, independentemente do funcionário
    $stmt = $conn->prepare("
        SELECT id, funcionario, data_caixa, data_cadastro, valor_do_deposito, tipo_deposito, caminho_anexo
        FROM deposito_caixa
        WHERE DATE(data_caixa) = :data_caixa 
          AND status = 'ativo'
        ORDER BY id DESC
    ");
    $stmt->bindParam(':data_caixa', $data);
    $stmt->execute();
    $depositos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statement para verificar status do caixa por funcionário/data (reuso dentro do loop)
    $stStatus = $conn->prepare("
        SELECT status 
        FROM caixa 
        WHERE funcionario = :funcionario 
          AND DATE(data_caixa) = :data_caixa
        ORDER BY id DESC 
        LIMIT 1
    ");

    $out = [];
    foreach ($depositos as $row) {
        $dataSomente = date('Y-m-d', strtotime($row['data_caixa']));

        // Verifica se o caixa daquele funcionário nessa data está ABERTO
        $stStatus->execute([
            ':funcionario' => $row['funcionario'],
            ':data_caixa'  => $dataSomente
        ]);
        $cx = $stStatus->fetch(PDO::FETCH_ASSOC);
        $podeExcluir = ($cx && $cx['status'] === 'aberto') ? 1 : 0;

        $row['pode_excluir'] = $podeExcluir;
        $out[] = $row;
    }

    echo json_encode(['depositos' => $out]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
