<?php
include(__DIR__ . '/session_check.php');
include(__DIR__ . '/db_connection.php');
checkSession();

$id = $_POST['id'] ?? '';

if ($id) {
    $stmt = $conn->prepare("DELETE FROM registros_cedulas WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "Cédula excluída com sucesso";
    } else {
        echo "Erro ao excluir a cédula";
    }
    $stmt->close();
}

$conn->close();
?>
