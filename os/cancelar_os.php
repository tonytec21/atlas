<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

if (!isset($conn)) {
    die(json_encode(['success' => false, 'error' => 'Erro ao conectar ao banco de dados']));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id  = isset($_POST['os_id']) ? (int)$_POST['os_id'] : 0;
    $motivo = trim($_POST['motivo'] ?? '');

    if ($os_id <= 0) {
        die(json_encode(['success' => false, 'error' => 'Ordem de Serviço inválida.']));
    }
    // Motivo do cancelamento é OBRIGATÓRIO
    if ($motivo === '') {
        die(json_encode(['success' => false, 'error' => 'Informe o motivo do cancelamento.']));
    }

    $usuario = $_SESSION['username'] ?? 'sistema';

    // ===== Garante colunas de cancelamento na tabela ordens_de_servico =====
    $c1 = $conn->query("SHOW COLUMNS FROM ordens_de_servico LIKE 'motivo_cancelamento'");
    if ($c1 && $c1->num_rows == 0) {
        $conn->query("ALTER TABLE ordens_de_servico ADD COLUMN motivo_cancelamento VARCHAR(1000) DEFAULT NULL");
    }
    $c2 = $conn->query("SHOW COLUMNS FROM ordens_de_servico LIKE 'cancelado_por'");
    if ($c2 && $c2->num_rows == 0) {
        $conn->query("ALTER TABLE ordens_de_servico ADD COLUMN cancelado_por VARCHAR(120) DEFAULT NULL");
    }
    $c3 = $conn->query("SHOW COLUMNS FROM ordens_de_servico LIKE 'cancelado_em'");
    if ($c3 && $c3->num_rows == 0) {
        $conn->query("ALTER TABLE ordens_de_servico ADD COLUMN cancelado_em DATETIME DEFAULT NULL");
    }

    // Atualiza o status da O.S. para "Cancelado" e registra o motivo/autor/data
    $sql = "UPDATE ordens_de_servico
               SET status = 'Cancelado',
                   motivo_cancelamento = ?,
                   cancelado_por = ?,
                   cancelado_em = NOW()
             WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $motivo, $usuario, $os_id);
    if (!$stmt->execute()) {
        die(json_encode(['success' => false, 'error' => 'Erro ao atualizar status da ordem de serviço: ' . $stmt->error]));
    }

    // Atualiza o status dos itens relacionados
    $sql = "UPDATE ordens_de_servico_itens SET status = 'Cancelado' WHERE ordem_servico_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $os_id);
    if (!$stmt->execute()) {
        die(json_encode(['success' => false, 'error' => 'Erro ao atualizar status dos itens da ordem de serviço: ' . $stmt->error]));
    }

    // ===== Rastreio: cancela na API enviando o motivo como observação (best-effort) =====
    try {
        require_once(__DIR__ . '/../pedidos_certidao/os_rastreio_lib.php');
        $pdo = os_rastreio_pdo();
        os_rastreio_cancelar($pdo, $os_id, $motivo, $usuario);
    } catch (Throwable $eR) {
        error_log('[cancelar_os][rastreio] ' . $eR->getMessage());
    }

    echo json_encode(['success' => true]);
}
?>
