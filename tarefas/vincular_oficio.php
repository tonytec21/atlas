<?php
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $taskToken = $_POST['taskToken'];
    $numeroOficio = $_POST['numeroOficio'];

    $sql = "UPDATE tarefas SET numero_oficio = ? WHERE token = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $numeroOficio, $taskToken);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>
