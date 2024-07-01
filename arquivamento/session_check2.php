<?php
$stmt = $conn->prepare("SELECT nome_completo FROM funcionarios WHERE usuario = ? AND senha = ?");
$stmt->bind_param("ss", $usuario, $senha);
$stmt->execute();
$stmt->bind_result($nome_completo);
$stmt->fetch();
$stmt->close();

if ($nome_completo) {
    $_SESSION['usuario'] = $usuario;
    $_SESSION['nome_completo'] = $nome_completo;
} else {
    // handle login failure
}

?>
