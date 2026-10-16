<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/caixa_saldo.php';

date_default_timezone_set('America/Sao_Paulo');

$conn = null;
$funcionario = $_SESSION['username'];
$dataCaixa   = date('Y-m-d');
$travado     = false;
$ctx = ['acao' => 'caixa_aberto', 'funcionario_caixa' => $funcionario, 'data_caixa' => $dataCaixa, 'referencia_tipo' => 'caixa'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        cx_json(['error' => 'Método de requisição inválido.']);
    }

    $conn = getDatabaseConnection();
    cx_garantir_estrutura($conn);
    $isAdmin = cx_eh_admin($conn, $funcionario);

    if (!cx_lock($conn, $funcionario, $dataCaixa)) {
        cx_json(['error' => 'O caixa está sendo atualizado em outra janela. Tente novamente em instantes.']);
    }
    $travado = true;

    $saldoInformado = isset($_POST['saldo_inicial']) ? round(cx_parse_valor($_POST['saldo_inicial']), 2) : 0.0;
    $ctx['detalhes'] = ['saldo_informado' => $saldoInformado, 'administrador' => $isAdmin];

    // 1) Não abre dois caixas no mesmo dia
    $st = $conn->prepare('SELECT id FROM caixa WHERE funcionario = :f AND DATE(data_caixa) = :d LIMIT 1');
    $st->execute([':f' => $funcionario, ':d' => $dataCaixa]);
    if ($idExistente = $st->fetchColumn()) {
        cx_bloquear($conn, array_merge($ctx, ['caixa_id' => (int) $idExistente]), 'Já existe um caixa aberto para você na data de hoje.');
    }

    // 2) Não abre se houver caixa anterior aberto com saldo
    $pendentes = cx_caixas_abertos_com_saldo($conn, $funcionario, $dataCaixa);
    if ($pendentes) {
        $lista = array_map(function ($p) {
            return cx_data_br($p['data']) . ' (' . cx_moeda($p['disponivel']) . ')';
        }, $pendentes);
        $ctx['detalhes']['pendentes'] = $pendentes;
        cx_bloquear(
            $conn,
            $ctx,
            'Você possui caixa aberto com saldo: ' . implode(', ', $lista) . '. Feche o caixa e transporte o saldo antes de abrir um novo.',
            ['bloqueado' => true, 'pendentes' => $pendentes]
        );
    }

    // 3) Saldo inicial
    $st = $conn->prepare("SELECT COALESCE(SUM(valor_transportado),0) FROM transporte_saldo_caixa WHERE status = 'em aberto' AND funcionario = :f");
    $st->execute([':f' => $funcionario]);
    $saldoTransportado = round((float) $st->fetchColumn(), 2);

    if ($saldoTransportado > 0) {
        $saldoInicial = $saldoTransportado;          // vem sempre do transporte
        $origem = 'transporte';
    } elseif ($isAdmin) {
        if ($saldoInformado < 0) {
            cx_bloquear($conn, $ctx, 'O saldo inicial não pode ser negativo.');
        }
        $saldoInicial = $saldoInformado;
        $origem = $saldoInicial > 0 ? 'administrador' : 'zerado';
    } else {
        $saldoInicial = 0.0;                         // usuário comum abre zerado
        $origem = 'zerado';
    }

    $conn->beginTransaction();

    if ($saldoTransportado > 0) {
        $st = $conn->prepare("UPDATE transporte_saldo_caixa SET status = 'usado', data_caixa_uso = :d WHERE status = 'em aberto' AND funcionario = :f");
        $st->execute([':d' => $dataCaixa, ':f' => $funcionario]);
    }

    $st = $conn->prepare("INSERT INTO caixa (saldo_inicial, funcionario, data_caixa, status) VALUES (:s, :f, :d, 'aberto')");
    $st->execute([':s' => $saldoInicial, ':f' => $funcionario, ':d' => $dataCaixa]);
    $caixaId = (int) $conn->lastInsertId();

    if ($saldoTransportado <= 0 && $isAdmin && $saldoInicial > 0) {
        $st = $conn->prepare('INSERT INTO caixa_saldo_inicial_log (caixa_id, funcionario_caixa, data_caixa, valor_anterior, valor_novo, administrador, motivo)
                              VALUES (:c, :f, :d, 0, :v, :a, :m)');
        $st->execute([':c' => $caixaId, ':f' => $funcionario, ':d' => $dataCaixa, ':v' => $saldoInicial, ':a' => $funcionario, ':m' => 'Informado na abertura do caixa']);
    }

    $conn->commit();

    $descr = [
        'transporte'    => 'Caixa aberto com saldo transportado de ' . cx_moeda($saldoInicial) . '.',
        'administrador' => 'Caixa aberto pelo administrador com saldo inicial de ' . cx_moeda($saldoInicial) . '.',
        'zerado'        => 'Caixa aberto zerado.' . (!$isAdmin && $saldoInformado > 0 ? ' Valor digitado (' . cx_moeda($saldoInformado) . ') ignorado: só o administrador lança saldo inicial.' : ''),
    ][$origem];

    cx_auditoria_registrar($conn, array_merge($ctx, [
        'caixa_id'      => $caixaId,
        'referencia_id' => $caixaId,
        'valor'         => $saldoInicial,
        'saldo_antes'   => 0,
        'saldo_depois'  => $saldoInicial,
        'descricao'     => $descr,
        'detalhes'      => $ctx['detalhes'] + ['origem' => $origem, 'saldo_transportado' => $saldoTransportado],
    ]));

    cx_json(['success' => true, 'saldo_inicial' => $saldoInicial, 'zerado' => $saldoInicial <= 0]);
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
        cx_unlock($conn, $funcionario, $dataCaixa);
    }
}
