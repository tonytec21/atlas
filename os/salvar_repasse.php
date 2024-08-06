<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

header('Content-Type: application/json'); // Definir o cabeçalho como JSON

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $os_id = $_POST['os_id'];
    $cliente = $_POST['cliente'];
    $total_os = $_POST['total_os'];
    $total_repasse = $_POST['total_repasse'];
    $forma_repasse = $_POST['forma_repasse'];
    $data_os = $_POST['data_os'];
    $funcionario = $_POST['funcionario'];
    $status = 'ativo';
    $data_repasse = date('Y-m-d H:i:s');

    // Iniciar uma transação
    $conn->begin_transaction();

    try {
        // Inserir o repasse
        $repasse_query = $conn->prepare("INSERT INTO repasse_credor (ordem_de_servico_id, cliente, total_os, total_repasse, forma_repasse, data_repasse, data_os, funcionario, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $repasse_query->bind_param("issdsssss", $os_id, $cliente, $total_os, $total_repasse, $forma_repasse, $data_repasse, $data_os, $funcionario, $status);

        if (!$repasse_query->execute()) {
            throw new Exception("Erro ao salvar repasse no banco de dados.");
        }

        // Commit a transação
        $conn->commit();
        echo json_encode(['success' => true, 'total_repasse' => $total_repasse]);
    } catch (Exception $e) {
        // Rollback em caso de erro
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método de solicitação inválido.']);
}
?>
