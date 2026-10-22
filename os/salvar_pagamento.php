<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');
require_once __DIR__ . '/pagamento_observacao_config.php';
require_once __DIR__ . '/caixa_pagamento_lib.php';
/* AUDITORIA O.S. — registra o antes/depois desta operação */
require_once __DIR__ . '/auditoria_os_lib.php';
osaud_monitorar('pagamento_lancado', ['os_id' => $_POST['os_id'] ?? 0]);
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];
    $cliente = $_POST['cliente'];
    $total_os = $_POST['total_os'];
    // O pagamento entra sempre no caixa de quem está logado (o valor enviado pela tela é ignorado)
    $funcionario = (string) $_SESSION['username'];
    $forma_pagamento = $_POST['forma_pagamento'];
    $valor_pagamento = $_POST['valor_pagamento'];
    $observacao = po_obs_normalizar($_POST['observacao'] ?? '');

    // Verifique se a conexão está definida
    if (!isset($conn)) {
        die(json_encode(['error' => 'Erro ao conectar ao banco de dados']));
    }

    // Bloqueio: sem o caixa do dia aberto, não entra pagamento (exceto "Isento de Pagamento")
    if (!os_caixa_forma_isenta((string) $forma_pagamento)) {
        try {
            $sitCaixa = os_caixa_situacao($conn, $funcionario);
        } catch (Throwable $e) {
            $sitCaixa = ['ok' => true];
        }
        if (empty($sitCaixa['ok'])) {
            echo json_encode(['error' => $sitCaixa['mensagem'], 'caixa_bloqueio' => $sitCaixa['motivo']], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    try {
        // Garante a coluna de observação (migração automática, executada uma única vez)
        $temObservacao = po_obs_garantir_coluna($conn);

        if ($temObservacao) {
            $stmt = $conn->prepare("INSERT INTO pagamento_os (ordem_de_servico_id, cliente, total_os, total_pagamento, forma_de_pagamento, data_pagamento, funcionario, status, observacao) VALUES (?, ?, ?, ?, ?, NOW(), ?, 'pago', ?)");
            $obsParam = ($observacao === '') ? null : $observacao;
            $stmt->bind_param("isddsss", $os_id, $cliente, $total_os, $valor_pagamento, $forma_pagamento, $funcionario, $obsParam);
        } else {
            $observacao = '';
            $stmt = $conn->prepare("INSERT INTO pagamento_os (ordem_de_servico_id, cliente, total_os, total_pagamento, forma_de_pagamento, data_pagamento, funcionario, status) VALUES (?, ?, ?, ?, ?, NOW(), ?, 'pago')");
            $stmt->bind_param("isddss", $os_id, $cliente, $total_os, $valor_pagamento, $forma_pagamento, $funcionario);
        }

        $stmt->execute();

        echo json_encode([
            'success' => true,
            'pagamento_id' => $stmt->insert_id,
            'data_pagamento' => date('Y-m-d H:i:s'),
            'observacao' => $observacao
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Erro ao salvar pagamento: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Método inválido']);
}
