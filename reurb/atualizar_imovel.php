<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

include(__DIR__ . '/db_connection.php');

try {
    // Coleta e trata os dados do formulário
    $id = $_POST['id'] ?? null;
    $tipoLogradouro = $_POST['tipoLogradouro'] ?? null;
    $logradouro = $_POST['logradouro'] ?? null;
    $quadra = $_POST['quadra'] ?? null;
    $numero = $_POST['numero'] ?? null;
    $bairro = $_POST['bairro'] ?? null;
    $cidade = $_POST['cidade'] ?? null;
    $cep = preg_replace('/\D/', '', $_POST['cep'] ?? '');
    $memorialDescritivo = $_POST['memorialDescritivo'] ?? null;
    $areaDoLote = $_POST['area_do_lote'] ?? null;
    $perimetro = $_POST['perimetro'] ?? null;
    $areaConstruida = $_POST['areaConstruida'] ?? null;
    $processoAdm = $_POST['processoAdm'] ?? null;
    $proprietarioNome = mb_strtoupper($_POST['proprietarioNome'] ?? null);
    $proprietarioCpf = preg_replace('/\D/', '', $_POST['proprietarioCpf'] ?? '');
    $nomeConjuge = mb_strtoupper($_POST['nomeConjuge'] ?? null);
    $cpfConjuge = preg_replace('/\D/', '', $_POST['cpfConjuge'] ?? '');

    if (!$id || !$logradouro || !$proprietarioCpf || !$proprietarioNome) {
        echo json_encode(['success' => false, 'message' => 'Campos obrigatórios estão faltando.']);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE cadastro_de_imoveis
        SET tipo_logradouro = ?, logradouro = ?, quadra = ?, numero = ?, bairro = ?, cidade = ?, cep = ?, memorial_descritivo = ?, 
            area_do_lote = ?, perimetro = ?, area_construida = ?, processo_adm = ?, proprietario_nome = ?, proprietario_cpf = ?, 
            nome_conjuge = ?, cpf_conjuge = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception('Erro na preparação da consulta SQL: ' . $conn->error);
    }

    $stmt->bind_param(
        "ssssssssddssssssi",
        $tipoLogradouro,
        $logradouro,
        $quadra,
        $numero,
        $bairro,
        $cidade,
        $cep,
        $memorialDescritivo,
        $areaDoLote,
        $perimetro,
        $areaConstruida,
        $processoAdm,
        $proprietarioNome,
        $proprietarioCpf,
        $nomeConjuge,
        $cpfConjuge,
        $id
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Imóvel atualizado com sucesso.']);
    } else {
        throw new Exception('Erro ao atualizar o imóvel: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
