<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $requerente = $_POST['requerente'];
    $qualificacao = $_POST['qualificacao'];
    $motivo = $_POST['motivo'];
    $peticao = $_POST['peticao'];
    $criado_por = $_POST['criado_por'];
    $data = date('Y-m-d');

    $sql = "INSERT INTO requerimentos (requerente, qualificacao, motivo, peticao, data, criado_por) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $requerente, $qualificacao, $motivo, $peticao, $data, $criado_por);

    if ($stmt->execute()) {
        echo "Requerimento salvo com sucesso!";
    } else {
        echo "Erro ao salvar requerimento: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
