<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$response = ['success' => false, 'message' => 'Erro desconhecido.'];

try {
    // Coleta os dados enviados pelo formulário
    $id = $_POST['id'] ?? null;
    $processo_adm = $_POST['processo_adm'] ?? null;
    $data_de_publicacao = $_POST['data_de_publicacao'] ?? null;
    $classificacao_individual = $_POST['classificacao_individual'] ?? null;
    $direito_real_outorgado = $_POST['direito_real_outorgado'] ?? null;
    $municipio = $_POST['municipio'] ?? null;
    $qualificacao_municipio = $_POST['qualificacao_municipio'] ?? null;
    $representante = mb_strtoupper($_POST['representante'] ?? null, 'UTF-8');
    $qualificacao_representante = $_POST['qualificacao_representante'] ?? null;
    $edital = $_POST['edital'] ?? null;
    $data_edital = $_POST['data_edital'] ?? null;
    $responsavel_tecnico = mb_strtoupper($_POST['responsavel_tecnico'] ?? null, 'UTF-8');
    $qualificacao_responsavel_tecnico = $_POST['qualificacao_responsavel_tecnico'] ?? null;
    $matricula_mae = $_POST['matricula_mae'] ?? null;
    $oficial_do_registro = $_POST['oficial_do_registro'] ?? null;
    $cargo_oficial = $_POST['cargo_oficial'] ?? null;

    if (!$id || !$processo_adm || !$data_de_publicacao || !$classificacao_individual || !$direito_real_outorgado || !$municipio || !$representante || !$qualificacao_representante) {
        throw new Exception('Todos os campos obrigatórios devem ser preenchidos.');
    }

    // Atualiza o processo administrativo
    $stmt = $conn->prepare("
        UPDATE cadastro_de_processo_adm 
        SET 
            processo_adm = ?, 
            data_de_publicacao = ?, 
            classificacao_individual = ?, 
            direito_real_outorgado = ?, 
            municipio = ?, 
            qualificacao_municipio = ?, 
            representante = ?, 
            qualificacao_representante = ?, 
            edital = ?, 
            data_edital = ?, 
            responsavel_tecnico = ?, 
            qualificacao_responsavel_tecnico = ?, 
            matricula_mae = ?, 
            oficial_do_registro = ?, 
            cargo_oficial = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Erro ao preparar consulta: ' . $conn->error);
    }

    $stmt->bind_param(
        "sssssssssssssssi",
        $processo_adm,
        $data_de_publicacao,
        $classificacao_individual,
        $direito_real_outorgado,
        $municipio,
        $qualificacao_municipio,
        $representante,
        $qualificacao_representante,
        $edital,
        $data_edital,
        $responsavel_tecnico,
        $qualificacao_responsavel_tecnico,
        $matricula_mae,
        $oficial_do_registro,
        $cargo_oficial,
        $id
    );

    if ($stmt->execute()) {
        $response = ['success' => true, 'message' => 'Processo atualizado com sucesso.'];
    } else {
        throw new Exception('Erro ao atualizar o processo: ' . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
$conn->close();
