<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include(__DIR__ . '/db_connection.php');

    // Desativar exibição de erros no navegador e habilitar log de erros
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error_log.txt');

    // Obter o nome do funcionário da sessão
    session_start();
    $funcionario = $_SESSION['nome_funcionario']; // Nome do funcionário logado

    // Captura os dados sem fazer qualquer conversão de caracteres especiais
    $termo = $_POST['termo'];
    $livro = $_POST['livro'];
    $folha = $_POST['folha'];
    $data_registro = $_POST['data_registro'];
    $data_nascimento = $_POST['data_nascimento'];
    $nome_registrado = $_POST['nome_registrado'];
    $nome_pai = $_POST['nome_pai'];
    $nome_mae = $_POST['nome_mae'];
    $status = 'ativo';

    // Verificar se já existe um registro com o mesmo termo, livro, folha e data de registro
    $stmt = $conn->prepare("SELECT nome_registrado FROM indexador_nascimento WHERE termo = ? AND livro = ? AND folha = ? AND data_registro = ? AND status = 'ativo'");
    $stmt->bind_param("ssss", $termo, $livro, $folha, $data_registro);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($nome_registrado_existente);
        $stmt->fetch();
        echo json_encode([
            'status' => 'duplicate',
            'message' => 'Já existe um registro com o mesmo livro, folha, termo e data de registro.',
            'nome_registrado' => $nome_registrado_existente
        ]);
        exit;
    }

    $stmt->close();

    // Inserir registro no banco de dados
    $stmt = $conn->prepare("INSERT INTO indexador_nascimento (termo, livro, folha, data_registro, data_nascimento, nome_registrado, nome_pai, nome_mae, funcionario, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssss", $termo, $livro, $folha, $data_registro, $data_nascimento, $nome_registrado, $nome_pai, $nome_mae, $funcionario, $status);

    if ($stmt->execute()) {
        $last_id = $stmt->insert_id;

        // Mover anexos temporários para o diretório final e salvar no banco de dados
        if (!empty($_POST['arquivo_pdf_paths'])) {
            foreach ($_POST['arquivo_pdf_paths'] as $temp_file_path) {
                $dir = 'anexos/' . $last_id . '/';
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                $file_name = basename($temp_file_path);
                $final_file_path = $dir . $file_name;
                if (rename($temp_file_path, $final_file_path)) {
                    $stmt_anexo = $conn->prepare("INSERT INTO indexador_nascimento_anexos (id_nascimento, caminho_anexo, funcionario, status) VALUES (?, ?, ?, ?)");
                    $stmt_anexo->bind_param("isss", $last_id, $final_file_path, $funcionario, $status);
                    $stmt_anexo->execute();
                }
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Registro salvo com sucesso!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar o registro: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>
