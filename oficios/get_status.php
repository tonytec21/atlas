<?php
include(__DIR__ . '/../session_check.php');
checkSession();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $numero = isset($_GET['numero']) ? $_GET['numero'] : '';

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "oficios_db";

    $conn = new mysqli($servername, $username, $password, $dbname); /* atlas-auditoria */ if (is_file(__DIR__ . '/../auditoria/hook.php')) { include_once __DIR__ . '/../auditoria/hook.php'; atlas_aud_conexao($conn); }
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("SELECT status FROM oficios WHERE numero = ?");
    $stmt->bind_param("s", $numero);
    $stmt->execute();
    $stmt->bind_result($status);
    $stmt->fetch();

    echo json_encode(["status" => $status]);

    $stmt->close();
    $conn->close();
}
?>
