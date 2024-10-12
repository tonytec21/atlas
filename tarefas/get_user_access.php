<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Verifique se o nome de usuário está presente na sessão
if (isset($_SESSION['username'])) {
    $usuarioLogado = $_SESSION['username'];

    // Buscar nível de acesso e acesso adicional do usuário logado
    $sql = "SELECT nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = '$usuarioLogado' AND status = 'ativo'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        $nivelAcesso = $userData['nivel_de_acesso'];
        $acessoAdicional = $userData['acesso_adicional'];

        // Verifica se o nível de acesso é 'administrador'
        if ($nivelAcesso === 'administrador') {
            echo json_encode(['nivel_de_acesso' => 'administrador']);
        }
        // Caso o nível de acesso seja 'usuario', verificar se tem acesso ao 'Controle de Tarefas'
        elseif ($nivelAcesso === 'usuario') {
            // Converter o campo 'acesso_adicional' em uma lista (array) separada por vírgulas
            $acessos = array_map('trim', explode(',', $acessoAdicional));

            // Verifica se 'Controle de Tarefas' está presente na lista de acessos adicionais
            if (in_array('Controle de Tarefas', $acessos)) {
                echo json_encode(['nivel_de_acesso' => 'administrador']);
            } else {
                echo json_encode(['nivel_de_acesso' => 'usuario']);
            }
        } else {
            // Caso contrário, mantém o nível de acesso como está
            echo json_encode(['nivel_de_acesso' => $nivelAcesso]);
        }
    } else {
        echo json_encode(['error' => 'Usuário não encontrado ou inativo.']);
    }
} else {
    echo json_encode(['error' => 'Usuário não logado.']);
}
?>
