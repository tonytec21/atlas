<?php
/**
 * Transferência de saldo entre Ordens de Serviço — Atlas, módulo O.S.
 * --------------------------------------------------------------------
 * Move um valor já recebido em uma O.S. (origem) para outra O.S. (destino),
 * SEM criar pagamento nem devolução: o dinheiro entrou no caixa no dia em que
 * foi pago na O.S. de origem e não deve aparecer de novo no caixa de hoje.
 *
 * Regras de cálculo (usadas em toda a O.S.):
 *   - transferência RECEBIDA conta como depósito prévio da O.S. de destino;
 *   - transferência ENVIADA sai do saldo da O.S. de origem (como uma devolução,
 *     mas sem mexer no caixa).
 *
 * Rastreabilidade: cada transferência tem código próprio (TS-AAAA-NNNNNN),
 * usuário, IP, data/hora, motivo, cópia dos valores das duas O.S. no momento
 * e um hash encadeado (SHA-256) que acusa qualquer alteração direta no banco.
 * Nada é apagado: o estorno muda o status para "estornada" e grava quem,
 * quando e por quê.
 */

if (!defined('TS_VERSAO')) {
    define('TS_VERSAO', '1.0.1');
}

require_once __DIR__ . '/db_connection.php';

function ts_pdo()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = getDatabaseConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

/** Cria a tabela na primeira utilização (idempotente). */
function ts_migrar($pdo = null)
{
    static $feito = false;
    if ($feito) {
        return;
    }
    $pdo = $pdo ?: ts_pdo();
    $pdo->exec("CREATE TABLE IF NOT EXISTS os_transferencias_saldo (
        id INT(11) NOT NULL AUTO_INCREMENT,
        codigo VARCHAR(20) NULL,
        os_origem_id INT(11) NOT NULL,
        os_destino_id INT(11) NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        motivo VARCHAR(1000) NOT NULL,
        usuario VARCHAR(100) NOT NULL,
        usuario_nome VARCHAR(150) NULL,
        ip VARCHAR(64) NULL,
        data_transferencia DATETIME NOT NULL,
        origem_cliente VARCHAR(255) NULL,
        origem_documento VARCHAR(25) NULL,
        destino_cliente VARCHAR(255) NULL,
        destino_documento VARCHAR(25) NULL,
        origem_disponivel_antes DECIMAL(10,2) NULL,
        destino_saldo_antes DECIMAL(10,2) NULL,
        documentos_divergentes TINYINT(1) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'ativa',
        estornado_por VARCHAR(100) NULL,
        estornado_em DATETIME NULL,
        motivo_estorno VARCHAR(1000) NULL,
        hash_anterior CHAR(64) NULL,
        hash_registro CHAR(64) NULL,
        hash_estorno CHAR(64) NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_codigo (codigo),
        KEY idx_origem (os_origem_id),
        KEY idx_destino (os_destino_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $feito = true;
}

function ts_moeda($v)
{
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
}

function ts_digitos($s)
{
    return preg_replace('/\D+/', '', (string) $s);
}

/** Pode transferir: administrador ou colaborador com acesso ao Fluxo de Caixa. */
function ts_pode_transferir($usuario)
{
    $u = ts_usuario($usuario);
    if (!$u) {
        return false;
    }
    if (strcasecmp(trim($u['nivel_de_acesso'] ?? ''), 'administrador') === 0) {
        return true;
    }
    $extras = array_map('trim', explode(',', (string) ($u['acesso_adicional'] ?? '')));
    foreach ($extras as $e) {
        if (strcasecmp($e, 'Fluxo de Caixa') === 0) {
            return true;
        }
    }
    return false;
}

/** Pode estornar: somente administrador. */
function ts_pode_estornar($usuario)
{
    $u = ts_usuario($usuario);
    return $u && strcasecmp(trim($u['nivel_de_acesso'] ?? ''), 'administrador') === 0;
}

function ts_usuario($usuario)
{
    static $cache = [];
    if ($usuario === '' || $usuario === null) {
        return null;
    }
    if (!array_key_exists($usuario, $cache)) {
        $st = ts_pdo()->prepare('SELECT usuario, nome_completo, nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ? LIMIT 1');
        $st->execute([$usuario]);
        $cache[$usuario] = $st->fetch() ?: null;
    }
    return $cache[$usuario];
}

/** Lista as transferências ligadas a uma O.S. (enviadas e recebidas). */
function ts_listar($osId)
{
    ts_migrar();
    $st = ts_pdo()->prepare('SELECT * FROM os_transferencias_saldo WHERE os_origem_id = ? OR os_destino_id = ? ORDER BY data_transferencia DESC, id DESC');
    $st->execute([(int) $osId, (int) $osId]);
    return $st->fetchAll();
}

/** Somas das transferências ATIVAS de uma O.S.: ['entrada' => x, 'saida' => y, 'qtd_entrada' => n]. */
function ts_totais($osId)
{
    try {
        ts_migrar();
        $st = ts_pdo()->prepare("SELECT
                COALESCE(SUM(CASE WHEN os_destino_id = ? THEN valor ELSE 0 END), 0) AS entrada,
                COALESCE(SUM(CASE WHEN os_origem_id  = ? THEN valor ELSE 0 END), 0) AS saida,
                COALESCE(SUM(CASE WHEN os_destino_id = ? THEN 1 ELSE 0 END), 0) AS qtd_entrada
            FROM os_transferencias_saldo
            WHERE status = 'ativa' AND (os_origem_id = ? OR os_destino_id = ?)");
        $osId = (int) $osId;
        $st->execute([$osId, $osId, $osId, $osId, $osId]);
        $r = $st->fetch() ?: [];
        return [
            'entrada'     => round((float) ($r['entrada'] ?? 0), 2),
            'saida'       => round((float) ($r['saida'] ?? 0), 2),
            'qtd_entrada' => (int) ($r['qtd_entrada'] ?? 0),
        ];
    } catch (Throwable $e) {
        error_log('[transferencia_saldo] totais: ' . $e->getMessage());
        return ['entrada' => 0.0, 'saida' => 0.0, 'qtd_entrada' => 0];
    }
}

/**
 * Créditos recebidos (ativos) no formato das linhas de pagamento_os, para os
 * recibos listarem junto com os pagamentos. $curto = texto reduzido (bobina 80mm).
 */
function ts_linhas_credito($osId, $cliente = '', $curto = false)
{
    $linhas = [];
    try {
        foreach (ts_listar($osId) as $t) {
            if ($t['status'] !== 'ativa' || (int) $t['os_destino_id'] !== (int) $osId) {
                continue;
            }
            $linhas[] = [
                'data_pagamento'     => $t['data_transferencia'],
                'cliente'            => $cliente,
                'forma_de_pagamento' => $curto
                    ? 'Créd. O.S. ' . (int) $t['os_origem_id']
                    : 'Crédito da O.S. nº ' . (int) $t['os_origem_id'] . ' (' . $t['codigo'] . ')',
                'total_pagamento'    => (float) $t['valor'],
                'funcionario'        => $t['usuario'],
                'transferencia'      => true,
            ];
        }
    } catch (Throwable $e) {
        error_log('[transferencia_saldo] linhas: ' . $e->getMessage());
    }
    return $linhas;
}

/**
 * Situação financeira completa de uma O.S., já considerando transferências.
 *   pago             = pagamentos lançados
 *   credito_recebido = transferências recebidas (ativas)
 *   transferido      = transferências enviadas (ativas)
 *   disponivel       = valor que ainda pode sair da O.S. (devolver ou transferir)
 *   falta_receber    = quanto falta para cobrir o total da O.S.
 */
function ts_situacao($osId)
{
    $pdo = ts_pdo();
    $osId = (int) $osId;
    $st = $pdo->prepare('SELECT id, cliente, cpf_cliente, total_os, status, data_criacao, descricao_os FROM ordens_de_servico WHERE id = ?');
    $st->execute([$osId]);
    $os = $st->fetch();
    if (!$os) {
        return null;
    }
    $soma = function ($sql) use ($pdo, $osId) {
        try {
            $s = $pdo->prepare($sql);
            $s->execute([$osId]);
            return (float) $s->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    };
    $pago      = $soma('SELECT COALESCE(SUM(total_pagamento),0) FROM pagamento_os WHERE ordem_de_servico_id = ?');
    $devolvido = $soma('SELECT COALESCE(SUM(total_devolucao),0) FROM devolucao_os WHERE ordem_de_servico_id = ?');
    $repassado = $soma('SELECT COALESCE(SUM(total_repasse),0) FROM repasse_credor WHERE ordem_de_servico_id = ?');
    $liquidado = $soma('SELECT COALESCE(SUM(total),0) FROM atos_liquidados WHERE ordem_servico_id = ?')
               + $soma('SELECT COALESCE(SUM(total),0) FROM atos_manuais_liquidados WHERE ordem_servico_id = ?');
    $t = ts_totais($osId);

    $liquido    = $pago + $t['entrada'] - $devolvido - $t['saida'];
    $disponivel = $liquido - $repassado - $liquidado;
    $total      = (float) $os['total_os'];
    $falta      = $total - ($liquido - $repassado);

    return [
        'os'               => $os,
        'total_os'         => round($total, 2),
        'pago'             => round($pago, 2),
        'credito_recebido' => $t['entrada'],
        'transferido'      => $t['saida'],
        'devolvido'        => round($devolvido, 2),
        'repassado'        => round($repassado, 2),
        'liquidado'        => round($liquidado, 2),
        'pago_liquido'     => round($liquido, 2),
        'disponivel'       => round(max(0, $disponivel), 2),
        'falta_receber'    => round(max(0, $falta), 2),
        'saldo'            => round($liquido - $total - $repassado, 2),
    ];
}

function ts_hash($r, $anterior)
{
    $base = implode('|', [
        $r['codigo'], $r['os_origem_id'], $r['os_destino_id'], number_format((float) $r['valor'], 2, '.', ''),
        $r['usuario'], $r['data_transferencia'], $r['motivo'], $anterior ?: '-',
    ]);
    return hash('sha256', $base);
}

/** Selo do estorno: amarra status/quem/quando/motivo ao hash do registro. */
function ts_hash_estorno($r)
{
    return hash('sha256', implode('|', [
        $r['hash_registro'], 'estornada', $r['estornado_por'], $r['estornado_em'], $r['motivo_estorno'],
    ]));
}

/**
 * Confere a cadeia de hashes e os selos de estorno.
 * Retorna ['ok' => bool, 'falhas' => [ids], 'codigos' => [códigos]].
 */
function ts_verificar_integridade()
{
    ts_migrar();
    $falhas = [];
    $codigos = [];
    $anterior = null;
    foreach (ts_pdo()->query('SELECT * FROM os_transferencias_saldo ORDER BY id ASC') as $r) {
        $ok = ($r['hash_anterior'] ?: null) === $anterior && ts_hash($r, $anterior) === $r['hash_registro'];
        if ($r['status'] === 'estornada') {
            $ok = $ok && $r['hash_estorno'] && hash_equals($r['hash_estorno'], ts_hash_estorno($r));
        } else {
            $ok = $ok && $r['status'] === 'ativa' && empty($r['hash_estorno']) && empty($r['estornado_em']);
        }
        if (!$ok) {
            $falhas[] = (int) $r['id'];
            $codigos[] = $r['codigo'] ?: ('#' . $r['id']);
        }
        $anterior = $r['hash_registro'];
    }
    return ['ok' => !$falhas, 'falhas' => $falhas, 'codigos' => $codigos];
}

function ts_ip()
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            return $ip === '::1' ? '127.0.0.1' : $ip;
        }
    }
    return '';
}

/**
 * Executa a transferência. Lança Exception com mensagem para o usuário.
 * Retorna o registro gravado.
 */
function ts_transferir($origemId, $destinoId, $valor, $motivo, $usuario, $confirmaDocumento)
{
    $pdo = ts_pdo();
    ts_migrar($pdo);

    $origemId  = (int) $origemId;
    $destinoId = (int) $destinoId;
    $valor     = round((float) $valor, 2);
    $motivo    = trim(preg_replace('/\s+/', ' ', strip_tags((string) $motivo)));

    if (!ts_pode_transferir($usuario)) {
        throw new Exception('Transferência de saldo restrita a administradores e a colaboradores com acesso ao Fluxo de Caixa.');
    }
    if ($origemId <= 0 || $destinoId <= 0) {
        throw new Exception('Informe a O.S. de destino.');
    }
    if ($origemId === $destinoId) {
        throw new Exception('A O.S. de destino deve ser diferente da O.S. de origem.');
    }
    if ($valor <= 0) {
        throw new Exception('Informe um valor maior que zero.');
    }
    if (mb_strlen($motivo) < 10) {
        throw new Exception('Descreva o motivo da transferência (mínimo de 10 caracteres).');
    }

    /* Trava nomeada: impede duas transferências simultâneas mexendo nos mesmos saldos. */
    $lock = $pdo->query("SELECT GET_LOCK('atlas_os_transferencia_saldo', 15)")->fetchColumn();
    if ((int) $lock !== 1) {
        throw new Exception('O sistema está processando outra transferência. Tente novamente em instantes.');
    }
    try {
        $o = ts_situacao($origemId);
        $d = ts_situacao($destinoId);
        if (!$o) {
            throw new Exception('O.S. de origem não encontrada.');
        }
        if (!$d) {
            throw new Exception('O.S. de destino nº ' . $destinoId . ' não encontrada.');
        }
        if (($o['os']['status'] ?? '') === 'Cancelado') {
            throw new Exception('A O.S. de origem está cancelada.');
        }
        if (($d['os']['status'] ?? '') === 'Cancelado') {
            throw new Exception('A O.S. de destino nº ' . $destinoId . ' está cancelada.');
        }
        if ($valor > $o['disponivel'] + 0.001) {
            throw new Exception('O valor informado (' . ts_moeda($valor) . ') é maior que o saldo disponível na O.S. de origem (' . ts_moeda($o['disponivel']) . ').');
        }

        $docO = ts_digitos($o['os']['cpf_cliente']);
        $docD = ts_digitos($d['os']['cpf_cliente']);
        $divergente = ($docO === '' || $docD === '' || $docO !== $docD) ? 1 : 0;
        if ($divergente && !$confirmaDocumento) {
            throw new Exception('O CPF/CNPJ do apresentante das duas O.S. não confere. Confirme a transferência marcando a opção de conferência do apresentante.');
        }

        $agora = date('Y-m-d H:i:s');
        $u = ts_usuario($usuario);

        $pdo->beginTransaction();
        $ultimo = $pdo->query('SELECT hash_registro FROM os_transferencias_saldo ORDER BY id DESC LIMIT 1 FOR UPDATE')->fetchColumn() ?: null;

        $ins = $pdo->prepare('INSERT INTO os_transferencias_saldo
            (os_origem_id, os_destino_id, valor, motivo, usuario, usuario_nome, ip, data_transferencia,
             origem_cliente, origem_documento, destino_cliente, destino_documento,
             origem_disponivel_antes, destino_saldo_antes, documentos_divergentes, status, hash_anterior)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'ativa\',?)');
        $ins->execute([
            $origemId, $destinoId, $valor, $motivo, $usuario, $u['nome_completo'] ?? $usuario, ts_ip(), $agora,
            $o['os']['cliente'], $o['os']['cpf_cliente'], $d['os']['cliente'], $d['os']['cpf_cliente'],
            $o['disponivel'], $d['saldo'], $divergente, $ultimo,
        ]);
        $id = (int) $pdo->lastInsertId();
        $codigo = sprintf('TS-%s-%06d', date('Y'), $id);

        $reg = [
            'codigo' => $codigo, 'os_origem_id' => $origemId, 'os_destino_id' => $destinoId, 'valor' => $valor,
            'usuario' => $usuario, 'data_transferencia' => $agora, 'motivo' => $motivo,
        ];
        $hash = ts_hash($reg, $ultimo);
        $pdo->prepare('UPDATE os_transferencias_saldo SET codigo = ?, hash_registro = ? WHERE id = ?')->execute([$codigo, $hash, $id]);
        $pdo->commit();

        $reg['id'] = $id;
        $reg['hash_registro'] = $hash;
        return $reg;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('atlas_os_transferencia_saldo')");
    }
}

/** Estorna (desfaz) uma transferência ativa. Somente administrador. */
function ts_estornar($id, $motivo, $usuario)
{
    $pdo = ts_pdo();
    ts_migrar($pdo);
    $id = (int) $id;
    $motivo = trim(preg_replace('/\s+/', ' ', strip_tags((string) $motivo)));

    if (!ts_pode_estornar($usuario)) {
        throw new Exception('O estorno de transferência de saldo é restrito a administradores.');
    }
    if (mb_strlen($motivo) < 10) {
        throw new Exception('Descreva o motivo do estorno (mínimo de 10 caracteres).');
    }

    $lock = $pdo->query("SELECT GET_LOCK('atlas_os_transferencia_saldo', 15)")->fetchColumn();
    if ((int) $lock !== 1) {
        throw new Exception('O sistema está processando outra transferência. Tente novamente em instantes.');
    }
    try {
        $st = $pdo->prepare('SELECT * FROM os_transferencias_saldo WHERE id = ?');
        $st->execute([$id]);
        $t = $st->fetch();
        if (!$t) {
            throw new Exception('Transferência não encontrada.');
        }
        if ($t['status'] !== 'ativa') {
            throw new Exception('Esta transferência já foi estornada.');
        }
        $o = ts_situacao($t['os_origem_id']);
        $d = ts_situacao($t['os_destino_id']);
        if ($o && ($o['os']['status'] ?? '') === 'Cancelado') {
            throw new Exception('A O.S. de origem nº ' . $t['os_origem_id'] . ' está cancelada; o valor não pode voltar para ela.');
        }
        if ($d && $d['disponivel'] + 0.001 < (float) $t['valor']) {
            throw new Exception('A O.S. de destino nº ' . $t['os_destino_id'] . ' já utilizou este crédito (disponível: ' . ts_moeda($d['disponivel']) . '). Desfaça antes as liquidações ou devoluções que o consumiram.');
        }
        $t['estornado_por']  = $usuario;
        $t['estornado_em']   = date('Y-m-d H:i:s');
        $t['motivo_estorno'] = $motivo;
        $pdo->prepare("UPDATE os_transferencias_saldo SET status = 'estornada', estornado_por = ?, estornado_em = ?, motivo_estorno = ?, hash_estorno = ? WHERE id = ? AND status = 'ativa'")
            ->execute([$usuario, $t['estornado_em'], $motivo, ts_hash_estorno($t), $id]);
        return $t;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('atlas_os_transferencia_saldo')");
    }
}
