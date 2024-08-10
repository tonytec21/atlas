<?php
include(__DIR__ . '/db_connection.php');

$id_nascimento = $_GET['id_nascimento'];

$query = "SELECT * FROM indexador_nascimento_anexos WHERE id_nascimento = ? AND status = 'ativo'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_nascimento);
$stmt->execute();
$result = $stmt->get_result();

$attachments = array();
while ($row = $result->fetch_assoc()) {
    $attachments[] = $row;
}

echo json_encode($attachments);

$stmt->close();
$conn->close();
?>
