<?php
/**
 * =====================================================================
 * desfazer_fechamento_caixa.php — Desfaz o fechamento de um caixa
 * ---------------------------------------------------------------------
 * Uso exclusivo do administrador (Oficial). Exige justificativa.
 *
 * O que é desfeito:
 *  - o caixa volta para "aberto";
 *  - o saldo transportado por esse fechamento volta para o caixa:
 *      · se o transporte ainda não foi usado (status "em aberto"), ele é
 *        simplesmente estornado;
 *      · se já entrou como saldo inicial do caixa seguinte, esse valor é
 *        retirado do saldo inicial do caixa seguinte — o que só é feito se
 *        o caixa seguinte ainda estiver aberto e tiver esse valor em espécie.
 *        Se o caixa seguinte também já foi fechado, desfaça primeiro o
 *        fechamento dele (sempre do mais recente para o mais antigo).
 *
 * Tudo fica registrado na tabela caixa_fechamento_desfeito (com a
 * justificativa e os valores antes/depois) e na trilha de auditoria.
 *
 * POST acao=previa   → só confere e devolve o que será feito
 * POST acao=desfazer → executa (exige justificativa com 15+ caracteres)
 * =====================================================================
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/caixa_saldo.php';

date_default_timezone_set('America/Sao_Paulo');

const CX_DESFAZER_MIN_JUSTIFICATIVA = 15;

$conn = null;
$travado = [];
$funcionario = '';
$dataCaixa = '';
$ctx = ['acao' => 'fechamento_desfeito', 'referencia_tipo' => 'caixa'];

function cx_desfazer_estrutura(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE IF NOT EXISTS caixa_fechamento_desfeito (
            id INT AUTO_INCREMENT PRIMARY KEY,
            caixa_id INT NOT NULL,
            funcionario_caixa VARCHAR(255) NOT NULL,
            data_caixa DATE NOT NULL,
            valor_estornado DECIMAL(15,2) NOT NULL DEFAULT 0,
            transporte_ids VARCHAR(255) NULL,
            transporte_status VARCHAR(30) NULL,
            caixa_destino_id INT NULL,
            data_caixa_destino DATE NULL,
            saldo_inicial_destino_antes DECIMAL(15,2) NULL,
            saldo_inicial_destino_depois DECIMAL(15,2) NULL,
            justificativa VARCHAR(1000) NOT NULL,
            administrador VARCHAR(100) NOT NULL,
            ip VARCHAR(45) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cfd_caixa (funcionario_caixa, data_caixa)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/**
 * Levanta tudo o que o desfazimento vai mexer e diz se é possível.
 * @return array{ok:bool, motivo:?string, ...}
 */
function cx_desfazer_plano(PDO $conn, string $funcionario, string $dataCaixa): array
{
    $saldo = cx_saldo_caixa($conn, $funcionario, $dataCaixa);
    $p = [
        'ok' => false, 'motivo' => null,
        'caixa_id' => $saldo['caixa_id'], 'funcionario' => $funcionario, 'data' => $dataCaixa,
        'valor' => 0.0, 'transportes' => [], 'status_transporte' => 'sem_transporte',
        'destino' => null, 'saldo_atual' => $saldo['disponivel'],
    ];

    if ($saldo['caixa_id'] === null) {
        $p['motivo'] = 'Não existe abertura de caixa para este funcionário nesta data.';
        return $p;
    }
    if ($saldo['caixa_status'] !== 'fechado') {
        $p['motivo'] = 'Este caixa não está fechado.';
        return $p;
    }

    $st = $conn->prepare(
        'SELECT id, valor_transportado, status, data_caixa_uso FROM transporte_saldo_caixa
          WHERE funcionario = :f AND DATE(data_caixa) = :d ORDER BY id'
    );
    $st->execute([':f' => $funcionario, ':d' => $dataCaixa]);
    $transportes = $st->fetchAll(PDO::FETCH_ASSOC);
    $p['transportes'] = $transportes;

    $usados = [];
    foreach ($transportes as $t) {
        $p['valor'] += (float) $t['valor_transportado'];
        if (cx_norm($t['status']) === 'usado') {
            $usados[] = $t;
        }
    }
    $p['valor'] = round($p['valor'], 2);

    if (!$transportes) {
        $p['ok'] = true;
        return $p;
    }
    if (!$usados) {
        $p['status_transporte'] = 'em_aberto';
        $p['ok'] = true;
        return $p;
    }

    // Transporte já usado na abertura de outro caixa
    $p['status_transporte'] = 'usado';
    $datasUso = array_unique(array_map(function ($t) { return substr((string) $t['data_caixa_uso'], 0, 10); }, $usados));
    if (count($datasUso) !== 1 || count($usados) !== count($transportes) || !cx_data_valida($datasUso[0])) {
        $p['motivo'] = 'O saldo transportado deste caixa foi usado de forma que não dá para estornar automaticamente. Procure o suporte.';
        return $p;
    }
    $dataDestino = $datasUso[0];
    $dest = cx_saldo_caixa($conn, $funcionario, $dataDestino);
    $p['destino'] = [
        'caixa_id' => $dest['caixa_id'], 'data' => $dataDestino, 'status' => $dest['caixa_status'],
        'saldo_inicial' => $dest['saldo_inicial'], 'disponivel' => $dest['disponivel'],
    ];

    if ($dest['caixa_id'] === null) {
        $p['motivo'] = 'O saldo foi transportado para o caixa de ' . cx_data_br($dataDestino) . ', que não foi encontrado. Procure o suporte.';
        return $p;
    }
    if ($dest['caixa_status'] === 'fechado') {
        $p['motivo'] = 'O saldo deste caixa já entrou no caixa de ' . cx_data_br($dataDestino) . ', que também está fechado. '
            . 'Desfaça primeiro o fechamento do caixa de ' . cx_data_br($dataDestino) . ' (sempre do mais recente para o mais antigo).';
        return $p;
    }
    if (cx_centavos($dest['saldo_inicial']) < cx_centavos($p['valor'])) {
        $p['motivo'] = 'O saldo inicial do caixa de ' . cx_data_br($dataDestino) . ' (' . cx_moeda($dest['saldo_inicial'])
            . ') é menor que o valor transportado (' . cx_moeda($p['valor']) . '). Procure o suporte.';
        return $p;
    }
    if (cx_centavos($dest['disponivel']) < cx_centavos($p['valor'])) {
        $p['motivo'] = 'O caixa de ' . cx_data_br($dataDestino) . ' tem só ' . cx_moeda($dest['disponivel'])
            . ' em espécie, menos que os ' . cx_moeda($p['valor']) . ' que precisam voltar. '
            . 'Esse dinheiro já saiu do caixa (saída ou depósito). Ajuste o caixa de ' . cx_data_br($dataDestino) . ' antes de desfazer.';
        return $p;
    }
    $p['ok'] = true;
    return $p;
}

function cx_desfazer_resumo(array $p): array
{
    $r = [
        'caixa_id' => $p['caixa_id'], 'funcionario' => $p['funcionario'], 'data' => $p['data'],
        'data_br' => cx_data_br($p['data']), 'valor' => $p['valor'], 'valor_br' => cx_moeda($p['valor']),
        'status_transporte' => $p['status_transporte'], 'pode' => $p['ok'], 'motivo' => $p['motivo'],
        'min_justificativa' => CX_DESFAZER_MIN_JUSTIFICATIVA,
    ];
    if ($p['destino']) {
        $r['destino_data_br'] = cx_data_br($p['destino']['data']);
        $r['destino_saldo_inicial_br'] = cx_moeda($p['destino']['saldo_inicial']);
        $r['destino_saldo_inicial_depois_br'] = cx_moeda($p['destino']['saldo_inicial'] - $p['valor']);
    }
    return $r;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        cx_json(['error' => 'Método de requisição inválido.']);
    }

    $acao        = $_POST['acao'] ?? 'previa';
    $dataCaixa   = $_POST['data_caixa'] ?? '';
    $funcionario = trim($_POST['funcionario'] ?? '');
    $usuario     = (string) ($_SESSION['username'] ?? '');

    if ($funcionario === '' || !cx_data_valida($dataCaixa)) {
        cx_json(['error' => 'Caixa inválido (funcionário ou data não informados).']);
    }

    $conn = getDatabaseConnection();
    $ctx += ['funcionario_caixa' => $funcionario, 'data_caixa' => $dataCaixa];

    if (!cx_eh_admin($conn, $usuario)) {
        if ($acao === 'desfazer') {
            cx_bloquear($conn, $ctx, 'Somente o Oficial (administrador) pode desfazer o fechamento do caixa.');
        }
        cx_json(['error' => 'Somente o Oficial (administrador) pode desfazer o fechamento do caixa.']);
    }

    if ($acao === 'previa') {
        cx_json(['success' => true, 'plano' => cx_desfazer_resumo(cx_desfazer_plano($conn, $funcionario, $dataCaixa))]);
    }
    if ($acao !== 'desfazer') {
        cx_json(['error' => 'Ação inválida.']);
    }

    $justificativa = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['justificativa'] ?? '')));
    $ctx['detalhes'] = ['justificativa' => $justificativa];
    if (mb_strlen($justificativa, 'UTF-8') < CX_DESFAZER_MIN_JUSTIFICATIVA) {
        cx_bloquear($conn, $ctx, 'Escreva a justificativa (pelo menos ' . CX_DESFAZER_MIN_JUSTIFICATIVA . ' caracteres) para desfazer o fechamento.');
    }
    $justificativa = mb_substr($justificativa, 0, 1000, 'UTF-8');

    // Trava o caixa e, se for o caso, o caixa seguinte que recebeu o saldo
    if (!cx_lock($conn, $funcionario, $dataCaixa)) {
        cx_json(['error' => 'O caixa está sendo atualizado em outra janela. Tente novamente em instantes.']);
    }
    $travado[] = $dataCaixa;

    $plano = cx_desfazer_plano($conn, $funcionario, $dataCaixa);
    $ctx['caixa_id'] = $plano['caixa_id'];
    $ctx['referencia_id'] = $plano['caixa_id'];
    $ctx['valor'] = $plano['valor'];
    if (!$plano['ok']) {
        cx_bloquear($conn, $ctx, $plano['motivo']);
    }
    if ($plano['destino']) {
        if (!cx_lock($conn, $funcionario, $plano['destino']['data'])) {
            cx_json(['error' => 'O caixa de ' . cx_data_br($plano['destino']['data']) . ' está sendo atualizado em outra janela. Tente novamente em instantes.']);
        }
        $travado[] = $plano['destino']['data'];
        $plano = cx_desfazer_plano($conn, $funcionario, $dataCaixa);   // reconfere já com as duas travas
        if (!$plano['ok']) {
            cx_bloquear($conn, $ctx, $plano['motivo']);
        }
    }

    cx_desfazer_estrutura($conn);
    $saldoAntes = $plano['saldo_atual'];
    $ids = array_map(function ($t) { return (int) $t['id']; }, $plano['transportes']);
    $destAntes = $plano['destino'] ? $plano['destino']['saldo_inicial'] : null;
    $destDepois = $plano['destino'] ? round($destAntes - $plano['valor'], 2) : null;

    $conn->beginTransaction();

    // 1) Estorna o transporte deste fechamento
    if ($ids) {
        $in = implode(',', $ids);
        $conn->exec("DELETE FROM transporte_saldo_caixa WHERE id IN ($in)");
    }
    // 2) Se já tinha entrado no caixa seguinte, retira do saldo inicial dele
    if ($plano['destino']) {
        $st = $conn->prepare('UPDATE caixa SET saldo_inicial = :s WHERE id = :id');
        $st->execute([':s' => number_format($destDepois, 2, '.', ''), ':id' => $plano['destino']['caixa_id']]);
    }
    // 3) Reabre o caixa
    $st = $conn->prepare("UPDATE caixa SET status = 'aberto' WHERE id = :id");
    $st->execute([':id' => $plano['caixa_id']]);

    // 4) Registro permanente
    $st = $conn->prepare('INSERT INTO caixa_fechamento_desfeito
        (caixa_id, funcionario_caixa, data_caixa, valor_estornado, transporte_ids, transporte_status, caixa_destino_id,
         data_caixa_destino, saldo_inicial_destino_antes, saldo_inicial_destino_depois, justificativa, administrador, ip)
        VALUES (:c, :f, :d, :v, :ti, :ts, :cd, :dd, :sa, :sd, :j, :a, :ip)');
    $st->execute([
        ':c' => $plano['caixa_id'], ':f' => $funcionario, ':d' => $dataCaixa, ':v' => number_format($plano['valor'], 2, '.', ''),
        ':ti' => $ids ? implode(',', $ids) : null, ':ts' => $plano['status_transporte'],
        ':cd' => $plano['destino']['caixa_id'] ?? null, ':dd' => $plano['destino']['data'] ?? null,
        ':sa' => $destAntes, ':sd' => $destDepois, ':j' => $justificativa, ':a' => $usuario,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $conn->commit();

    $depois = cx_saldo_caixa($conn, $funcionario, $dataCaixa);
    $descr = 'Fechamento desfeito pelo Oficial. ';
    if ($plano['status_transporte'] === 'em_aberto') {
        $descr .= 'Transporte de ' . cx_moeda($plano['valor']) . ' estornado; o valor voltou para este caixa.';
    } elseif ($plano['status_transporte'] === 'usado') {
        $descr .= cx_moeda($plano['valor']) . ' retirados do saldo inicial do caixa de ' . cx_data_br($plano['destino']['data'])
            . ' e devolvidos a este caixa.';
    } else {
        $descr .= 'Não havia saldo transportado.';
    }
    cx_auditoria_registrar($conn, array_merge($ctx, [
        'valor'        => $plano['valor'],
        'saldo_antes'  => $saldoAntes,
        'saldo_depois' => $depois['disponivel'],
        'descricao'    => mb_substr($descr . ' Justificativa: ' . $justificativa, 0, 500, 'UTF-8'),
        'detalhes'     => [
            'justificativa' => $justificativa, 'transporte_ids' => $ids, 'status_transporte' => $plano['status_transporte'],
            'caixa_destino' => $plano['destino'], 'saldo_inicial_destino_depois' => $destDepois,
        ],
    ]));
    if ($plano['destino']) {
        cx_auditoria_registrar($conn, [
            'acao' => 'fechamento_desfeito', 'referencia_tipo' => 'caixa',
            'funcionario_caixa' => $funcionario, 'data_caixa' => $plano['destino']['data'],
            'caixa_id' => $plano['destino']['caixa_id'], 'referencia_id' => $plano['destino']['caixa_id'],
            'valor' => $plano['valor'], 'saldo_antes' => $plano['destino']['disponivel'],
            'saldo_depois' => round($plano['destino']['disponivel'] - $plano['valor'], 2),
            'descricao' => mb_substr('Saldo inicial reduzido de ' . cx_moeda($destAntes) . ' para ' . cx_moeda($destDepois)
                . ' porque o fechamento do caixa de ' . cx_data_br($dataCaixa) . ' foi desfeito. Justificativa: ' . $justificativa, 0, 500, 'UTF-8'),
            'detalhes' => ['caixa_reaberto' => $plano['caixa_id'], 'justificativa' => $justificativa],
        ]);
    }

    cx_json(['success' => true, 'valor' => $plano['valor'], 'saldo' => $depois['disponivel'], 'plano' => cx_desfazer_resumo($plano)]);
} catch (Exception $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    if ($conn instanceof PDO && !empty($ctx['funcionario_caixa'])) {
        cx_auditoria_registrar($conn, array_merge($ctx, ['resultado' => 'erro', 'descricao' => 'Erro: ' . $e->getMessage()]));
    }
    cx_json(['error' => $e->getMessage()]);
} finally {
    if ($conn instanceof PDO) {
        foreach ($travado as $d) {
            cx_unlock($conn, $funcionario, $d);
        }
    }
}
