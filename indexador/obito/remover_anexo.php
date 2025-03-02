<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);

    if ($id <= 0) {
        echo json_encode(["success" => false, "message" => "ID inválido."]);
        exit;
    }

    $query = "UPDATE indexador_obito_anexos SET status = 'R' WHERE id = ?";
    $stmt = $conn->prepare($query);

    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Anexo marcado como removido."]);
        } else {
            echo json_encode(["success" => false, "message" => "Erro ao atualizar status do anexo."]);
        }
        $stmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao preparar a consulta."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Requisição inválida."]);
}

$conn->close();
?>
