<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $os_id = $_POST['os_id'];

    try {
        $conn = getDatabaseConnection();

        // Inicializar variáveis
        $temAnexos = false;
        $todosPagamentosEmEspecie = true;

        // Verificar se há anexos
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM anexos_os WHERE ordem_servico_id = ? AND status = 'ativo'");
            $stmt->bind_param('i', $os_id);
            $stmt->execute();
            $stmt->bind_result($anexosCount);
            $stmt->fetch();
            $temAnexos = $anexosCount > 0;
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao verificar anexos: ' . $e->getMessage()]);
            exit;
        }

        // Verificar se todos os pagamentos são em espécie
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM pagamento_os WHERE ordem_de_servico_id = ? AND forma_de_pagamento != 'Espécie'");
            $stmt->bind_param('i', $os_id);
            $stmt->execute();
            $stmt->bind_result($pagamentosDiferentesDeEspecie);
            $stmt->fetch();
            $todosPagamentosEmEspecie = ($pagamentosDiferentesDeEspecie == 0);
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao verificar pagamentos: ' . $e->getMessage()]);
            exit;
        }

        // Retornar os resultados
        echo json_encode([
            'success' => true,
            'temAnexos' => $temAnexos,
            'todosPagamentosEmEspecie' => $todosPagamentosEmEspecie
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro geral: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
}
