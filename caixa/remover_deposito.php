<?php
/**
 * Remove (inativa) um depósito do caixa. Só em caixa aberto; fica auditado.
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/caixa_saldo.php';

date_default_timezone_set('America/Sao_Paulo');

$conn = null;
$ctx = ['acao' => 'deposito_removido', 'referencia_tipo' => 'deposito'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        cx_json(['success' => false, 'error' => 'Método de requisição inválido.']);
    }
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        cx_json(['success' => false, 'error' => 'ID do depósito não fornecido.']);
    }

    $conn = getDatabaseConnection();

    $st = $conn->prepare('SELECT id, funcionario, data_caixa, valor_do_deposito, tipo_deposito, caminho_anexo, status FROM deposito_caixa WHERE id = :id');
    $st->execute([':id' => $id]);
    $dep = $st->fetch(PDO::FETCH_ASSOC);
    if (!$dep) {
        cx_json(['success' => false, 'error' => 'Depósito não encontrado.']);
    }

    $dataCaixa = substr((string) $dep['data_caixa'], 0, 10);
    $ctx += [
        'funcionario_caixa' => $dep['funcionario'],
        'data_caixa'        => $dataCaixa,
        'referencia_id'     => $id,
        'valor'             => (float) $dep['valor_do_deposito'],
        'detalhes'          => ['tipo_deposito' => $dep['tipo_deposito'], 'anexo' => $dep['caminho_anexo']],
    ];

    if ($dep['status'] !== 'ativo') {
        cx_bloquear($conn, $ctx, 'Este depósito já foi removido.', ['success' => false]);
    }

    $saldo = cx_data_valida($dataCaixa) ? cx_saldo_caixa($conn, $dep['funcionario'], $dataCaixa) : null;
    if ($saldo) {
        $ctx['caixa_id']    = $saldo['caixa_id'];
        $ctx['saldo_antes'] = $saldo['disponivel'];
        if ($saldo['caixa_status'] !== 'aberto') {
            cx_bloquear($conn, $ctx, 'Só é possível remover depósitos de um caixa aberto.', ['success' => false]);
        }
    }

    $st = $conn->prepare("UPDATE deposito_caixa SET status = 'removido' WHERE id = :id");
    $st->execute([':id' => $id]);

    cx_auditoria_registrar($conn, array_merge($ctx, [
        'saldo_depois' => $saldo ? round($saldo['disponivel'] + (float) $dep['valor_do_deposito'], 2) : null,
        'descricao'    => 'Depósito (' . $dep['tipo_deposito'] . ') de ' . cx_moeda($dep['valor_do_deposito']) . ' removido.',
    ]));

    cx_json(['success' => true]);
} catch (Exception $e) {
    if ($conn instanceof PDO && !empty($ctx['funcionario_caixa'])) {
        cx_auditoria_registrar($conn, array_merge($ctx, ['resultado' => 'erro', 'descricao' => 'Erro: ' . $e->getMessage()]));
    }
    cx_json(['success' => false, 'error' => $e->getMessage()]);
}
