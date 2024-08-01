<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

try {
    $titulo = $_POST['titulo'];
    $valor_saida = str_replace(',', '.', str_replace('.', '', $_POST['valor_saida']));
    $forma_de_saida = $_POST['forma_de_saida'];
    $data = $_POST['data_saida'];
    $funcionario = $_POST['funcionario_saida'];

    $conn = getDatabaseConnection();

    $sql = 'INSERT INTO saidas_despesas (titulo, valor_saida, forma_de_saida, data, funcionario) VALUES (:titulo, :valor_saida, :forma_de_saida, :data, :funcionario)';
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':titulo', $titulo);
    $stmt->bindParam(':valor_saida', $valor_saida);
    $stmt->bindParam(':forma_de_saida', $forma_de_saida);
    $stmt->bindParam(':data', $data);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
