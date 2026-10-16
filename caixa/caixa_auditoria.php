<?php
/**
 * =====================================================================
 * caixa_auditoria.php — Trilha de auditoria do Fluxo de Caixa
 * ---------------------------------------------------------------------
 * Toda operação do caixa (abertura, saídas, depósitos, comprovantes,
 * remoções, fechamento/transporte e saldo inicial do administrador)
 * grava um evento — inclusive as TENTATIVAS BLOQUEADAS pelas regras
 * de saldo, para o administrador enxergar quem tentou o quê.
 *
 * Integridade: cada evento guarda o hash SHA-256 do evento anterior
 * mais o seu próprio conteúdo (encadeamento). Se alguém alterar ou
 * apagar um registro direto no banco, a verificação da tela de
 * auditoria aponta o primeiro registro em que a cadeia quebrou.
 * =====================================================================
 */

if (!defined('CX_AUDIT_LIB')) {
    define('CX_AUDIT_LIB', '2026-10-03');

    /** Rótulos das ações (também usados nos filtros da tela de auditoria). */
    define('CX_AUDIT_ACOES', [
        'caixa_aberto'          => 'Caixa aberto',
        'saida_cadastrada'      => 'Saída cadastrada',
        'saida_removida'        => 'Saída removida',
        'deposito_cadastrado'   => 'Depósito cadastrado',
        'deposito_removido'     => 'Depósito removido',
        'comprovante_anexado'   => 'Comprovante anexado',
        'caixa_fechado'         => 'Caixa fechado / saldo transportado',
        'saldo_inicial_lancado' => 'Saldo inicial lançado (admin)',
    ]);

    define('CX_AUDIT_RESULTADOS', [
        'sucesso'   => 'Sucesso',
        'bloqueado' => 'Bloqueado pela regra',
        'erro'      => 'Erro',
    ]);

    function cx_auditoria_estrutura(PDO $conn): void
    {
        static $feito = false;
        if ($feito) {
            return;
        }
        $feito = true;
        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS caixa_auditoria (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    criado_em DATETIME NOT NULL,
                    usuario VARCHAR(100) NOT NULL,
                    acao VARCHAR(40) NOT NULL,
                    resultado VARCHAR(15) NOT NULL DEFAULT 'sucesso',
                    funcionario_caixa VARCHAR(100) NULL,
                    data_caixa DATE NULL,
                    caixa_id INT NULL,
                    referencia_tipo VARCHAR(30) NULL,
                    referencia_id INT NULL,
                    valor DECIMAL(15,2) NULL,
                    saldo_antes DECIMAL(15,2) NULL,
                    saldo_depois DECIMAL(15,2) NULL,
                    descricao VARCHAR(500) NOT NULL DEFAULT '',
                    detalhes TEXT NULL,
                    ip VARCHAR(45) NULL,
                    user_agent VARCHAR(255) NULL,
                    hash_anterior CHAR(64) NOT NULL DEFAULT '',
                    hash CHAR(64) NOT NULL DEFAULT '',
                    INDEX idx_cxa_data (criado_em),
                    INDEX idx_cxa_caixa (funcionario_caixa, data_caixa),
                    INDEX idx_cxa_usuario (usuario),
                    INDEX idx_cxa_acao (acao, resultado)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Throwable $e) {
            error_log('[caixa_auditoria] estrutura: ' . $e->getMessage());
        }
    }

    /** Valor monetário canônico para o hash ('' quando nulo). */
    function cx_auditoria_num($v): string
    {
        return ($v === null || $v === '') ? '' : number_format((float) $v, 2, '.', '');
    }

    /** Hash do evento: depende do hash anterior + todos os campos relevantes. */
    function cx_auditoria_hash(array $e, string $hashAnterior): string
    {
        $base = [
            $hashAnterior,
            (string) $e['criado_em'],
            (string) $e['usuario'],
            (string) $e['acao'],
            (string) $e['resultado'],
            (string) ($e['funcionario_caixa'] ?? ''),
            (string) ($e['data_caixa'] ?? ''),
            (string) ($e['caixa_id'] ?? ''),
            (string) ($e['referencia_tipo'] ?? ''),
            (string) ($e['referencia_id'] ?? ''),
            cx_auditoria_num($e['valor'] ?? null),
            cx_auditoria_num($e['saldo_antes'] ?? null),
            cx_auditoria_num($e['saldo_depois'] ?? null),
            (string) ($e['descricao'] ?? ''),
            (string) ($e['detalhes'] ?? ''),
            (string) ($e['ip'] ?? ''),
        ];
        return hash('sha256', json_encode($base, JSON_UNESCAPED_UNICODE));
    }

    function cx_auditoria_ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $fwd = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($fwd, FILTER_VALIDATE_IP)) {
                $ip = $fwd . ' (via ' . $ip . ')';
            }
        }
        return mb_substr($ip, 0, 45, 'UTF-8');
    }

    /**
     * Registra um evento de auditoria. Nunca interrompe a operação:
     * em caso de falha, só escreve no log de erros do PHP.
     *
     * Campos aceitos em $ev: acao, resultado, funcionario_caixa, data_caixa,
     * caixa_id, referencia_tipo, referencia_id, valor, saldo_antes,
     * saldo_depois, descricao, detalhes (array).
     */
    function cx_auditoria_registrar(PDO $conn, array $ev): void
    {
        try {
            cx_auditoria_estrutura($conn);

            $e = [
                'criado_em'         => date('Y-m-d H:i:s'),
                'usuario'           => (string) ($_SESSION['username'] ?? 'desconhecido'),
                'acao'              => (string) ($ev['acao'] ?? 'desconhecida'),
                'resultado'         => (string) ($ev['resultado'] ?? 'sucesso'),
                'funcionario_caixa' => isset($ev['funcionario_caixa']) && $ev['funcionario_caixa'] !== '' ? (string) $ev['funcionario_caixa'] : null,
                'data_caixa'        => !empty($ev['data_caixa']) ? substr((string) $ev['data_caixa'], 0, 10) : null,
                'caixa_id'          => isset($ev['caixa_id']) && $ev['caixa_id'] !== null ? (int) $ev['caixa_id'] : null,
                'referencia_tipo'   => $ev['referencia_tipo'] ?? null,
                'referencia_id'     => isset($ev['referencia_id']) && $ev['referencia_id'] !== null ? (int) $ev['referencia_id'] : null,
                'valor'             => isset($ev['valor']) ? round((float) $ev['valor'], 2) : null,
                'saldo_antes'       => isset($ev['saldo_antes']) ? round((float) $ev['saldo_antes'], 2) : null,
                'saldo_depois'      => isset($ev['saldo_depois']) ? round((float) $ev['saldo_depois'], 2) : null,
                'descricao'         => mb_substr((string) ($ev['descricao'] ?? ''), 0, 500, 'UTF-8'),
                'detalhes'          => isset($ev['detalhes']) ? json_encode($ev['detalhes'], JSON_UNESCAPED_UNICODE) : null,
                'ip'                => cx_auditoria_ip(),
                'user_agent'        => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255, 'UTF-8'),
            ];

            // Encadeamento: um evento por vez
            $lk = $conn->query("SELECT GET_LOCK('atlas_caixa_auditoria', 10)")->fetchColumn();
            try {
                $ant = $conn->query('SELECT hash FROM caixa_auditoria ORDER BY id DESC LIMIT 1')->fetchColumn();
                $ant = $ant ? (string) $ant : '';
                $e['hash_anterior'] = $ant;
                $e['hash']          = cx_auditoria_hash($e, $ant);

                $cols = array_keys($e);
                $st = $conn->prepare(
                    'INSERT INTO caixa_auditoria (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')'
                );
                foreach ($e as $k => $v) {
                    $st->bindValue(':' . $k, $v, $v === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
                $st->execute();
            } finally {
                if ((int) $lk === 1) {
                    $conn->query("SELECT RELEASE_LOCK('atlas_caixa_auditoria')");
                }
            }
        } catch (Throwable $t) {
            error_log('[caixa_auditoria] ' . $t->getMessage());
        }
    }

    /**
     * Verifica a cadeia de hashes inteira.
     * @return array{ok:bool, total:int, quebra_id:?int, motivo:string}
     */
    function cx_auditoria_verificar(PDO $conn): array
    {
        cx_auditoria_estrutura($conn);
        $st = $conn->query(
            'SELECT id, criado_em, usuario, acao, resultado, funcionario_caixa, data_caixa, caixa_id,
                    referencia_tipo, referencia_id, valor, saldo_antes, saldo_depois, descricao,
                    detalhes, ip, hash_anterior, hash
               FROM caixa_auditoria ORDER BY id ASC'
        );
        $anterior = '';
        $total = 0;
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $total++;
            if ((string) $r['hash_anterior'] !== $anterior) {
                return ['ok' => false, 'total' => $total, 'quebra_id' => (int) $r['id'],
                        'motivo' => 'Registro anterior a este foi apagado ou alterado.'];
            }
            if (cx_auditoria_hash($r, $anterior) !== (string) $r['hash']) {
                return ['ok' => false, 'total' => $total, 'quebra_id' => (int) $r['id'],
                        'motivo' => 'O conteúdo deste registro foi alterado depois de gravado.'];
            }
            $anterior = (string) $r['hash'];
        }
        return ['ok' => true, 'total' => $total, 'quebra_id' => null, 'motivo' => ''];
    }
}
