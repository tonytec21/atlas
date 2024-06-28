<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'], $_POST['titulo'])) {
    $id = $_POST['id'];
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $sql = "UPDATE categorias SET titulo = ? WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $titulo, $id);

    if ($stmt->execute()) {
        echo "Categoria atualizada com sucesso!";
    } else {
        echo "Erro ao atualizar categoria: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>
