<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

try {
    $funcionario = isset($_GET['funcionario']) ? $_GET['funcionario'] : null;
    $data_caixa  = isset($_GET['data_caixa']) ? $_GET['data_caixa'] : null;

    if (!$funcionario || !$data_caixa) {
        echo json_encode(['error' => 'Parâmetros inválidos.']);
        exit;
    }

    $conn = getDatabaseConnection();

    // Verifica se o caixa desse funcionário na data está ABERTO
    $st = $conn->prepare("
        SELECT status 
        FROM caixa 
        WHERE funcionario = :funcionario 
          AND DATE(data_caixa) = :data_caixa
        ORDER BY id DESC 
        LIMIT 1
    ");
    $st->execute([
        ':funcionario' => $funcionario,
        ':data_caixa'  => $data_caixa
    ]);
    $cx = $st->fetch(PDO::FETCH_ASSOC);
    $podeExcluir = ($cx && $cx['status'] === 'aberto') ? 1 : 0;

    // Busca saídas ativas da data/funcionário
    $sql = '
        SELECT id, funcionario, titulo, valor_saida, forma_de_saida, caminho_anexo, data_caixa, data
        FROM saidas_despesas 
        WHERE funcionario = :funcionario 
          AND DATE(data_caixa) = :data_caixa 
          AND status = "ativo"
        ORDER BY id DESC
    ';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->bindParam(':data_caixa', $data_caixa);
    $stmt->execute();
    $saidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Anexa a flag pode_excluir em cada item
    $out = [];
    foreach ($saidas as $row) {
        $row['pode_excluir'] = $podeExcluir;
        $out[] = $row;
    }

    echo json_encode(['saidas' => $out]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
