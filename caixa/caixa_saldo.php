<?php
/**
 * =====================================================================
 * caixa_saldo.php — Saldo em espécie do caixa (fonte única de cálculo)
 * ---------------------------------------------------------------------
 * Todas as regras de proteção do caixa (saídas, depósitos, transporte
 * de saldo, abertura e saldo inicial lançado pelo administrador) usam
 * este mesmo cálculo, para que o valor validado no servidor seja o
 * mesmo exibido na tela.
 *
 *   Saldo em caixa = saldo inicial
 *                  + recebido em espécie
 *                  - devolvido em espécie
 *                  - saídas e despesas ativas
 *                  - depósitos ativos
 *                  - saldo já transportado daquele caixa
 *
 * Os pagamentos e devoluções lançados pela API (funcionario no formato
 * "API/<sistema>: <pessoa>") são atribuídos ao colaborador pela mesma
 * regra de caixa_funcionario.php.
 * =====================================================================
 */

require_once __DIR__ . '/caixa_funcionario.php';
require_once __DIR__ . '/caixa_versao.php';
require_once __DIR__ . '/caixa_auditoria.php';

if (!defined('CX_SALDO_LIB')) {
    define('CX_SALDO_LIB', ATLAS_CAIXA_VERSAO);

    /** Formas de pagamento que entram como "Recebido em Conta". */
    define('CX_FORMAS_CONTA', [
        'PIX', 'Centrais Eletrônicas', 'Boleto', 'Transferência Bancária',
        'Depósito Bancário', 'Crédito', 'Débito',
    ]);

    /** Diferença tolerada (em centavos) entre dois valores monetários. */
    define('CX_TOLERANCIA_CENTAVOS', 0);

    /* ------------------------------------------------------------------
     * Utilitários
     * ---------------------------------------------------------------- */

    function cx_data_valida($data): bool
    {
        if (!is_string($data) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** Converte para centavos (inteiro) — evita erro de ponto flutuante nas comparações. */
    function cx_centavos($valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    /**
     * Converte texto monetário em float.
     * Aceita "1.234,56", "1234,56", "R$ 1.234,56" e "1234.56".
     */
    function cx_parse_valor($valor): float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        $s = preg_replace('/[^\d,.\-]/', '', (string) $valor);
        if ($s === '' || $s === '-') {
            return 0.0;
        }
        if (strpos($s, ',') !== false) {
            // Formato brasileiro: ponto = milhar, vírgula = decimal
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (substr_count($s, '.') > 1) {
            // "1.234.567" sem decimais
            $s = str_replace('.', '', $s);
        }
        return (float) $s;
    }

    function cx_moeda($valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    function cx_data_br(string $data): string
    {
        $t = strtotime($data);
        return $t ? date('d/m/Y', $t) : $data;
    }

    /**
     * Normaliza textos de situação vindos do banco ("Aberto", " ABERTO ",
     * ENUM('Aberto','Fechado') etc.) para comparação: minúsculo e sem espaços.
     */
    function cx_norm($valor): string
    {
        return mb_strtolower(trim((string) $valor), 'UTF-8');
    }

    /** Nível de acesso é de administrador? ("administrador", "Administrador", "admin") */
    function cx_nivel_admin($nivel): bool
    {
        return in_array(cx_norm($nivel), ['administrador', 'admin'], true);
    }

    /** Usuário logado é administrador? (somente nível "administrador") */
    function cx_eh_admin(PDO $conn, ?string $usuario): bool
    {
        if ($usuario === null || $usuario === '') {
            return false;
        }
        $st = $conn->prepare('SELECT nivel_de_acesso, status FROM funcionarios WHERE usuario = :u LIMIT 1');
        $st->execute([':u' => $usuario]);
        $f = $st->fetch(PDO::FETCH_ASSOC);
        return $f && cx_nivel_admin($f['nivel_de_acesso']) && cx_norm($f['status']) === 'ativo';
    }

    /**
     * Tabela de auditoria do saldo inicial lançado pelo administrador.
     * Criada automaticamente (uma vez por requisição).
     */
    function cx_garantir_estrutura(PDO $conn): void
    {
        static $feito = false;
        if ($feito) {
            return;
        }
        $feito = true;
        try {
            $conn->exec("
                CREATE TABLE IF NOT EXISTS caixa_saldo_inicial_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    caixa_id INT NOT NULL,
                    funcionario_caixa VARCHAR(100) NOT NULL,
                    data_caixa DATE NOT NULL,
                    valor_anterior DECIMAL(15,2) NOT NULL DEFAULT 0,
                    valor_novo DECIMAL(15,2) NOT NULL DEFAULT 0,
                    administrador VARCHAR(100) NOT NULL,
                    motivo VARCHAR(500) NULL,
                    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_cx_sil_caixa (caixa_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Throwable $e) {
            error_log('[caixa_saldo] estrutura: ' . $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------
     * Trava por caixa (funcionário + data) — impede que duas requisições
     * simultâneas validem o mesmo saldo e gastem o dinheiro duas vezes.
     * ---------------------------------------------------------------- */

    function cx_lock_nome(string $funcionario, string $data): string
    {
        return 'atlas_caixa_' . md5(mb_strtoupper(trim($funcionario), 'UTF-8') . '|' . $data);
    }

    function cx_lock(PDO $conn, string $funcionario, string $data, int $timeout = 10): bool
    {
        $st = $conn->prepare('SELECT GET_LOCK(:n, :t)');
        $st->execute([':n' => cx_lock_nome($funcionario, $data), ':t' => $timeout]);
        return (int) $st->fetchColumn() === 1;
    }

    function cx_unlock(PDO $conn, string $funcionario, string $data): void
    {
        try {
            $st = $conn->prepare('SELECT RELEASE_LOCK(:n)');
            $st->execute([':n' => cx_lock_nome($funcionario, $data)]);
        } catch (Throwable $e) {
            // a trava também é liberada quando a conexão termina
        }
    }

    /* ------------------------------------------------------------------
     * Cálculo do saldo
     * ---------------------------------------------------------------- */

    /**
     * Calcula o saldo em espécie do caixa de um funcionário numa data.
     *
     * @return array{
     *   funcionario:string, data:string,
     *   caixa_id:?int, caixa_status:?string, saldo_inicial:float,
     *   recebido_especie:float, recebido_conta:float, total_pagamentos:float,
     *   devolvido_especie:float, total_devolucoes:float,
     *   saidas:float, depositos:float, depositos_qtd:int, depositos_sem_comprovante:int,
     *   transportado:float, transporte_recebido:float, saldo_admin:bool,
     *   origem_saldo:string, disponivel:float
     * }
     */
    function cx_saldo_caixa(PDO $conn, string $funcionario, string $data): array
    {
        $d0 = $data;
        $d1 = date('Y-m-d', strtotime($data . ' +1 day'));
        $funcSql = cx_func_sql('funcionario');

        $r = [
            'funcionario'               => $funcionario,
            'data'                      => $data,
            'caixa_id'                  => null,
            'caixa_status'              => null,
            'saldo_inicial'             => 0.0,
            'recebido_especie'          => 0.0,
            'recebido_conta'            => 0.0,
            'total_pagamentos'          => 0.0,
            'devolvido_especie'         => 0.0,
            'total_devolucoes'          => 0.0,
            'saidas'                    => 0.0,
            'depositos'                 => 0.0,
            'depositos_qtd'             => 0,
            'depositos_sem_comprovante' => 0,
            'transportado'              => 0.0,
            'transporte_recebido'       => 0.0,
            'saldo_admin'               => false,
            'origem_saldo'              => 'sem_caixa',
            'ultimo_caixa'              => false,
            'disponivel'                => 0.0,
        ];

        // Caixa do dia (o primeiro aberto naquela data — mesma regra das telas de detalhe)
        $st = $conn->prepare(
            'SELECT id, saldo_inicial, status FROM caixa
              WHERE funcionario = :f AND data_caixa >= :d0 AND data_caixa < :d1
              ORDER BY id ASC LIMIT 1'
        );
        $st->execute([':f' => $funcionario, ':d0' => $d0, ':d1' => $d1]);
        if ($cx = $st->fetch(PDO::FETCH_ASSOC)) {
            $r['caixa_id']      = (int) $cx['id'];
            $r['caixa_status']  = cx_norm($cx['status']);
            $r['saldo_inicial'] = (float) $cx['saldo_inicial'];
        }

        // Pagamentos (inclui os lançados pela API e atribuídos ao colaborador)
        $st = $conn->prepare(
            "SELECT forma_de_pagamento, COALESCE(SUM(total_pagamento),0) AS total
               FROM pagamento_os
              WHERE data_pagamento >= :d0 AND data_pagamento < :d1
                AND (funcionario = :f OR funcionario LIKE 'API/%')
                AND $funcSql = :f2
              GROUP BY forma_de_pagamento"
        );
        $st->execute([':d0' => $d0, ':d1' => $d1, ':f' => $funcionario, ':f2' => $funcionario]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $v = (float) $p['total'];
            $r['total_pagamentos'] += $v;
            if ($p['forma_de_pagamento'] === 'Espécie') {
                $r['recebido_especie'] += $v;
            } elseif (in_array($p['forma_de_pagamento'], CX_FORMAS_CONTA, true)) {
                $r['recebido_conta'] += $v;
            }
        }

        // Devoluções
        $st = $conn->prepare(
            "SELECT COALESCE(SUM(total_devolucao),0) AS total,
                    COALESCE(SUM(CASE WHEN forma_devolucao = 'Espécie' THEN total_devolucao ELSE 0 END),0) AS especie
               FROM devolucao_os
              WHERE data_devolucao >= :d0 AND data_devolucao < :d1
                AND (funcionario = :f OR funcionario LIKE 'API/%')
                AND $funcSql = :f2"
        );
        $st->execute([':d0' => $d0, ':d1' => $d1, ':f' => $funcionario, ':f2' => $funcionario]);
        $dv = $st->fetch(PDO::FETCH_ASSOC);
        $r['total_devolucoes']  = (float) ($dv['total'] ?? 0);
        $r['devolvido_especie'] = (float) ($dv['especie'] ?? 0);

        // Saídas e despesas ativas
        $st = $conn->prepare(
            "SELECT COALESCE(SUM(valor_saida),0) FROM saidas_despesas
              WHERE funcionario = :f AND data >= :d0 AND data < :d1 AND status = 'ativo'"
        );
        $st->execute([':f' => $funcionario, ':d0' => $d0, ':d1' => $d1]);
        $r['saidas'] = (float) $st->fetchColumn();

        // Depósitos ativos (+ pendência de comprovante)
        $st = $conn->prepare(
            "SELECT COALESCE(SUM(valor_do_deposito),0) AS total, COUNT(*) AS qtd,
                    COALESCE(SUM(CASE WHEN caminho_anexo IS NULL OR caminho_anexo = ''
                                        OR caminho_anexo LIKE 'sem_comprovante_%' THEN 1 ELSE 0 END),0) AS sem_comp
               FROM deposito_caixa
              WHERE funcionario = :f AND data_caixa >= :d0 AND data_caixa < :d1 AND status = 'ativo'"
        );
        $st->execute([':f' => $funcionario, ':d0' => $d0, ':d1' => $d1]);
        $dp = $st->fetch(PDO::FETCH_ASSOC);
        $r['depositos']                 = (float) ($dp['total'] ?? 0);
        $r['depositos_qtd']             = (int) ($dp['qtd'] ?? 0);
        $r['depositos_sem_comprovante'] = (int) ($dp['sem_comp'] ?? 0);

        // Saldo já transportado deste caixa (sai do caixa do dia)
        $st = $conn->prepare(
            'SELECT COALESCE(SUM(valor_transportado),0) FROM transporte_saldo_caixa
              WHERE funcionario = :f AND data_caixa >= :d0 AND data_caixa < :d1'
        );
        $st->execute([':f' => $funcionario, ':d0' => $d0, ':d1' => $d1]);
        $r['transportado'] = (float) $st->fetchColumn();

        // Origem do saldo inicial
        if ($r['caixa_id'] !== null) {
            $st = $conn->prepare(
                "SELECT COALESCE(SUM(valor_transportado),0) FROM transporte_saldo_caixa
                  WHERE funcionario = :f AND status = 'usado'
                    AND data_caixa_uso >= :d0 AND data_caixa_uso < :d1"
            );
            $st->execute([':f' => $funcionario, ':d0' => $d0, ':d1' => $d1]);
            $r['transporte_recebido'] = (float) $st->fetchColumn();

            try {
                $st = $conn->prepare('SELECT COUNT(*) FROM caixa_saldo_inicial_log WHERE caixa_id = :id');
                $st->execute([':id' => $r['caixa_id']]);
                $r['saldo_admin'] = ((int) $st->fetchColumn()) > 0;
            } catch (Throwable $e) {
                $r['saldo_admin'] = false; // tabela ainda não existe
            }

            // É o caixa mais recente do funcionário? (nenhum aberto em data posterior)
            $st = $conn->prepare('SELECT COUNT(*) FROM caixa WHERE funcionario = :f AND data_caixa >= :d1');
            $st->execute([':f' => $funcionario, ':d1' => $d1]);
            $r['ultimo_caixa'] = ((int) $st->fetchColumn()) === 0;

            if (cx_centavos($r['transporte_recebido']) > 0) {
                $r['origem_saldo'] = 'transporte';
            } elseif ($r['saldo_admin']) {
                $r['origem_saldo'] = 'administrador';
            } elseif (cx_centavos($r['saldo_inicial']) === 0) {
                $r['origem_saldo'] = 'zerado';
            } else {
                $r['origem_saldo'] = 'informado'; // aberturas antigas com valor digitado
            }
        }

        $r['disponivel'] = round(
            $r['saldo_inicial']
            + $r['recebido_especie']
            - $r['devolvido_especie']
            - $r['saidas']
            - $r['depositos']
            - $r['transportado'],
            2
        );

        return $r;
    }

    /**
     * Caixa pode receber saldo inicial do administrador?
     * Somente o caixa aberto mais recente do funcionário, que nasceu zerado
     * (ou cujo saldo já foi lançado pelo próprio administrador) — nunca os que
     * vieram de transporte nem caixas antigos já substituídos por um novo.
     */
    function cx_pode_lancar_saldo_inicial(array $saldo): bool
    {
        return $saldo['caixa_id'] !== null
            && $saldo['caixa_status'] === 'aberto'
            && !empty($saldo['ultimo_caixa'])
            && in_array($saldo['origem_saldo'], ['zerado', 'administrador'], true);
    }

    /**
     * Caixas anteriores do funcionário que continuam abertos e com saldo.
     * Impedem a abertura de um novo caixa até serem fechados (com transporte).
     *
     * @return array<int, array{id:int, data:string, disponivel:float}>
     */
    function cx_caixas_abertos_com_saldo(PDO $conn, string $funcionario, string $antesDe): array
    {
        $st = $conn->prepare(
            "SELECT MIN(id) AS id, DATE(data_caixa) AS data
               FROM caixa
              WHERE funcionario = :f AND status = 'aberto' AND data_caixa < :d
              GROUP BY DATE(data_caixa)
              ORDER BY DATE(data_caixa) DESC"
        );
        $st->execute([':f' => $funcionario, ':d' => $antesDe]);

        $pendentes = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $s = cx_saldo_caixa($conn, $funcionario, $c['data']);
            if (cx_centavos($s['disponivel']) > 0) {
                $pendentes[] = [
                    'id'         => (int) $c['id'],
                    'data'       => $c['data'],
                    'disponivel' => $s['disponivel'],
                ];
            }
        }
        return $pendentes;
    }

    /**
     * Recusa a operação por regra de negócio: registra a tentativa na
     * auditoria (resultado "bloqueado") e responde o erro ao navegador.
     */
    function cx_bloquear(?PDO $conn, array $ctx, string $mensagem, array $resposta = []): void
    {
        if ($conn instanceof PDO) {
            cx_auditoria_registrar($conn, array_merge($ctx, [
                'resultado' => 'bloqueado',
                'descricao' => $mensagem,
            ]));
        }
        cx_json(array_merge(['error' => $mensagem], $resposta));
    }

    /** Quantas vezes o Oficial desfez o fechamento deste caixa (0 se a tabela ainda não existe). */
    function cx_qtd_fechamento_desfeito(PDO $conn, int $caixaId): int
    {
        static $existe = null;
        try {
            if ($existe === null) {
                $existe = (bool) $conn->query("SHOW TABLES LIKE 'caixa_fechamento_desfeito'")->fetchColumn();
            }
            if (!$existe) {
                return 0;
            }
            $st = $conn->prepare('SELECT COUNT(*) FROM caixa_fechamento_desfeito WHERE caixa_id = :id');
            $st->execute([':id' => $caixaId]);
            return (int) $st->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** Resposta JSON padrão + encerramento. */
    function cx_json(array $dados, int $http = 200): void
    {
        if (!headers_sent()) {
            http_response_code($http);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
