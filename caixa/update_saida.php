<?php
/**
 * Remove (inativa) uma saída/despesa. Só em caixa aberto; fica auditado.
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/caixa_saldo.php';

date_default_timezone_set('America/Sao_Paulo');

$conn = null;
$ctx = ['acao' => 'saida_removida', 'referencia_tipo' => 'saida'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        cx_json(['error' => 'Método de requisição inválido.']);
    }
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        cx_json(['error' => 'Saída não informada.']);
    }

    $conn = getDatabaseConnection();

    $st = $conn->prepare('SELECT id, titulo, valor_saida, funcionario, data, data_caixa, status, caminho_anexo FROM saidas_despesas WHERE id = :id');
    $st->execute([':id' => $id]);
    $saida = $st->fetch(PDO::FETCH_ASSOC);
    if (!$saida) {
        cx_json(['error' => 'Saída não encontrada.']);
    }

    $dataCaixa = substr((string) ($saida['data_caixa'] ?: $saida['data']), 0, 10);
    $ctx += [
        'funcionario_caixa' => $saida['funcionario'],
        'data_caixa'        => $dataCaixa,
        'referencia_id'     => $id,
        'valor'             => (float) $saida['valor_saida'],
        'detalhes'          => ['titulo' => $saida['titulo'], 'anexo' => $saida['caminho_anexo']],
    ];

    if ($saida['status'] !== 'ativo') {
        cx_bloquear($conn, $ctx, 'Esta saída já foi removida.');
    }

    $saldo = cx_data_valida($dataCaixa) ? cx_saldo_caixa($conn, $saida['funcionario'], $dataCaixa) : null;
    if ($saldo) {
        $ctx['caixa_id']    = $saldo['caixa_id'];
        $ctx['saldo_antes'] = $saldo['disponivel'];
        if ($saldo['caixa_status'] !== 'aberto') {
            cx_bloquear($conn, $ctx, 'Só é possível remover saídas de um caixa aberto.');
        }
    }

    $st = $conn->prepare("UPDATE saidas_despesas SET status = 'removido' WHERE id = :id");
    $st->execute([':id' => $id]);

    cx_auditoria_registrar($conn, array_merge($ctx, [
        'saldo_depois' => $saldo ? round($saldo['disponivel'] + (float) $saida['valor_saida'], 2) : null,
        'descricao'    => 'Saída "' . $saida['titulo'] . '" de ' . cx_moeda($saida['valor_saida']) . ' removida.',
    ]));

    cx_json(['success' => true]);
} catch (Exception $e) {
    if ($conn instanceof PDO && !empty($ctx['funcionario_caixa'])) {
        cx_auditoria_registrar($conn, array_merge($ctx, ['resultado' => 'erro', 'descricao' => 'Erro: ' . $e->getMessage()]));
    }
    cx_json(['error' => $e->getMessage()]);
}
