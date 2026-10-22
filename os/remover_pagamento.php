<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');
require_once __DIR__ . '/pagamento_exclusao_lib.php';
/* AUDITORIA O.S. — registra o antes/depois desta operação */
require_once __DIR__ . '/auditoria_os_lib.php';
osaud_monitorar('pagamento_removido', ['pagamento_id' => $_POST['pagamento_id'] ?? 0]);
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pagamento_id = (int) ($_POST['pagamento_id'] ?? 0);

    if (!isset($conn)) {
        die(json_encode(['error' => 'Erro ao conectar ao banco de dados']));
    }

    // Proteção: só quem lançou ou o Oficial, no mesmo dia, sem ato liquidado e com o caixa de quem lançou ainda aberto
    $st = $conn->prepare('SELECT id, ordem_de_servico_id, funcionario, data_pagamento, forma_de_pagamento FROM pagamento_os WHERE id = ?');
    $st->bind_param('i', $pagamento_id);
    $st->execute();
    $pag = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$pag) {
        echo json_encode(['error' => 'Pagamento não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $regra = os_pagto_exclusao($conn, $pag, (string) $_SESSION['username']);
    if (!$regra['pode']) {
        echo json_encode(['error' => $regra['motivo'], 'bloqueado' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM pagamento_os WHERE id = ?");
    $stmt->bind_param("i", $pagamento_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Erro ao remover pagamento: ' . $conn->error]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
