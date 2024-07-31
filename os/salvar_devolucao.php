<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifique se a conexão está definida
    if (!isset($conn)) {
        die(json_encode(['error' => 'Erro ao conectar ao banco de dados']));
    }

    $os_id = $_POST['os_id'];
    $cliente = $_POST['cliente'];
    $total_os = $_POST['total_os'];
    $total_devolucao = $_POST['total_devolucao'];
    $forma_devolucao = $_POST['forma_devolucao'];
    $funcionario = $_POST['funcionario'];
    $status = 'Devolvido';
    $data_devolucao = date('Y-m-d H:i:s');

    $query = $conn->prepare("INSERT INTO devolucao_os (ordem_de_servico_id, cliente, total_os, total_devolucao, forma_devolucao, data_devolucao, funcionario, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $query->bind_param("issdssss", $os_id, $cliente, $total_os, $total_devolucao, $forma_devolucao, $data_devolucao, $funcionario, $status);

    if ($query->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Erro ao salvar devolução: ' . $conn->error]);
    }

    $query->close();
    $conn->close();
}
?>
