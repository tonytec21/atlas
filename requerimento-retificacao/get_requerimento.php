<?php
include(__DIR__ . '/db_connection.php');

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM requerimentos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $requerimento = $result->fetch_assoc();
        echo json_encode($requerimento);
    } else {
        echo json_encode(['error' => 'Requerimento não encontrado']);
    }

    $stmt->close();
} else {
    echo json_encode(['error' => 'ID do requerimento não fornecido']);
}

$conn->close();
?>

