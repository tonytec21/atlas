<?php
include(__DIR__ . '/db_connection.php');

$recent = isset($_GET['recent']) ? true : false;
$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM indexador_nascimento WHERE id = ? AND status = 'ativo'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $registry = $result->fetch_assoc();
    echo json_encode($registry);
} else {
    $query = "SELECT * FROM indexador_nascimento WHERE status = 'ativo' ";
    if ($recent) {
        $query .= "ORDER BY data_cadastro DESC LIMIT 10";
    }

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    $registries = array();
    while ($row = $result->fetch_assoc()) {
        $registries[] = $row;
    }

    echo json_encode($registries);
}

$stmt->close();
$conn->close();
?>
