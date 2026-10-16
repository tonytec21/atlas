<?php
/**
 * Lançamento do saldo inicial de um caixa aberto zerado.
 * Somente usuários com nível de acesso "administrador".
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/caixa_saldo.php';

date_default_timezone_set('America/Sao_Paulo');

$conn = null;
$travado = false;
$funcionario = '';
$data = '';
$ctx = ['acao' => 'saldo_inicial_lancado', 'referencia_tipo' => 'caixa'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        cx_json(['error' => 'Método de requisição inválido.']);
    }

    $conn = getDatabaseConnection();
    cx_garantir_estrutura($conn);

    $admin = $_SESSION['username'];
    $caixaId = (int) ($_POST['caixa_id'] ?? 0);
    $novo    = round(cx_parse_valor($_POST['saldo_inicial'] ?? ''), 2);
    $motivo  = trim((string) ($_POST['motivo'] ?? ''));
    $motivo  = mb_substr($motivo, 0, 500, 'UTF-8');
    $ctx += ['caixa_id' => $caixaId ?: null, 'referencia_id' => $caixaId ?: null, 'valor' => $novo,
             'detalhes' => ['motivo' => $motivo]];


    if ($caixaId <= 0) {
        cx_json(['error' => 'Caixa não informado.']);
    }
    if ($novo < 0) {
        cx_json(['error' => 'O saldo inicial não pode ser negativo.']);
    }

    $st = $conn->prepare('SELECT id, funcionario, DATE(data_caixa) AS data FROM caixa WHERE id = :id');
    $st->execute([':id' => $caixaId]);
    $cx = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cx) {
        cx_json(['error' => 'Caixa não encontrado.']);
    }
    $funcionario = $cx['funcionario'];
    $data        = $cx['data'];
    $ctx['funcionario_caixa'] = $funcionario;
    $ctx['data_caixa']        = $data;

    if (!cx_eh_admin($conn, $admin)) {
        cx_bloquear($conn, $ctx, 'Somente o administrador pode lançar o saldo inicial do caixa.');
    }

    if (!cx_lock($conn, $funcionario, $data)) {
        cx_json(['error' => 'O caixa está sendo atualizado em outra janela. Tente novamente em instantes.']);
    }
    $travado = true;

    $saldo = cx_saldo_caixa($conn, $funcionario, $data);
    $ctx['saldo_antes'] = $saldo['disponivel'];
    $ctx['detalhes']['saldo_inicial_anterior'] = $saldo['saldo_inicial'];

    if ($saldo['caixa_id'] !== $caixaId) {
        cx_bloquear($conn, $ctx, 'Existe mais de um registro de caixa nesta data; ajuste o primeiro caixa do dia.');
    }
    if ($saldo['caixa_status'] !== 'aberto') {
        cx_bloquear($conn, $ctx, 'Este caixa já está fechado. O saldo inicial só pode ser lançado em caixa aberto.');
    }
    if ($saldo['origem_saldo'] === 'transporte') {
        cx_bloquear($conn, $ctx, 'O saldo inicial deste caixa veio do transporte do caixa anterior e não pode ser alterado.');
    }
    if (empty($saldo['ultimo_caixa'])) {
        cx_bloquear($conn, $ctx, 'Já existe um caixa mais recente para este funcionário; o saldo inicial só pode ser lançado no caixa atual.');
    }
    if (!cx_pode_lancar_saldo_inicial($saldo)) {
        cx_bloquear($conn, $ctx, 'O saldo inicial só pode ser lançado em caixas abertos zerados.');
    }

    $anterior = (float) $saldo['saldo_inicial'];
    if (cx_centavos($novo) === cx_centavos($anterior)) {
        cx_bloquear($conn, $ctx, 'O valor informado é igual ao saldo inicial atual.');
    }

    // Reduzir o saldo inicial não pode deixar o caixa negativo
    $disponivelDepois = round($saldo['disponivel'] - $anterior + $novo, 2);
    if (cx_centavos($disponivelDepois) < 0) {
        $minimo = round($anterior - $saldo['disponivel'], 2);
        cx_bloquear($conn, $ctx, 'Com este valor o caixa ficaria negativo. O saldo inicial mínimo, pelas movimentações já lançadas, é '
            . cx_moeda(max(0, $minimo)) . '.');
    }

    $conn->beginTransaction();

    $st = $conn->prepare('UPDATE caixa SET saldo_inicial = :v WHERE id = :id');
    $st->execute([':v' => number_format($novo, 2, '.', ''), ':id' => $caixaId]);

    $st = $conn->prepare('INSERT INTO caixa_saldo_inicial_log (caixa_id, funcionario_caixa, data_caixa, valor_anterior, valor_novo, administrador, motivo)
                          VALUES (:c, :f, :d, :va, :vn, :a, :m)');
    $st->execute([
        ':c'  => $caixaId,
        ':f'  => $funcionario,
        ':d'  => $data,
        ':va' => number_format($anterior, 2, '.', ''),
        ':vn' => number_format($novo, 2, '.', ''),
        ':a'  => $admin,
        ':m'  => $motivo !== '' ? $motivo : null,
    ]);

    $conn->commit();

    cx_auditoria_registrar($conn, array_merge($ctx, [
        'saldo_depois' => $disponivelDepois,
        'descricao'    => 'Saldo inicial alterado de ' . cx_moeda($anterior) . ' para ' . cx_moeda($novo) . '.',
    ]));

    cx_json([
        'success'       => true,
        'saldo_inicial' => $novo,
        'disponivel'    => $disponivelDepois,
    ]);
} catch (Exception $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    if ($conn instanceof PDO) {
        cx_auditoria_registrar($conn, array_merge($ctx, ['resultado' => 'erro', 'descricao' => 'Erro: ' . $e->getMessage()]));
    }
    cx_json(['error' => $e->getMessage()]);
} finally {
    if ($travado && $conn instanceof PDO) {
        cx_unlock($conn, $funcionario, $data);
    }
}
