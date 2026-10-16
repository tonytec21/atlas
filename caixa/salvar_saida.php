<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/caixa_saldo.php';

date_default_timezone_set('America/Sao_Paulo');

$conn = null;
$travado = false;
$funcionario = '';
$data_caixa = '';
$ctx = ['acao' => 'saida_cadastrada', 'referencia_tipo' => 'saida'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        cx_json(['error' => 'Método de requisição inválido.']);
    }

    // Dados recebidos
    $titulo         = trim($_POST['titulo'] ?? '');
    $valor_saida    = round(cx_parse_valor($_POST['valor_saida'] ?? '0'), 2);
    $forma_de_saida = trim($_POST['forma_de_saida'] ?? 'Espécie');
    $data_caixa     = $_POST['data_caixa_saida'] ?? '';
    $data           = $_POST['data_saida'] ?? $data_caixa;
    $funcionario    = trim(str_replace(' ', '', $_POST['funcionario_saida'] ?? ''));
    $status         = 'ativo';

    if ($titulo === '') {
        cx_json(['error' => 'Informe o título da saída.']);
    }
    if ($funcionario === '' || !cx_data_valida($data_caixa)) {
        cx_json(['error' => 'Caixa inválido (funcionário ou data não informados).']);
    }
    if (!cx_data_valida($data)) {
        $data = $data_caixa;
    }
    if ($valor_saida <= 0) {
        cx_json(['error' => 'O valor da saída deve ser maior que zero.']);
    }

    $conn = getDatabaseConnection();
    $ctx += [
        'funcionario_caixa' => $funcionario,
        'data_caixa'        => $data_caixa,
        'valor'             => $valor_saida,
        'detalhes'          => ['titulo' => $titulo, 'forma_de_saida' => $forma_de_saida],
    ];

    if (!cx_lock($conn, $funcionario, $data_caixa)) {
        cx_json(['error' => 'O caixa está sendo atualizado em outra janela. Tente novamente em instantes.']);
    }
    $travado = true;

    // ===============================
    // Validação do saldo (antes de gravar qualquer arquivo)
    // ===============================
    $saldo = cx_saldo_caixa($conn, $funcionario, $data_caixa);
    $ctx['caixa_id']    = $saldo['caixa_id'];
    $ctx['saldo_antes'] = $saldo['disponivel'];

    if ($saldo['caixa_status'] === 'fechado') {
        cx_bloquear($conn, $ctx, 'Este caixa já está fechado. Não é possível lançar novas saídas.');
    }
    if (cx_centavos($valor_saida) > cx_centavos($saldo['disponivel'])) {
        cx_bloquear(
            $conn,
            $ctx,
            'A saída (' . cx_moeda($valor_saida) . ') é maior do que o saldo disponível em caixa ('
                . cx_moeda(max(0, $saldo['disponivel'])) . ').',
            ['disponivel' => $saldo['disponivel']]
        );
    }

    // Processamento do upload de anexo
    $caminho_anexo = null;
    if (!empty($_FILES['anexo']['name'])) {
        if ($_FILES['anexo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Falha no envio do anexo (código ' . (int) $_FILES['anexo']['error'] . ').');
        }
        $targetDir = __DIR__ . '/anexos/' . $data_caixa . '/' . $funcionario . '/saidas/';
        if (!file_exists($targetDir) && !mkdir($targetDir, 0777, true)) {
            throw new Exception('Falha ao criar o diretório do anexo.');
        }

        $fileName   = basename($_FILES['anexo']['name']);
        $targetFile = $targetDir . $fileName;

        if (!move_uploaded_file($_FILES['anexo']['tmp_name'], $targetFile)) {
            throw new Exception('Erro ao fazer upload do anexo.');
        }
        $caminho_anexo = $fileName;
    }

    // ===============================
    // Inserção no banco
    // ===============================
    $stmt = $conn->prepare(
        'INSERT INTO saidas_despesas
            (titulo, valor_saida, forma_de_saida, data, data_caixa, funcionario, caminho_anexo, status)
         VALUES
            (:titulo, :valor_saida, :forma_de_saida, :data, :data_caixa, :funcionario, :caminho_anexo, :status)'
    );
    $stmt->execute([
        ':titulo'         => $titulo,
        ':valor_saida'    => number_format($valor_saida, 2, '.', ''),
        ':forma_de_saida' => $forma_de_saida,
        ':data'           => $data,
        ':data_caixa'     => $data_caixa,
        ':funcionario'    => $funcionario,
        ':caminho_anexo'  => $caminho_anexo,
        ':status'         => $status,
    ]);

    $saidaId = (int) $conn->lastInsertId();
    $ctx['detalhes']['anexo'] = $caminho_anexo;
    cx_auditoria_registrar($conn, array_merge($ctx, [
        'referencia_id' => $saidaId,
        'saldo_depois'  => round($saldo['disponivel'] - $valor_saida, 2),
        'descricao'     => 'Saída "' . $titulo . '" de ' . cx_moeda($valor_saida) . ' cadastrada.',
    ]));

    cx_json([
        'success'    => true,
        'disponivel' => round($saldo['disponivel'] - $valor_saida, 2),
    ]);
} catch (Exception $e) {
    if ($conn instanceof PDO) {
        cx_auditoria_registrar($conn, array_merge($ctx, ['resultado' => 'erro', 'descricao' => 'Erro: ' . $e->getMessage()]));
    }
    cx_json(['error' => 'Erro: ' . $e->getMessage()]);
} finally {
    if ($travado && $conn instanceof PDO) {
        cx_unlock($conn, $funcionario, $data_caixa);
    }
}
