<?php
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/session_check.php');
checkSession(); // Confirma se o usuário está logado

function salvarAnexos($idRegistro) {
    $caminhoBase = __DIR__ . "/anexos/$idRegistro/";
    if (!is_dir($caminhoBase) && !mkdir($caminhoBase, 0777, true)) {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar diretório de anexos.']);
        exit;
    }

    $anexos = [];
    foreach ($_FILES['anexos']['tmp_name'] as $index => $tmpName) {
        $nomeArquivo = $_FILES['anexos']['name'][$index];
        $caminhoCompleto = $caminhoBase . $nomeArquivo;

        if (move_uploaded_file($tmpName, $caminhoCompleto)) {
            $anexos[] = "anexos/$idRegistro/$nomeArquivo";
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao mover o arquivo: ' . $nomeArquivo]);
            exit;
        }
    }
    return implode(';', $anexos);
}

// Validação da sessão
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Sessão não iniciada.']);
    exit;
}

// Função para gerar o próximo protocolo para a cidade
function gerarProtocolo($conn, $cidade) {
    $sigla = match ($cidade) {
        'São Roberto' => 'SR',
        'São Raimundo do Doca Bezerra' => 'SRDB',
        'Esperantinópolis' => 'EP',
        default => '',
    };

    if (empty($sigla)) {
        echo json_encode(['success' => false, 'message' => 'Cidade inválida.']);
        exit;
    }

    // Busca o último número de protocolo gerado para a cidade
    $query = "SELECT MAX(CAST(SUBSTRING(n_protocolo, 1, 3) AS UNSIGNED)) + 1 AS numero FROM triagem_comunitario WHERE cidade = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $cidade);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    $numero = str_pad($result['numero'] ?? 1, 3, '0', STR_PAD_LEFT); // Ex: 001, 002, 003...
    return $numero . $sigla; // Ex: 001SR, 002SRDB
}

// Recebendo os dados do formulário
$cidade = $_POST['cidade'] ?? '';
$nome_noivo = $_POST['nomeNoivo'] ?? '';
$novo_nome_noivo = $_POST['novoNomeNoivo'] ?? null;
$noivo_menor = $_POST['noivoMenor'] ?? 0;
$nome_noiva = $_POST['nomeNoiva'] ?? '';
$novo_nome_noiva = $_POST['novoNomeNoiva'] ?? null;
$noiva_menor = $_POST['noivaMenor'] ?? 0;
$funcionario = $_SESSION['username']; // Pega o funcionário logado

// Gerando o protocolo no momento do salvamento
$n_protocolo = gerarProtocolo($conn, $cidade);

// Preparando a query de inserção
$stmt = $conn->prepare("
    INSERT INTO triagem_comunitario (
        cidade, n_protocolo, nome_do_noivo, novo_nome_do_noivo, 
        noivo_menor, nome_da_noiva, novo_nome_da_noiva, noiva_menor, 
        funcionario
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro na preparação da query: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    'ssssissss',
    $cidade, $n_protocolo, $nome_noivo, $novo_nome_noivo,
    $noivo_menor, $nome_noiva, $novo_nome_noiva, $noiva_menor, $funcionario
);

// Executando a inserção
if ($stmt->execute()) {
    $idRegistro = $stmt->insert_id;
    $caminhoAnexos = salvarAnexos($idRegistro);

    // Atualizando o caminho dos anexos no banco de dados
    $stmtUpdate = $conn->prepare("UPDATE triagem_comunitario SET caminho_anexo = ? WHERE id = ?");
    if (!$stmtUpdate) {
        echo json_encode(['success' => false, 'message' => 'Erro na preparação da query de atualização: ' . $conn->error]);
        exit;
    }
    $stmtUpdate->bind_param('si', $caminhoAnexos, $idRegistro);
    $stmtUpdate->execute();

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao executar a query: ' . $stmt->error]);
}
?>
