<?php
include(__DIR__ . '/db_connection.php');

$sql = "SELECT id, titulo FROM categorias WHERE status = 'ativo'";
$result = $conn->query($sql);

$categories = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

echo json_encode($categories);
$conn->close();
?>
