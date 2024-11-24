<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

include(__DIR__ . '/db_connection.php');

try {
    // Coleta e trata os dados do formulário
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
    $funcionario = 'Sistema'; // Nome do funcionário logado
    $status = 'ativo'; // Status padrão como "ativo"

    // Tratamento do Memorial Descritivo
    if ($memorialDescritivo) {
        $memorialDescritivo = preg_replace('/\r\n|\n|\r/', ' ', $memorialDescritivo); // Remove quebras de linha
        $memorialDescritivo = str_replace(["'", '"'], '', $memorialDescritivo); // Remove aspas simples e duplas
        $memorialDescritivo = trim($memorialDescritivo); // Remove espaços extras
    }

    // Verifica campos obrigatórios
    if (!$logradouro || !$proprietarioCpf || !$proprietarioNome) {
        echo json_encode(['success' => false, 'message' => 'Campos obrigatórios estão faltando.']);
        exit;
    }

    // Prepara a consulta para inserir os dados
    $stmt = $conn->prepare("
        INSERT INTO cadastro_de_imoveis (
            tipo_logradouro, logradouro, quadra, numero, bairro, cidade, cep, memorial_descritivo, area_do_lote,
            perimetro, area_construida, processo_adm, proprietario_nome, proprietario_cpf, conjuge, nome_conjuge,
            cpf_conjuge, funcionario, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    // Verifica se a preparação da consulta teve sucesso
    if (!$stmt) {
        throw new Exception('Erro na preparação da consulta SQL: ' . $conn->error);
    }

    // Vincula os parâmetros
    $stmt->bind_param(
        "ssssssssddsssssssss",
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
        $nomeConjuge,
        $cpfConjuge,
        $funcionario,
        $status
    );

    // Executa a consulta e verifica o resultado
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Imóvel cadastrado com sucesso.']);
    } else {
        if ($stmt->errno === 1062 && strpos($stmt->error, 'proprietario_cpf') !== false) {
            // Busca o nome do proprietário já cadastrado
            $stmtDuplicado = $conn->prepare("SELECT proprietario_nome FROM cadastro_de_imoveis WHERE proprietario_cpf = ?");
            if ($stmtDuplicado) {
                $stmtDuplicado->bind_param("s", $proprietarioCpf);
                $stmtDuplicado->execute();
                $stmtDuplicado->bind_result($nomeDuplicado);
                if ($stmtDuplicado->fetch()) {
                    echo json_encode([
                        'success' => false,
                        'message' => "Já consta um imóvel cadastrado para o CPF '{$proprietarioCpf}' proprietário '{$nomeDuplicado}'."
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => "Já consta um imóvel cadastrado para o CPF '{$proprietarioCpf}'."
                    ]);
                }
                $stmtDuplicado->close();
            }
        } else {
            throw new Exception('Erro ao salvar os dados no banco de dados: ' . $stmt->error);
        }
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
