<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];
    $cliente = $_POST['cliente'];
    $total_os = $_POST['total_os'];
    $funcionario = $_POST['funcionario'];
    $forma_pagamento = $_POST['forma_pagamento'];
    $valor_pagamento = $_POST['valor_pagamento'];

    // Verifique se a conexão está definida
    if (!isset($conn)) {
        die(json_encode(['error' => 'Erro ao conectar ao banco de dados']));
    }

    try {
        $stmt = $conn->prepare("INSERT INTO pagamento_os (ordem_de_servico_id, cliente, total_os, total_pagamento, forma_de_pagamento, data_pagamento, funcionario, status) VALUES (?, ?, ?, ?, ?, NOW(), ?, 'pago')");
        $stmt->bind_param("isddss", $os_id, $cliente, $total_os, $valor_pagamento, $forma_pagamento, $funcionario);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro ao salvar pagamento: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
