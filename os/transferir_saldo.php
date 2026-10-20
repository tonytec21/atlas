<?php
/**
 * Transferência de saldo entre O.S. — endpoint AJAX.
 * POST acao=consultar   (origem, destino)            -> resumo das duas O.S.
 * POST acao=transferir  (origem, destino, valor, motivo, confirma_documento)
 * POST acao=estornar    (id, motivo)
 */
include(__DIR__ . '/session_check.php');
checkSession();
require_once __DIR__ . '/transferencia_saldo_lib.php';
require_once __DIR__ . '/auditoria_os_lib.php';
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

function ts_resp($dados, $http = 200)
{
    http_response_code($http);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ts_resp(['ok' => false, 'erro' => 'Método inválido.'], 405);
}

if (empty($_SESSION['csrf_pedidos']) || empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_pedidos'], (string) $_POST['csrf'])) {
    ts_resp(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página.'], 403);
}

$usuario = $_SESSION['username'] ?? '';
$acao    = $_POST['acao'] ?? '';

try {
    ts_migrar();

    if ($acao === 'consultar') {
        $o = ts_situacao((int) ($_POST['origem'] ?? 0));
        $d = ts_situacao((int) ($_POST['destino'] ?? 0));
        if (!$o) {
            throw new Exception('O.S. de origem não encontrada.');
        }
        if (!$d) {
            throw new Exception('O.S. nº ' . (int) ($_POST['destino'] ?? 0) . ' não encontrada.');
        }
        if ((int) $o['os']['id'] === (int) $d['os']['id']) {
            throw new Exception('Escolha uma O.S. diferente da atual.');
        }
        $docO = ts_digitos($o['os']['cpf_cliente']);
        $docD = ts_digitos($d['os']['cpf_cliente']);
        ts_resp([
            'ok' => true,
            'origem' => [
                'id' => (int) $o['os']['id'], 'cliente' => $o['os']['cliente'], 'documento' => $o['os']['cpf_cliente'],
                'disponivel' => $o['disponivel'],
            ],
            'destino' => [
                'id' => (int) $d['os']['id'], 'cliente' => $d['os']['cliente'], 'documento' => $d['os']['cpf_cliente'],
                'titulo' => $d['os']['descricao_os'], 'status' => $d['os']['status'],
                'data' => date('d/m/Y', strtotime($d['os']['data_criacao'])),
                'total' => $d['total_os'], 'pago_liquido' => $d['pago_liquido'], 'falta' => $d['falta_receber'],
            ],
            'documento_confere' => ($docO !== '' && $docO === $docD),
            'sugerido' => round(min($o['disponivel'], max(0, $d['falta_receber'])) ?: $o['disponivel'], 2),
        ]);
    }

    if ($acao === 'transferir') {
        $origem  = (int) ($_POST['origem'] ?? 0);
        $destino = (int) ($_POST['destino'] ?? 0);
        $vTxt    = trim((string) ($_POST['valor'] ?? '0'));
        $valor   = strpos($vTxt, ',') !== false
                 ? (float) str_replace(',', '.', str_replace('.', '', $vTxt))   // 1.234,56
                 : (float) $vTxt;                                              // 1234.56

        /* Auditoria nas duas O.S. (antes e depois), com o motivo informado */
        osaud_monitorar('saldo_transferido', ['os_id' => $origem], ['motivo_post' => 'motivo']);
        osaud_monitorar('saldo_transferido', ['os_id' => $destino], ['motivo_post' => 'motivo']);

        $reg = ts_transferir($origem, $destino, $valor, $_POST['motivo'] ?? '', $usuario, !empty($_POST['confirma_documento']));
        ts_resp([
            'ok' => true,
            'codigo' => $reg['codigo'],
            'mensagem' => 'Transferência ' . $reg['codigo'] . ' registrada: ' . ts_moeda($reg['valor'])
                        . ' da O.S. nº ' . $origem . ' para a O.S. nº ' . $destino . '.',
            'destino' => $destino,
        ]);
    }

    if ($acao === 'estornar') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = ts_pdo()->prepare('SELECT os_origem_id, os_destino_id FROM os_transferencias_saldo WHERE id = ?');
        $st->execute([$id]);
        $par = $st->fetch();
        if ($par) {
            osaud_monitorar('saldo_transferencia_estornada', ['os_id' => (int) $par['os_origem_id']], ['motivo_post' => 'motivo']);
            osaud_monitorar('saldo_transferencia_estornada', ['os_id' => (int) $par['os_destino_id']], ['motivo_post' => 'motivo']);
        }
        $t = ts_estornar($id, $_POST['motivo'] ?? '', $usuario);
        ts_resp(['ok' => true, 'mensagem' => 'Transferência ' . $t['codigo'] . ' estornada. O valor voltou para a O.S. nº ' . $t['os_origem_id'] . '.']);
    }

    ts_resp(['ok' => false, 'erro' => 'Ação inválida.'], 400);
} catch (Throwable $e) {
    ts_resp(['ok' => false, 'erro' => $e->getMessage()], 422);
}
