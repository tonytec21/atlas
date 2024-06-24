<?php
include(__DIR__ . '/../session_check.php');
checkSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = isset($_POST['numero']) ? $_POST['numero'] : '';

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "oficios_db";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("UPDATE oficios SET status = 1 WHERE numero = ?");
    $stmt->bind_param("s", $numero);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "Ofício não encontrado ou já travado."]);
    }

    $stmt->close();
    $conn->close();
}
?>
