<?php
/**
 * Marca um item da OS como ISENTO: zera os valores e anexa " (isento)" ao ato.
 * (Espelha o comportamento do "Ato Isento" do criar_os.php, agora persistindo no banco.)
 */
header('Content-Type: application/json; charset=utf-8');
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método inválido']);
    exit;
}

$os_id   = isset($_POST['os_id'])   ? $_POST['os_id']   : null;
$item_id = isset($_POST['item_id']) ? $_POST['item_id'] : null;

if (!$os_id || !$item_id) {
    echo json_encode(['error' => 'Parâmetros inválidos.']);
    exit;
}

try {
    $conn = getDatabaseConnection();

    // Busca o ato atual do item
    $sel = $conn->prepare(
        "SELECT ato FROM ordens_de_servico_itens WHERE id = :id AND ordem_servico_id = :os_id"
    );
    $sel->bindParam(':id', $item_id);
    $sel->bindParam(':os_id', $os_id);
    $sel->execute();
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['error' => 'Item não encontrado.']);
        exit;
    }

    $ato = trim($row['ato']);

    // O ISS é automático/dinâmico — não pode ser isento
    if (strtoupper($ato) === 'ISS') {
        echo json_encode(['error' => 'O ISS não pode ser marcado como isento.']);
        exit;
    }

    // Anexa " (isento)" se ainda não houver
    if (stripos($ato, '(isento)') === false) {
        $ato = $ato . ' (isento)';
    }

    // Zera os valores e grava o ato marcado
    $upd = $conn->prepare(
        "UPDATE ordens_de_servico_itens
            SET emolumentos = 0, ferc = 0, fadep = 0, femp = 0, ferrfis = 0, total = 0, ato = :ato
          WHERE id = :id AND ordem_servico_id = :os_id AND ato <> 'ISS'"
    );
    $upd->bindParam(':ato', $ato);
    $upd->bindParam(':id', $item_id);
    $upd->bindParam(':os_id', $os_id);
    $upd->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao marcar o ato como isento: ' . $e->getMessage()]);
}
