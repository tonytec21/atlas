<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oficios_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$numero = isset($_GET['numero']) ? $_GET['numero'] : '';

$stmt = $conn->prepare("SELECT * FROM oficios WHERE numero = ?");
$stmt->bind_param("s", $numero);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Ofício não encontrado.");
}

$oficioData = $result->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode($oficioData);
?>
