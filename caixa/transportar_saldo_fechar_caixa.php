<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/caixa_saldo.php';

date_default_timezone_set('America/Sao_Paulo');

$conn = null;
$travado = false;
$funcionario = '';
$dataCaixa = '';
$ctx = ['acao' => 'caixa_fechado', 'referencia_tipo' => 'caixa'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        cx_json(['error' => 'Método de requisição inválido.']);
    }

    $dataCaixa   = $_POST['data_caixa'] ?? '';
    $funcionario = trim($_POST['funcionario'] ?? '');
    $valorPedido = round(cx_parse_valor($_POST['total_em_caixa'] ?? '0'), 2);

    if ($funcionario === '' || !cx_data_valida($dataCaixa)) {
        cx_json(['error' => 'Caixa inválido (funcionário ou data não informados).']);
    }
    if ($valorPedido < 0) {
        cx_json(['error' => 'O valor a transportar não pode ser negativo.']);
    }

    $conn = getDatabaseConnection();
    $ctx += ['funcionario_caixa' => $funcionario, 'data_caixa' => $dataCaixa, 'valor' => $valorPedido,
             'detalhes' => ['valor_solicitado' => $valorPedido]];

    if (!cx_lock($conn, $funcionario, $dataCaixa)) {
        cx_json(['error' => 'O caixa está sendo atualizado em outra janela. Tente novamente em instantes.']);
    }
    $travado = true;

    // Saldo real, calculado no servidor (o valor da tela é só conferência)
    $saldo      = cx_saldo_caixa($conn, $funcionario, $dataCaixa);
    $disponivel = $saldo['disponivel'];
    $ctx['caixa_id']      = $saldo['caixa_id'];
    $ctx['referencia_id'] = $saldo['caixa_id'];
    $ctx['saldo_antes']   = $disponivel;

    if ($saldo['caixa_status'] === 'fechado') {
        cx_bloquear($conn, $ctx, 'Este caixa já está fechado.');
    }
    if (cx_centavos($disponivel) < 0) {
        cx_bloquear($conn, $ctx, 'O saldo deste caixa está negativo (' . cx_moeda($disponivel) . '). '
            . 'Revise as saídas e depósitos lançados antes de fechar.');
    }
    if (cx_centavos($valorPedido) > cx_centavos($disponivel)) {
        cx_bloquear($conn, $ctx, 'Não é possível transportar ' . cx_moeda($valorPedido)
            . ': o saldo em caixa é de apenas ' . cx_moeda($disponivel) . '.', ['disponivel' => $disponivel]);
    }
    if (cx_centavos($valorPedido) !== cx_centavos($disponivel)) {
        // O caixa mudou desde que a tela foi carregada (novo recebimento, saída etc.)
        cx_bloquear($conn, $ctx, 'O saldo em caixa foi atualizado para ' . cx_moeda($disponivel)
            . '. Recarregue a página e confira antes de fechar.', ['disponivel' => $disponivel]);
    }

    $conn->beginTransaction();

    if (cx_centavos($disponivel) > 0) {
        $st = $conn->prepare("INSERT INTO transporte_saldo_caixa (data_caixa, data_transporte, valor_transportado, funcionario, status)
                              VALUES (:d, NOW(), :v, :f, 'em aberto')");
        $st->execute([':d' => $dataCaixa, ':v' => number_format($disponivel, 2, '.', ''), ':f' => $funcionario]);
    }

    $st = $conn->prepare("UPDATE caixa SET status = 'fechado' WHERE DATE(data_caixa) = :d AND funcionario = :f");
    $st->execute([':d' => $dataCaixa, ':f' => $funcionario]);

    $conn->commit();

    cx_auditoria_registrar($conn, array_merge($ctx, [
        'valor'        => $disponivel,
        'saldo_depois' => 0,
        'descricao'    => cx_centavos($disponivel) > 0
            ? 'Caixa fechado com transporte de ' . cx_moeda($disponivel) . ' para o próximo caixa.'
            : 'Caixa fechado sem saldo a transportar.',
        'detalhes'     => $ctx['detalhes'] + ['saldo_inicial' => $saldo['saldo_inicial'], 'recebido_especie' => $saldo['recebido_especie'],
                           'devolvido_especie' => $saldo['devolvido_especie'], 'saidas' => $saldo['saidas'], 'depositos' => $saldo['depositos']],
    ]));

    cx_json(['success' => true, 'transportado' => $disponivel]);
} catch (Exception $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    if ($conn instanceof PDO && !empty($ctx['funcionario_caixa'])) {
        cx_auditoria_registrar($conn, array_merge($ctx, ['resultado' => 'erro', 'descricao' => 'Erro: ' . $e->getMessage()]));
    }
    cx_json(['error' => $e->getMessage()]);
} finally {
    if ($travado && $conn instanceof PDO) {
        cx_unlock($conn, $funcionario, $dataCaixa);
    }
}
