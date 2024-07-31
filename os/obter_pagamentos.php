<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection2.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $os_id = $_POST['os_id'];

    if (!isset($conn)) {
        die(json_encode(['error' => 'Erro ao conectar ao banco de dados']));
    }

    $stmt = $conn->prepare("SELECT * FROM pagamento_os WHERE ordem_de_servico_id = ?");
    $stmt->bind_param("i", $os_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pagamentos = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['pagamentos' => $pagamentos]);
} else {
    echo json_encode(['error' => 'Método inválido']);
}
