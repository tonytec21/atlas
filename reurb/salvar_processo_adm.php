<?php
header('Content-Type: application/json');

// Inclui os arquivos necessários
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/session_check.php');

// Ativa a exibição de erros apenas no log (para desenvolvimento, não em produção)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Verifica a sessão
checkSession();

// Resposta padrão
$response = ['success' => false, 'message' => 'Erro ao salvar os dados.'];

try {
    // Captura os dados enviados
    $processoAdm = $_POST['processoAdm'] ?? null;
    $dataDePublicacao = $_POST['dataDePublicacao'] ?? null;
    $classificacaoIndividual = $_POST['classificacaoIndividual'] ?? null;
    $direitoRealOutorgado = $_POST['direitoRealOutorgado'] ?? null;
    $municipio = $_POST['municipio'] ?? null;
    $qualificacaoMunicipio = $_POST['qualificacaoMunicipio'] ?? null;
    $representante = mb_strtoupper($_POST['representante'] ?? null, 'UTF-8');
    $qualificacaoRepresentante = $_POST['qualificacaoRepresentante'] ?? null;
    $edital = $_POST['edital'] ?? null;
    $dataEdital = $_POST['dataEdital'] ?? null;
    $responsavelTecnico = mb_strtoupper($_POST['responsavelTecnico'] ?? null, 'UTF-8');
    $qualificacaoResponsavelTecnico = $_POST['qualificacaoResponsavelTecnico'] ?? null;
    $matriculaMae = $_POST['matriculaMae'] ?? null;
    $oficialDoRegistro = $_POST['oficialDoRegistro'] ?? null;
    $cargoOficial = $_POST['cargoOficial'] ?? null;
    $funcionario = 'Sistema'; // Ajuste para capturar o nome do usuário logado
    $status = 'ativo';

    // Valida os campos obrigatórios
    if (
        !$processoAdm || !$dataDePublicacao || !$classificacaoIndividual || !$direitoRealOutorgado ||
        !$municipio || !$representante || !$qualificacaoRepresentante || !$edital || !$dataEdital ||
        !$responsavelTecnico || !$qualificacaoResponsavelTecnico || !$matriculaMae || !$oficialDoRegistro || !$cargoOficial
    ) {
        $response['message'] = 'Todos os campos são obrigatórios.';
        echo json_encode($response);
        exit;
    }

    // Prepara a consulta SQL
    $stmt = $conn->prepare("
        INSERT INTO cadastro_de_processo_adm (
            processo_adm, data_de_publicacao, classificacao_individual, direito_real_outorgado, municipio, qualificacao_municipio,
            representante, qualificacao_representante, edital, data_edital, responsavel_tecnico, qualificacao_responsavel_tecnico,
            matricula_mae, oficial_do_registro, cargo_oficial, funcionario, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta: ' . $conn->error);
    }

    // Vincula os parâmetros
    $stmt->bind_param(
        "sssssssssssssssss",
        $processoAdm,
        $dataDePublicacao,
        $classificacaoIndividual,
        $direitoRealOutorgado,
        $municipio,
        $qualificacaoMunicipio,
        $representante,
        $qualificacaoRepresentante,
        $edital,
        $dataEdital,
        $responsavelTecnico,
        $qualificacaoResponsavelTecnico,
        $matriculaMae,
        $oficialDoRegistro,
        $cargoOficial,
        $funcionario,
        $status
    );

    // Executa a consulta
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Processo administrativo cadastrado com sucesso.';
    } else {
        throw new Exception('Erro ao executar a consulta: ' . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
}

// Garante que nenhuma saída extra seja enviada
if (ob_get_length()) ob_clean();
echo json_encode($response);
$conn->close();
