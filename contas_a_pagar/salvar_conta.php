<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

// Exibir todos os erros para depuração (somente em ambiente de desenvolvimento)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Limpa qualquer saída anterior
if (ob_get_length()) {
    ob_end_clean();
}

// Função para salvar o anexo
function salvarAnexo($id_conta) {
    $diretorio = __DIR__ . "/anexos/" . $id_conta . "/";
    if (!file_exists($diretorio)) {
        mkdir($diretorio, 0777, true);
    }

    if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] == 0) {
        $arquivo = $_FILES['anexo']['name'];
        $caminho = $diretorio . basename($arquivo);
        if (move_uploaded_file($_FILES['anexo']['tmp_name'], $caminho)) {
            return "anexos/" . $id_conta . "/" . $arquivo;
        }
    }
    return null;
}

header('Content-Type: application/json'); // Define o tipo de conteúdo da resposta como JSON

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Pega o nome do funcionário logado a partir da sessão
        if (isset($_SESSION['username'])) {
            $funcionario = $_SESSION['username'];
        } else {
            throw new Exception("Usuário não logado.");
        }

        $titulo = $_POST['titulo'];
        $valor = str_replace(',', '.', str_replace('.', '', $_POST['valor'])); // Formatação do valor para salvar no banco
        $data_vencimento = $_POST['data_vencimento'];
        $descricao = !empty($_POST['descricao']) ? $_POST['descricao'] : null;
        $recorrencia = $_POST['recorrencia'];
        $status = 'Pendente';

        // Insere os dados no banco de dados
        $stmt = $conn->prepare("INSERT INTO contas_a_pagar (titulo, valor, data_vencimento, descricao, recorrencia, funcionario, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            throw new Exception("Erro na preparação da query: " . $conn->error);
        }

        $stmt->bind_param('sdsssss', $titulo, $valor, $data_vencimento, $descricao, $recorrencia, $funcionario, $status);

        if (!$stmt->execute()) {
            throw new Exception("Erro ao executar inserção: " . $stmt->error);
        }

        $id_conta = $stmt->insert_id;
        $caminho_anexo = salvarAnexo($id_conta);
        if ($caminho_anexo) {
            $stmt = $conn->prepare("UPDATE contas_a_pagar SET caminho_anexo = ? WHERE id = ?");
            if ($stmt === false) {
                throw new Exception("Erro na preparação da query para o anexo: " . $conn->error);
            }

            $stmt->bind_param('si', $caminho_anexo, $id_conta);
            if (!$stmt->execute()) {
                throw new Exception("Erro ao atualizar caminho do anexo: " . $stmt->error);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Conta cadastrada com sucesso!']);
    } else {
        throw new Exception('Método HTTP inválido.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
