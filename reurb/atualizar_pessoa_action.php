<?php
include(__DIR__ . '/db_connection.php');

// Coleta os dados do formulário
$id = $_POST['id'] ?? null;
$nome = strtoupper($_POST['nome'] ?? null);
$dataNascimento = $_POST['dataNascimento'] ?? null;
$cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$rg = $_POST['rg'] ?? null;
$dataEmissaoRg = $_POST['dataEmissaoRg'] ?? null;
$orgaoEmissorRg = mb_strtoupper($_POST['orgaoEmissorRg'] ?? null, 'UTF-8');
$nacionalidade = mb_strtolower($_POST['nacionalidade'] ?? null, 'UTF-8');
$naturalidade = $_POST['naturalidade'] ?? null;
$profissao = mb_strtolower($_POST['profissao'] ?? null, 'UTF-8');
$estadoCivil = mb_strtolower($_POST['estadoCivil'] ?? null, 'UTF-8');
$regimeBens = $_POST['regimeBens'] ?? null;
$filiacao = mb_strtoupper($_POST['filiacao'] ?? null, 'UTF-8');
$cep = preg_replace('/\D/', '', $_POST['cep'] ?? '');
$logradouro = $_POST['logradouro'] ?? null;
$quadra = $_POST['quadra'] ?? null;
$numero = $_POST['numero'] ?? null;
$bairro = $_POST['bairro'] ?? null;
$cidade = $_POST['cidade'] ?? null;

// Verifica se o ID foi enviado
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID não informado.']);
    exit;
}

// Verifica se há campos obrigatórios faltando
if (!$nome || !$dataNascimento || !$cpf) {
    echo json_encode(['success' => false, 'message' => 'Campos obrigatórios estão faltando.']);
    exit;
}

// Prepara a consulta para atualizar os dados
$stmt = $conn->prepare("
    UPDATE cadastro_de_pessoas 
    SET 
        nome = ?, 
        data_de_nascimento = ?, 
        cpf = ?, 
        rg = ?, 
        data_emissao_rg = ?, 
        orgao_emissor_rg = ?, 
        nacionalidade = ?, 
        naturalidade = ?, 
        profissao = ?, 
        estado_civil = ?, 
        regime_de_bens = ?, 
        filiacao = ?, 
        logradouro = ?, 
        quadra = ?, 
        numero = ?, 
        bairro = ?, 
        cidade = ?, 
        cep = ?
    WHERE id = ?
");

// Verifica se a preparação da consulta teve sucesso
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro na preparação da consulta SQL: ' . $conn->error]);
    exit;
}

// Vincula os parâmetros
$stmt->bind_param(
    "ssssssssssssssssssi",
    $nome,
    $dataNascimento,
    $cpf,
    $rg,
    $dataEmissaoRg,
    $orgaoEmissorRg,
    $nacionalidade,
    $naturalidade,
    $profissao,
    $estadoCivil,
    $regimeBens,
    $filiacao,
    $logradouro,
    $quadra,
    $numero,
    $bairro,
    $cidade,
    $cep,
    $id
);

// Executa a consulta e verifica o resultado
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Dados atualizados com sucesso.']);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao atualizar os dados no banco de dados: ' . $stmt->error
    ]);
}

// Fecha a consulta e a conexão
$stmt->close();
$conn->close();
