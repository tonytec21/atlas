<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['titulo'])) {
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $sql = "INSERT INTO categorias (titulo, status) VALUES (?, 'ativo')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $titulo);

    if ($stmt->execute()) {
        echo "Categoria salva com sucesso!";
    } else {
        echo "Erro ao salvar categoria: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>
