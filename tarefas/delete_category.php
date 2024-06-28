<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $sql = "DELETE FROM categorias WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        echo "Categoria excluída com sucesso!";
    } else {
        echo "Erro ao excluir categoria: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>
