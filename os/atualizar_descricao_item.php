<?php
/**
 * Atualiza a descrição de um item da OS (edição in-line em editar_os.php).
 */
header('Content-Type: application/json; charset=utf-8');
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método inválido']);
    exit;
}

$os_id     = isset($_POST['os_id'])     ? $_POST['os_id']     : null;
$item_id   = isset($_POST['item_id'])   ? $_POST['item_id']   : null;
$descricao = isset($_POST['descricao']) ? $_POST['descricao'] : '';

if (!$os_id || !$item_id) {
    echo json_encode(['error' => 'Parâmetros inválidos.']);
    exit;
}

try {
    $conn = getDatabaseConnection();
    // Não permite editar a descrição do ato ISS (automático/dinâmico)
    $stmt = $conn->prepare(
        "UPDATE ordens_de_servico_itens
            SET descricao = :descricao
          WHERE id = :id
            AND ordem_servico_id = :os_id
            AND ato <> 'ISS'"
    );
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':id', $item_id);
    $stmt->bindParam(':os_id', $os_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao atualizar a descrição: ' . $e->getMessage()]);
}
