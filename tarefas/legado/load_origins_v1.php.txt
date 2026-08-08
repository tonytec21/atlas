<?php
include(__DIR__ . '/db_connection.php');

$sql = "SELECT id, titulo FROM origem WHERE status = 'ativo'";
$result = $conn->query($sql);

$origins = array();
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $origins[] = $row;
    }
}

echo json_encode($origins);
$conn->close();
?>
