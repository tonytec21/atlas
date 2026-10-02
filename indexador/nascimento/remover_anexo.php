<?php require_once __DIR__ . '/../_core/legacy_guard.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include(__DIR__ . '/db_connection.php');

    session_start();
    $funcionario = $_SESSION['nome_funcionario']; // Nome do funcionário logado

    $id = $_POST['id'];
    $status = 'removido';

    $stmt = $conn->prepare("UPDATE indexador_nascimento_anexos SET status = ?, funcionario = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $funcionario, $id);

    if ($stmt->execute()) {
        echo json_encode(array('status' => 'success'));
    } else {
        echo json_encode(array('status' => 'error', 'message' => 'Erro ao remover o anexo'));
    }

    $stmt->close();
    $conn->close();
}
?>
