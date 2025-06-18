<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: application/json');

// Definir timezone corretamente
date_default_timezone_set('America/Sao_Paulo');

try {
    // Dados recebidos
    $titulo          = trim($_POST['titulo']);
    $valor_saida     = str_replace(',', '.', str_replace('.', '', $_POST['valor_saida']));
    $forma_de_saida  = trim($_POST['forma_de_saida']);
    $data            = $_POST['data_saida'];
    $data_caixa      = $_POST['data_caixa_saida'];
    $funcionario     = trim(str_replace(' ', '', $_POST['funcionario_saida']));
    $status          = 'ativo';

    // Conexão com o banco
    $conn = getDatabaseConnection();

    // Processamento do upload de anexo
    $caminho_anexo = null;
    if (!empty($_FILES['anexo']['name'])) {
        $targetDir = "anexos/" . $data_caixa . "/" . $funcionario . "/saidas/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = basename($_FILES['anexo']['name']);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES['anexo']['tmp_name'], $targetFile)) {
            $caminho_anexo = $fileName;
        } else {
            throw new Exception('Erro ao fazer upload do anexo.');
        }
    }

    // ===============================
    // Verificar o saldo disponível
    // ===============================

    // Saldo Inicial
    $stmt = $conn->prepare('
        SELECT saldo_inicial 
        FROM caixa 
        WHERE DATE(data_caixa) = :data_caixa 
        AND funcionario = :funcionario 
        AND status = "aberto"
    ');
    $stmt->bindParam(':data_caixa', $data_caixa);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->execute();
    $caixa = $stmt->fetch(PDO::FETCH_ASSOC);
    $saldoInicial = $caixa ? floatval($caixa['saldo_inicial']) : 0.0;

    // Total Recebido em Espécie
    $stmt = $conn->prepare('
        SELECT SUM(total_pagamento) as total 
        FROM pagamento_os 
        WHERE DATE(data_pagamento) = :data_caixa 
        AND funcionario = :funcionario 
        AND forma_de_pagamento = "Espécie"
    ');
    $stmt->bindParam(':data_caixa', $data_caixa);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->execute();
    $pagamento = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalRecebidoEspecie = $pagamento['total'] ? floatval($pagamento['total']) : 0.0;

    // Total Devolvido em Espécie
    $stmt = $conn->prepare('
        SELECT SUM(total_devolucao) as total 
        FROM devolucao_os 
        WHERE DATE(data_devolucao) = :data_caixa 
        AND funcionario = :funcionario 
        AND forma_devolucao = "Espécie"
    ');
    $stmt->bindParam(':data_caixa', $data_caixa);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->execute();
    $devolucao = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalDevolvidoEspecie = $devolucao['total'] ? floatval($devolucao['total']) : 0.0;

    // Total de Saídas
    $stmt = $conn->prepare('
        SELECT SUM(valor_saida) as total 
        FROM saidas_despesas 
        WHERE DATE(data_caixa) = :data_caixa 
        AND funcionario = :funcionario 
        AND status = "ativo"
    ');
    $stmt->bindParam(':data_caixa', $data_caixa);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->execute();
    $saidas = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalSaidas = $saidas['total'] ? floatval($saidas['total']) : 0.0;

    // Total de Depósitos
    $stmt = $conn->prepare('
        SELECT SUM(valor_do_deposito) as total 
        FROM deposito_caixa 
        WHERE DATE(data_caixa) = :data_caixa 
        AND funcionario = :funcionario 
        AND status = "ativo"
    ');
    $stmt->bindParam(':data_caixa', $data_caixa);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->execute();
    $depositos = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalDepositos = $depositos['total'] ? floatval($depositos['total']) : 0.0;

    // Total de Saldo Transportado
    $stmt = $conn->prepare('
        SELECT SUM(valor_transportado) as total 
        FROM transporte_saldo_caixa 
        WHERE DATE(data_caixa) = :data_caixa 
        AND funcionario = :funcionario 
        AND status = "ativo"
    ');
    $stmt->bindParam(':data_caixa', $data_caixa);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->execute();
    $transportes = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalTransportado = $transportes['total'] ? floatval($transportes['total']) : 0.0;

    // Saldo disponível
    $saldoDisponivel = $saldoInicial + $totalRecebidoEspecie - $totalDevolvidoEspecie - $totalSaidas - $totalDepositos - $totalTransportado;

    if ($valor_saida > $saldoDisponivel) {
        echo json_encode([
            'error' => 'A saída não pode ser maior do que o saldo disponível em caixa. Saldo disponível: R$ ' . number_format($saldoDisponivel, 2, ',', '.')
        ]);
        exit;
    }

    // ===============================
    // Inserção no banco
    // ===============================
    $sql = 'INSERT INTO saidas_despesas 
        (titulo, valor_saida, forma_de_saida, data, data_caixa, funcionario, caminho_anexo, status) 
        VALUES 
        (:titulo, :valor_saida, :forma_de_saida, :data, :data_caixa, :funcionario, :caminho_anexo, :status)';

    $stmt = $conn->prepare($sql);

    $stmt->bindParam(':titulo', $titulo);
    $stmt->bindParam(':valor_saida', $valor_saida);
    $stmt->bindParam(':forma_de_saida', $forma_de_saida);
    $stmt->bindParam(':data', $data);
    $stmt->bindParam(':data_caixa', $data_caixa);
    $stmt->bindParam(':funcionario', $funcionario);
    $stmt->bindParam(':caminho_anexo', $caminho_anexo);
    $stmt->bindParam(':status', $status);

    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
?>
