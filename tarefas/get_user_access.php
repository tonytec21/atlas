<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Verifique se o nome de usuário está presente na sessão
if (isset($_SESSION['username'])) {
    $usuarioLogado = $_SESSION['username'];

    // Buscar nível de acesso do usuário logado
    $sql = "SELECT nivel_de_acesso FROM funcionarios WHERE usuario = '$usuarioLogado' AND status = 'ativo'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        echo json_encode($userData); // Retorna o nível de acesso como JSON
    } else {
        echo json_encode(['error' => 'Usuário não encontrado ou inativo.']);
    }
} else {
    echo json_encode(['error' => 'Usuário não logado.']);
}
?>
