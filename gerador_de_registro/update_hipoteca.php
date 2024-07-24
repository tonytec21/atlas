<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $titulo_cedula = isset($_POST['titulo_cedula']) ? $conn->real_escape_string($_POST['titulo_cedula']) : '';
    $n_cedula = isset($_POST['n_cedula']) ? $conn->real_escape_string($_POST['n_cedula']) : '';
    $emissao_cedula = isset($_POST['emissao_cedula']) ? $conn->real_escape_string($_POST['emissao_cedula']) : '';
    $valor_cedula = isset($_POST['valor_cedula']) ? str_replace(',', '.', str_replace('.', '', $conn->real_escape_string($_POST['valor_cedula']))) : '0.00';
    $credor = isset($_POST['credor']) ? $conn->real_escape_string($_POST['credor']) : '';
    $emitente = isset($_POST['emitente']) ? $conn->real_escape_string($_POST['emitente']) : '';
    $registro_garantia = isset($_POST['registro_garantia']) ? $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['registro_garantia']))) : '';
    $forma_de_pagamento = isset($_POST['forma_de_pagamento']) ? $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['forma_de_pagamento']))) : '';
    $matricula = isset($_POST['matricula']) ? $conn->real_escape_string($_POST['matricula']) : '';
    $data = isset($_POST['data']) ? $conn->real_escape_string($_POST['data']) : '';
    $funcionario = isset($_POST['funcionario']) ? $conn->real_escape_string($_POST['funcionario']) : '';

    $stmt = $conn->prepare("UPDATE registros_cedulas SET titulo_cedula = ?, n_cedula = ?, emissao_cedula = ?, valor_cedula = ?, credor = ?, emitente = ?, registro_garantia = ?, forma_de_pagamento = ?, matricula = ?, data = ?, funcionario = ? WHERE id = ?");
    $stmt->bind_param("sssssssssssi", $titulo_cedula, $n_cedula, $emissao_cedula, $valor_cedula, $credor, $emitente, $registro_garantia, $forma_de_pagamento, $matricula, $data, $funcionario, $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "<script>alert('Registro atualizado com sucesso!'); window.location.href = 'index.php';</script>";
    } else {
        echo "<script>alert('Nenhuma alteração foi feita.'); window.location.href = 'index.php';</script>";
    }

    $stmt->close();
    $conn->close();
}
?>
