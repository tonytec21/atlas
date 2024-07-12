<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $titulo_cedula = $conn->real_escape_string($_POST['titulo_cedula']);
    $n_cedula = $conn->real_escape_string($_POST['n_cedula']);
    $emissao_cedula = $conn->real_escape_string($_POST['emissao_cedula']);
    $vencimento_cedula = $conn->real_escape_string($_POST['vencimento_cedula']);
    $valor_cedula = str_replace(',', '.', str_replace('.', '', $conn->real_escape_string($_POST['valor_cedula'])));
    $credor = $conn->real_escape_string($_POST['credor']);
    $emitente = $conn->real_escape_string($_POST['emitente']);
    $registro_garantia = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['registro_garantia'])));
    $forma_de_pagamento = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['forma_de_pagamento'])));
    $vencimento_antecipado = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['vencimento_antecipado'])));
    $juros = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['juros'])));
    $matricula = $conn->real_escape_string($_POST['matricula']);
    $data = $conn->real_escape_string($_POST['data']);
    $funcionario = $conn->real_escape_string($_POST['funcionario']);
    $avalista = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['avalista'])));
    $imovel_localizacao = $conn->real_escape_string(trim(preg_replace('/\r\n|\r|\n/', '', $_POST['imovel_localizacao'])));

    $stmt = $conn->prepare("UPDATE registros_cedulas SET titulo_cedula = ?, n_cedula = ?, emissao_cedula = ?, vencimento_cedula = ?, valor_cedula = ?, credor = ?, emitente = ?, registro_garantia = ?, forma_de_pagamento = ?, vencimento_antecipado = ?, juros = ?, matricula = ?, data = ?, funcionario = ?, avalista = ?, imovel_localizacao = ? WHERE id = ?");
    $stmt->bind_param("ssssssssssssssssi", $titulo_cedula, $n_cedula, $emissao_cedula, $vencimento_cedula, $valor_cedula, $credor, $emitente, $registro_garantia, $forma_de_pagamento, $vencimento_antecipado, $juros, $matricula, $data, $funcionario, $avalista, $imovel_localizacao, $id);
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
