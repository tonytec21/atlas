<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Verifica se o arquivo foi enviado
if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == 0) {
    $id_conta = $_POST['id_conta'];
    $comprovante = $_FILES['comprovante'];
    $nomeArquivo = basename($comprovante['name']);
    
    // Define o diretório de destino
    $diretorioDestino = __DIR__ . "/anexos/{$id_conta}/comprovantes/";
    if (!is_dir($diretorioDestino)) {
        mkdir($diretorioDestino, 0777, true);
    }

    // Define o caminho completo do arquivo
    $caminhoCompleto = $diretorioDestino . $nomeArquivo;

    // Move o arquivo para o diretório de destino
    if (move_uploaded_file($comprovante['tmp_name'], $caminhoCompleto)) {
        // Salva o caminho no banco de dados
        $caminhoComprovante = "anexos/{$id_conta}/comprovantes/{$nomeArquivo}";
        
        // Verifique se a chave "username" está presente na sessão
        if (isset($_SESSION['username'])) {
            $funcionario = $_SESSION['username'];
        } else {
            // Trate o caso onde o usuário não está logado ou não foi definido corretamente
            echo json_encode(['success' => false, 'message' => 'Erro: Usuário não autenticado.']);
            exit;
        }

        $status = 'Enviado';

        $sql = "INSERT INTO contas_pagas_comprovante (id_conta, caminho_comprovante, funcionario, data, status) 
                VALUES (?, ?, ?, NOW(), ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isss', $id_conta, $caminhoComprovante, $funcionario, $status);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar no banco de dados.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao mover o arquivo.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado.']);
}
?>
