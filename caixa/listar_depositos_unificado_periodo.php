<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

try {
    $dini = isset($_GET['data_inicial']) ? $_GET['data_inicial'] : null;
    $dfim = isset($_GET['data_final'])   ? $_GET['data_final']   : null;
    $func = isset($_GET['funcionario'])  ? $_GET['funcionario']  : 'todos';

    if (!$dini || !$dfim) {
        echo json_encode(['error' => 'Parâmetros "data_inicial" e "data_final" são obrigatórios.']);
        exit;
    }

    $conn = getDatabaseConnection();

    $sql = '
        SELECT id, funcionario, data_caixa, data_cadastro, valor_do_deposito, tipo_deposito, caminho_anexo
        FROM deposito_caixa
        WHERE status="ativo" AND DATE(data_caixa) BETWEEN :dini AND :dfim
    ';
    $bind = [':dini'=>$dini, ':dfim'=>$dfim];
    if ($func && $func !== 'todos') {
        $sql .= ' AND funcionario = :func';
        $bind[':func'] = $func;
    }
    $sql .= ' ORDER BY id DESC';

    $st = $conn->prepare($sql);
    $st->execute($bind);
    $depositos = $st->fetchAll(PDO::FETCH_ASSOC);

    // Verifica status do caixa por funcionário/data (para poder excluir)
    $stStatus = $conn->prepare('
        SELECT status FROM caixa
        WHERE funcionario = :funcionario AND DATE(data_caixa) = :data_caixa
        ORDER BY id DESC LIMIT 1
    ');

    $out = [];
    foreach ($depositos as $row) {
        $dataSomente = date('Y-m-d', strtotime($row['data_caixa']));
        $stStatus->execute([
            ':funcionario' => $row['funcionario'],
            ':data_caixa'  => $dataSomente
        ]);
        $cx = $stStatus->fetch(PDO::FETCH_ASSOC);
        $row['pode_excluir'] = ($cx && $cx['status'] === 'aberto') ? 1 : 0;
        $out[] = $row;
    }

    echo json_encode(['depositos' => $out]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
