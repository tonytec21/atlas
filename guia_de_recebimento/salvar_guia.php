<?php
// Iniciar a sessão e verificar se o usuário está logado
include(__DIR__ . '/session_check.php');
checkSession(); // Verifica se o usuário está logado
date_default_timezone_set('America/Sao_Paulo');

// Conectar ao banco de dados
include(__DIR__ . '/db_connection.php');

// Obter o username da sessão
$username = $_SESSION['username'];

// Buscar o nome completo do usuário na tabela "funcionarios"
$stmt = $conn->prepare("SELECT nome_completo FROM funcionarios WHERE usuario = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

// Verificar se o usuário foi encontrado
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $nome_completo = $row['nome_completo']; // Captura o nome completo do usuário
} else {
    echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado.']);
    exit;
}

// Verificar se os dados foram enviados via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capturar os dados do formulário
    $cliente = $_POST['cliente'];
    $documentoApresentante = $_POST['documentoApresentante']; // Novo campo
    $documentosRecebidos = $_POST['documentosRecebidos'];
    $observacoes = isset($_POST['observacoes']) ? $_POST['observacoes'] : '';

    // Capturar a data e hora atual para "data_recebimento" e "data_cadastro"
    $dataAtual = date('Y-m-d H:i:s');

    // Preparar a consulta SQL para inserção, agora com o campo documento_apresentante
    $stmt = $conn->prepare("INSERT INTO guia_de_recebimento (cliente, documento_apresentante, funcionario, data_recebimento, documentos_recebidos, observacoes, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $cliente, $documentoApresentante, $nome_completo, $dataAtual, $documentosRecebidos, $observacoes, $dataAtual);

    // Executar a consulta e verificar se foi bem-sucedida
    if ($stmt->execute()) {
        // Obter o ID da guia recém-criada
        $guiaId = $conn->insert_id;

        // Carregar o arquivo JSON para verificar o valor de "timbrado"
        $configPath = __DIR__ . '/../style/configuracao.json';
        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);

            // Verificar o valor de "timbrado" e determinar a URL correta
            if ($config['timbrado'] === 'S') {
                $url = "guia_recebimento.php?id={$guiaId}";
            } elseif ($config['timbrado'] === 'N') {
                $url = "guia-recebimento.php?id={$guiaId}";
            } else {
                echo json_encode(['success' => false, 'message' => 'Valor de timbrado inválido.']);
                exit;
            }

            // Retornar uma resposta JSON com a URL correta
            echo json_encode(['success' => true, 'url' => $url]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Arquivo de configuração não encontrado.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar Guia de Recebimento: ' . $conn->error]);
    }

    // Fechar a conexão e a instrução
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Método de requisição inválido.']);
}
?>
