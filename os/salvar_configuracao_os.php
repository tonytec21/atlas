<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

$id = isset($_POST['id']) ? $_POST['id'] : null;
$banco = $_POST['banco'];
$agencia = $_POST['agencia'];
$tipo_conta = $_POST['tipo_conta'];
$numero_conta = $_POST['numero_conta'];
$titular_conta = $_POST['titular_conta'];
$cpf_cnpj_titular = $_POST['cpf_cnpj_titular'];
$chave_pix = $_POST['chave_pix'];
$qr_code_pix = isset($_POST['qr_code_pix']) ? $_POST['qr_code_pix'] : null;
$status = 'ativa';

try {
    $conn = getDatabaseConnection();
    if ($id) {
        if ($qr_code_pix) {
            $stmt = $conn->prepare("UPDATE configuracao_os SET banco = :banco, agencia = :agencia, tipo_conta = :tipo_conta, numero_conta = :numero_conta, titular_conta = :titular_conta, cpf_cnpj_titular = :cpf_cnpj_titular, chave_pix = :chave_pix, qr_code_pix = :qr_code_pix, status = :status WHERE id = :id");
            $stmt->bindParam(':qr_code_pix', $qr_code_pix);
        } else {
            $stmt = $conn->prepare("UPDATE configuracao_os SET banco = :banco, agencia = :agencia, tipo_conta = :tipo_conta, numero_conta = :numero_conta, titular_conta = :titular_conta, cpf_cnpj_titular = :cpf_cnpj_titular, chave_pix = :chave_pix, status = :status WHERE id = :id");
        }
        $stmt->bindParam(':id', $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO configuracao_os (banco, agencia, tipo_conta, numero_conta, titular_conta, cpf_cnpj_titular, chave_pix, qr_code_pix, status) VALUES (:banco, :agencia, :tipo_conta, :numero_conta, :titular_conta, :cpf_cnpj_titular, :chave_pix, :qr_code_pix, :status)");
        $stmt->bindParam(':qr_code_pix', $qr_code_pix);
    }
    $stmt->bindParam(':banco', $banco);
    $stmt->bindParam(':agencia', $agencia);
    $stmt->bindParam(':tipo_conta', $tipo_conta);
    $stmt->bindParam(':numero_conta', $numero_conta);
    $stmt->bindParam(':titular_conta', $titular_conta);
    $stmt->bindParam(':cpf_cnpj_titular', $cpf_cnpj_titular);
    $stmt->bindParam(':chave_pix', $chave_pix);
    $stmt->bindParam(':status', $status);
    $stmt->execute();

    if (!$id) {
        $id = $conn->lastInsertId();
    }

    echo json_encode(['id' => $id]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao salvar os dados: ' . $e->getMessage()]);
}
