<?php
// Iniciar a sessão e verificar se o usuário está logado
include(__DIR__ . '/session_check.php');
checkSession(); // Verifica se o usuário está logado
date_default_timezone_set('America/Sao_Paulo');

// Conectar ao banco de dados
include(__DIR__ . '/db_connection_guia.php');

// Verificar se a conexão com o banco foi estabelecida
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Erro ao conectar ao banco de dados.']);
    exit;
}

try {
    // Obter o username da sessão
    $username = $_SESSION['username'];

    // Buscar o nome completo do usuário na tabela "funcionarios"
    $stmt = $conn->prepare("SELECT nome_completo FROM funcionarios WHERE usuario = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nome_completo = $row['nome_completo'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Capturar os dados do formulário
        $cliente = trim($_POST['cliente']);
        $documentoApresentante = trim($_POST['documentoApresentante']);
        $documentosRecebidos = trim($_POST['documentosRecebidos']);
        $observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : '';

        // Verificar se os campos obrigatórios foram preenchidos
        if (empty($cliente) || empty($documentosRecebidos)) {
            echo json_encode(['success' => false, 'message' => 'Campos obrigatórios não preenchidos.']);
            exit;
        }

        $dataAtual = date('Y-m-d H:i:s');

        // Inserir a guia no banco de dados
        $stmt = $conn->prepare(
            "INSERT INTO guia_de_recebimento 
            (cliente, documento_apresentante, funcionario, data_recebimento, documentos_recebidos, observacoes, data_cadastro) 
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssss", $cliente, $documentoApresentante, $nome_completo, $dataAtual, $documentosRecebidos, $observacoes, $dataAtual);

        if ($stmt->execute()) {
            $guiaId = $conn->insert_id;
            $configPath = __DIR__ . '/../style/configuracao.json';

            if (file_exists($configPath)) {
                $config = json_decode(file_get_contents($configPath), true);

                if ($config['timbrado'] === 'S') {
                    $url = "../guia_de_recebimento/guia_recebimento.php?id={$guiaId}";
                } elseif ($config['timbrado'] === 'N') {
                    $url = "../guia_de_recebimento/guia-recebimento.php?id={$guiaId}";
                } else {
                    echo json_encode(['success' => false, 'message' => 'Valor de timbrado inválido.']);
                    exit;
                }

                // Responder com a URL para abrir na nova aba
                echo json_encode(['success' => true, 'url' => $url]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Arquivo de configuração não encontrado.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao criar Guia de Recebimento: ' . $conn->error]);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Método de requisição inválido.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro inesperado: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
