<?php
include(__DIR__ . '/../session_check.php');
checkSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = $_POST['numero'];
    $tratamento = $_POST['tratamento'];
    $destinatario = $_POST['destinatario'];
    $cargo = $_POST['cargo'];
    $assunto = $_POST['assunto'];
    $corpo = $_POST['corpo'];
    $assinante = $_POST['assinante'];
    $cargo_assinante = $_POST['cargo_assinante'];
    $data = $_POST['data'];

    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "oficios_db";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Falha na conexão: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("UPDATE oficios SET tratamento = ?, destinatario = ?, cargo = ?, assunto = ?, corpo = ?, assinante = ?, cargo_assinante = ?, data = ? WHERE numero = ?");
    $stmt->bind_param("sssssssss", $tratamento, $destinatario, $cargo, $assunto, $corpo, $assinante, $cargo_assinante, $data, $numero);

    if ($stmt->execute()) {
        echo "<script>alert('Ofício atualizado com sucesso!'); window.location.href = 'index.php';</script>";
    } else {
        echo "<script>alert('Erro ao atualizar o ofício.'); window.location.href = 'index.php';</script>";
    }

    $stmt->close();
    $conn->close();
}
?>
