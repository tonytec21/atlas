<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/base_calculo_lib.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];
    $ato = $_POST['ato'];
    $quantidade = $_POST['quantidade'];
    $desconto_legal = $_POST['desconto_legal'];
    $descricao = $_POST['descricao'];
    $emolumentos = $_POST['emolumentos'];
    $ferc = $_POST['ferc'];
    $fadep = $_POST['fadep'];
    $femp = $_POST['femp'];
    $ferrfis = isset($_POST['ferrfis']) ? $_POST['ferrfis'] : 0;
    $total = $_POST['total'];

    // ---------- BASE DE CÁLCULO DO ATO ----------
    // A tela valida antes de enviar, mas a checagem que vale é esta: um ato
    // de faixa gravado sem base trava a selagem depois, longe de quem lançou.
    $baseBruta = $_POST['base_de_calculo'] ?? '';
    $base_de_calculo = ($baseBruta === '' || $baseBruta === null) ? null : bc_valor($baseBruta);

    $faixaAto = bc_extrair_faixa($descricao);
    $ehIsento = stripos((string) $ato, '(isento)') !== false;

    if ($faixaAto && !$ehIsento) {
        $vb = bc_validar((float) $base_de_calculo, $faixaAto);
        if (!$vb['ok']) {
            echo json_encode(['error' => 'Ato ' . $ato . ': ' . $vb['mensagem']]);
            exit;
        }
    }

    if ($base_de_calculo !== null && $base_de_calculo <= 0) {
        $base_de_calculo = null;
    }

    try {
        $conn = getDatabaseConnection();

        // Inicia a transação
        $conn->beginTransaction();

        // Adiciona o item na tabela `ordens_de_servico_itens`
        bc_migrar($conn);

        $stmt = $conn->prepare("INSERT INTO ordens_de_servico_itens (ordem_servico_id, ato, quantidade, desconto_legal, descricao, emolumentos, ferc, fadep, femp, ferrfis, total, base_de_calculo) VALUES (:ordem_servico_id, :ato, :quantidade, :desconto_legal, :descricao, :emolumentos, :ferc, :fadep, :femp, :ferrfis, :total, :base_de_calculo)");
        $stmt->bindParam(':base_de_calculo', $base_de_calculo);
        $stmt->bindParam(':ordem_servico_id', $os_id);
        $stmt->bindParam(':ato', $ato);
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':desconto_legal', $desconto_legal);
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':emolumentos', $emolumentos);
        $stmt->bindParam(':ferc', $ferc);
        $stmt->bindParam(':fadep', $fadep);
        $stmt->bindParam(':femp', $femp);
        $stmt->bindParam(':ferrfis', $ferrfis);
        $stmt->bindParam(':total', $total);
        $stmt->execute();
        $novoItemId = $conn->lastInsertId();

        // Atualiza o total da OS na tabela `ordens_de_servico`
        $stmt = $conn->prepare("UPDATE ordens_de_servico SET total_os = total_os + :total WHERE id = :id");
        $stmt->bindParam(':total', $total);
        $stmt->bindParam(':id', $os_id);
        $stmt->execute();

        // Confirma a transação
        $conn->commit();

        echo json_encode(['success' => true, 'id' => isset($novoItemId) ? $novoItemId : null]);
    } catch (PDOException $e) {
        // Desfaz a transação em caso de erro
        $conn->rollBack();
        echo json_encode(['error' => 'Erro ao adicionar o item: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
?>