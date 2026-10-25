<?php

declare(strict_types=1);

/**
 * pe_lib.php — Integracao Parcela Express (pagamento online / maquininha).
 *
 * PRINCIPIO DE PROJETO: o modulo e ADITIVO. Enquanto pe_config.ativo = 0,
 * nada neste arquivo altera o comportamento da O.S. A unica coisa que
 * visualizar_os.php faz e perguntar pe_habilitado(); se a resposta for
 * false, a tela renderiza exatamente como sempre renderizou.
 *
 * Toda venda aprovada e gravada em pagamento_os com as mesmas colunas e as
 * mesmas formas de pagamento ja usadas hoje ("Credito", "Debito", "PIX").
 * Recibo, liquidacao, saldo, NFS-e e relatorios continuam funcionando sem
 * saber que esta integracao existe.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Versão do módulo Pagamento Online (exibida na tela de configuração). */
if (!defined('PE_VERSAO')) {
    define('PE_VERSAO', '3.0.6');
}

require_once __DIR__ . '/../atlas_tempo.php';

require_once __DIR__ . '/lib/ParcelaExpressClient.php';

/* =====================================================================
 * 1. CONEXAO
 * ===================================================================== */

function pe_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('mysql:host=localhost;dbname=atlas;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    atlas_alinhar_fuso($pdo);

    /* atlas-auditoria */ if (is_file(__DIR__ . '/../../auditoria/hook.php')) { include_once __DIR__ . '/../../auditoria/hook.php'; atlas_aud_conexao($pdo); }
    return $pdo;
}

/* =====================================================================
 * 2. MIGRACAO
 * ===================================================================== */

function pe_migrar(?PDO $pdo = null): void
{
    static $feito = false;

    if ($feito) {
        return;
    }

    $feito = true;
    $pdo = $pdo ?: pe_pdo();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pe_config (
            id                TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            ativo             TINYINT(1)   NOT NULL DEFAULT 0,
            ambiente          VARCHAR(20)  NOT NULL DEFAULT 'sandbox',

            seller_id         VARCHAR(64)  NULL,
            usuario           VARCHAR(150) NULL,
            senha             TEXT         NULL,
            base_url          VARCHAR(255) NULL,

            habilitar_pos     TINYINT(1)   NOT NULL DEFAULT 1,
            habilitar_boleto  TINYINT(1)   NOT NULL DEFAULT 0,

            timeout_poll_seg  SMALLINT UNSIGNED NOT NULL DEFAULT 3,
            timeout_venda_seg SMALLINT UNSIGNED NOT NULL DEFAULT 300,

            atualizado_em     DATETIME     NULL,
            atualizado_por    VARCHAR(100) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("INSERT IGNORE INTO pe_config (id) VALUES (1)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pe_pos_sales (
            id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            local_reference       CHAR(32)        NOT NULL,
            remote_id             VARCHAR(64)     NULL,

            ordem_de_servico_id   INT             NULL,
            pagamento_id          INT             NULL,

            pre_capture_status    VARCHAR(32)     NULL,
            transaction_status    VARCHAR(32)     NULL,
            reconciliation_state  ENUM('pending','settled','failed','unknown')
                                  NOT NULL DEFAULT 'pending',

            amount_cents          INT UNSIGNED    NULL,
            charged_amount_cents  INT UNSIGNED    NULL,
            installments          TINYINT UNSIGNED NULL,
            payment_form          VARCHAR(16)     NULL,
            terminal_id           VARCHAR(64)     NULL,
            order_number          VARCHAR(64)     NULL,

            funcionario           VARCHAR(120)    NULL,
            request_payload       JSON            NULL,
            raw_payload           JSON            NULL,
            last_error            VARCHAR(500)    NULL,

            created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY uk_local_reference (local_reference),
            KEY idx_remote (remote_id),
            KEY idx_os (ordem_de_servico_id),
            KEY idx_pagamento (pagamento_id),
            KEY idx_reconc (reconciliation_state, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pe_boletos (
            id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            local_reference       CHAR(32)        NOT NULL,
            remote_id             VARCHAR(64)     NULL,

            ordem_de_servico_id   INT             NULL,
            pagamento_id          INT             NULL,

            status                VARCHAR(32)     NOT NULL DEFAULT 'pending',
            amount_cents          INT UNSIGNED    NULL,
            paid_amount_cents     INT UNSIGNED    NULL,

            vencimento            DATE            NULL,
            pago_em               DATETIME        NULL,

            linha_digitavel       VARCHAR(80)     NULL,
            codigo_barras         VARCHAR(80)     NULL,
            pix_copia_cola        TEXT            NULL,
            url_pdf               VARCHAR(500)    NULL,

            pagador_nome          VARCHAR(150)    NULL,
            pagador_documento     VARCHAR(20)     NULL,
            pagador_email         VARCHAR(150)    NULL,

            funcionario           VARCHAR(120)    NULL,
            request_payload       JSON            NULL,
            raw_payload           JSON            NULL,
            last_error            VARCHAR(500)    NULL,

            created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at            DATETIME        NULL ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            UNIQUE KEY uk_boleto_ref (local_reference),
            KEY idx_boleto_remote (remote_id),
            KEY idx_boleto_os (ordem_de_servico_id),
            KEY idx_boleto_status (status, vencimento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    /* Colunas acrescentadas depois da criação original das tabelas. */
    pe_add_coluna($pdo, 'pe_config', 'terminais', 'TEXT NULL');
    pe_add_coluna($pdo, 'pe_config', 'seller_id', 'VARCHAR(64) NULL');
    pe_add_coluna($pdo, 'pe_boletos', 'remote_sale_id', 'VARCHAR(64) NULL');
    pe_add_coluna($pdo, 'pe_boletos', 'url_pagamento', 'VARCHAR(500) NULL');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pe_log (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nivel       VARCHAR(10)  NOT NULL DEFAULT 'info',
            contexto    VARCHAR(60)  NULL,
            mensagem    TEXT         NULL,
            os_id       INT          NULL,
            usuario     VARCHAR(120) NULL,
            criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_criado (criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/** Acrescenta uma coluna se ela ainda não existir (idempotente). */
function pe_add_coluna(PDO $pdo, string $tabela, string $coluna, string $definicao): void
{
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$tabela, $coluna]);

        if (!$st->fetchColumn()) {
            $pdo->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
        }
    } catch (Throwable $e) {
        error_log('[pe_migrar] ' . $tabela . '.' . $coluna . ': ' . $e->getMessage());
    }
}

/* =====================================================================
 * 3. CONFIGURACAO
 * ===================================================================== */

/**
 * @return array<string,mixed>
 */
function pe_config(bool $comSegredos = false): array
{
    pe_migrar();

    $cfg = pe_pdo()
        ->query("SELECT * FROM pe_config WHERE id = 1")
        ->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$comSegredos) {
        unset($cfg['senha']);
    }

    return $cfg;
}

/**
 * Saldo DEVIDO da O.S., em reais (positivo = falta receber).
 *
 * ATENCAO ao sinal: em visualizar_os.php a variavel $saldo e calculada como
 * "pago liquido - total_os - repasses", ou seja, ela e NEGATIVA quando ha
 * valor a receber. Aqui invertemos para a leitura natural de cobranca.
 *
 * Esta e a unica fonte de verdade do valor a cobrar: a tela e a validacao
 * do servidor usam esta mesma funcao, para que nunca divirjam.
 */
function pe_saldo_devido(int $osId): float
{
    $pdo = pe_pdo();

    $stmt = $pdo->prepare("SELECT total_os FROM ordens_de_servico WHERE id = ? LIMIT 1");
    $stmt->execute([$osId]);
    $totalOs = (float) ($stmt->fetchColumn() ?: 0);

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(total_pagamento), 0) FROM pagamento_os WHERE ordem_de_servico_id = ?"
    );
    $stmt->execute([$osId]);
    $pago = (float) $stmt->fetchColumn();

    $devolvido = 0.0;
    $repasses = 0.0;

    // As duas tabelas abaixo podem nao existir em instalacoes antigas.
    try {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(total_devolucao), 0) FROM devolucao_os WHERE ordem_de_servico_id = ?"
        );
        $stmt->execute([$osId]);
        $devolvido = (float) $stmt->fetchColumn();
    } catch (Throwable $e) {
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(total_repasse), 0) FROM repasse_credor WHERE ordem_de_servico_id = ?"
        );
        $stmt->execute([$osId]);
        $repasses = (float) $stmt->fetchColumn();
    } catch (Throwable $e) {
    }

    // Transferências de saldo entre O.S. (somente ativas): recebida abate o
    // devido, enviada volta a ser devida. A tabela pode não existir.
    $tsEntrada = 0.0;
    $tsSaida = 0.0;
    try {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN os_destino_id = ? THEN valor ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN os_origem_id  = ? THEN valor ELSE 0 END), 0)
               FROM os_transferencias_saldo
              WHERE status = 'ativa' AND (os_origem_id = ? OR os_destino_id = ?)"
        );
        $stmt->execute([$osId, $osId, $osId, $osId]);
        $r = $stmt->fetch(PDO::FETCH_NUM);
        if ($r) {
            $tsEntrada = (float) $r[0];
            $tsSaida = (float) $r[1];
        }
    } catch (Throwable $e) {
    }

    return round($totalOs + $repasses - ($pago + $tsEntrada - $devolvido - $tsSaida), 2);
}

/**
 * Pendencias que impedem o uso mesmo com o recurso marcado como ativo.
 *
 * A cobranca na maquininha opera com tres dados: seller_id, usuario e senha.
 * O cartao e inserido no proprio terminal, entao nao ha chave de checkout
 * envolvida.
 *
 * @param array<string,mixed> $cfg
 * @return list<string>
 */
function pe_pendencias(array $cfg): array
{
    /* Em sandbox, sem e-mail/senha, usa-se a conta de homologação da
       Parcela Express. Em produção as credenciais da serventia são exigidas. */
    if (($cfg['ambiente'] ?? 'sandbox') !== 'production') {
        return [];
    }

    $faltas = [];
    if (empty($cfg['usuario'])) $faltas[] = 'E-mail de acesso';
    if (empty($cfg['senha']))   $faltas[] = 'Senha de acesso';

    return $faltas;
}

/**
 * O portao do modulo inteiro.
 *
 * Retorna true somente se o administrador ativou E a configuracao esta
 * completa. Qualquer excecao aqui e engolida de proposito: uma falha de
 * banco no modulo novo NAO pode derrubar a tela de O.S.
 */
function pe_habilitado(): bool
{
    static $resultado = null;

    if ($resultado !== null) {
        return $resultado;
    }

    try {
        $cfg = pe_config(true);
        $resultado = !empty($cfg['ativo']) && pe_pendencias($cfg) === [];
    } catch (Throwable $e) {
        $resultado = false;
    }

    return $resultado;
}

function pe_pos_habilitado(): bool
{
    if (!pe_habilitado()) {
        return false;
    }

    $cfg = pe_config();

    return !empty($cfg['habilitar_pos']);
}

function pe_boleto_habilitado(): bool
{
    if (!pe_habilitado()) {
        return false;
    }

    $cfg = pe_config();

    return !empty($cfg['habilitar_boleto']);
}

/* =====================================================================
 * 4. CLIENTE DA API
 * ===================================================================== */

/** Cliente da Parcela Express montado a partir da configuração salva. */
function pe_cliente(?array $cfg = null): ParcelaExpressClient
{
    static $cache = null;

    if ($cfg === null && $cache instanceof ParcelaExpressClient) {
        return $cache;
    }

    $row = $cfg ?? pe_config(true);
    $sandbox = ($row['ambiente'] ?? 'sandbox') !== 'production';

    $cliente = new ParcelaExpressClient($sandbox);
    /* Endereço alternativo apenas para testes internos (variável de ambiente). */
    $cliente->setBaseUrl((string) getenv('PE_BASE_URL'));
    $cliente->setEmailAndPassword((string) ($row['usuario'] ?? ''), (string) ($row['senha'] ?? ''));
    $cliente->setSellerFixo((string) ($row['seller_id'] ?? ''));

    if ($cfg === null) {
        $cache = $cliente;
    }

    return $cliente;
}

/**
 * Maquininhas cadastradas na configuração (uma por linha: "serial | nome").
 *
 * @return list<array{id:string,name:string}>
 */
function pe_terminais_config(): array
{
    $texto = (string) (pe_config()['terminais'] ?? '');
    $out = [];

    foreach (preg_split('/\R/', $texto) ?: [] as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#')) {
            continue;
        }
        $partes = array_map('trim', explode('|', $linha, 2));
        if ($partes[0] === '') {
            continue;
        }
        $out[] = ['id' => $partes[0], 'name' => ($partes[1] ?? '') !== '' ? $partes[1] . ' (' . $partes[0] . ')' : $partes[0]];
    }

    return $out;
}

/** Referência local: OS_{id}_{timestamp}_{sufixo} — única mesmo no mesmo segundo. */
function pe_referencia(int $osId): string
{
    return 'OS_' . $osId . '_' . time() . '_' . bin2hex(random_bytes(2));
}

/** Referência enviada à API: OS_{id}_{timestamp} (sem o sufixo local). */
function pe_referencia_api(string $referenciaLocal): string
{
    return (string) preg_replace('/_[0-9a-f]{4}$/', '', $referenciaLocal);
}

/* =====================================================================
 * 5. SEGURANCA
 * ===================================================================== */

function pe_csrf_token(): string
{
    if (empty($_SESSION['pe_csrf'])) {
        $_SESSION['pe_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['pe_csrf'];
}

function pe_csrf_check(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['pe_csrf'])
        && hash_equals($_SESSION['pe_csrf'], $token);
}

function pe_usuario(): string
{
    return (string) ($_SESSION['username'] ?? '');
}

/** Le nivel_de_acesso da tabela funcionarios, como o resto do sistema faz. */
function pe_usuario_e_admin(): bool
{
    try {
        $stmt = pe_pdo()->prepare("SELECT nivel_de_acesso FROM funcionarios WHERE usuario = ? LIMIT 1");
        $stmt->execute([pe_usuario()]);
        $nivel = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));

        return $nivel === 'administrador' || $nivel === 'admin';
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Guarda padrao dos endpoints AJAX de operacao.
 * Encerra a requisicao com JSON de erro quando algo nao confere.
 */
function pe_guard_operacao(bool $exigirCsrf = true): void
{
    header('Content-Type: application/json; charset=utf-8');

    if (pe_usuario() === '') {
        pe_json_erro('Sessao expirada.', 401);
    }

    if (!pe_habilitado()) {
        pe_json_erro('Recurso de pagamento online nao esta habilitado.', 403);
    }

    if ($exigirCsrf) {
        $token = $_POST['csrf'] ?? $_GET['csrf'] ?? null;

        if (!pe_csrf_check(is_string($token) ? $token : null)) {
            pe_json_erro('Token de seguranca invalido. Recarregue a pagina.', 403);
        }
    }
}

/** @return never */
function pe_json_erro(string $mensagem, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}

/** @param array<string,mixed> $dados @return never */
function pe_json_ok(array $dados = []): void
{
    echo json_encode(['success' => true] + $dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function pe_log(string $nivel, string $contexto, string $mensagem, ?int $osId = null): void
{
    try {
        pe_migrar();
        $stmt = pe_pdo()->prepare(
            "INSERT INTO pe_log (nivel, contexto, mensagem, os_id, usuario)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$nivel, $contexto, mb_substr($mensagem, 0, 4000), $osId, pe_usuario()]);
    } catch (Throwable $e) {
        // Log nunca derruba operacao.
    }
}

/* =====================================================================
 * 6. PONTE COM O FLUXO ATUAL DA O.S.
 * ===================================================================== */

/** CREDITO/DEBITO/PIX -> rótulo já usado em pagamento_os. */
function pe_forma_para_rotulo(string $modalidade): string
{
    return match (strtoupper($modalidade)) {
        'DEBITO', 'DEBIT' => 'Débito',
        'PIX' => 'PIX',
        default => 'Crédito',
    };
}

/**
 * Lança o pagamento CONFIRMADO em pagamento_os, como salvar_pagamento.php,
 * e amarra o lançamento ao registro de origem (pe_pos_sales ou pe_boletos).
 * Idempotente: se já houver pagamento_id, devolve o mesmo.
 */
function pe_registrar_pagamento(int $osId, string $tabela, string $localReference, string $forma, ?string $observacao = null): int
{
    if (!in_array($tabela, ['pe_pos_sales', 'pe_boletos'], true)) {
        throw new InvalidArgumentException('Origem inválida.');
    }

    $pdo = pe_pdo();
    pe_migrar($pdo);

    $pdo->beginTransaction();

    try {
        $st = $pdo->prepare("SELECT pagamento_id, amount_cents FROM {$tabela} WHERE local_reference = ? LIMIT 1 FOR UPDATE");
        $st->execute([$localReference]);
        $reg = $st->fetch(PDO::FETCH_ASSOC);

        if (!$reg) {
            throw new RuntimeException('Cobrança não encontrada.');
        }
        if (!empty($reg['pagamento_id'])) {
            $pdo->commit();
            return (int) $reg['pagamento_id'];
        }

        $valor = ((int) $reg['amount_cents']) / 100;

        $os = $pdo->prepare("SELECT cliente, total_os FROM ordens_de_servico WHERE id = ? LIMIT 1");
        $os->execute([$osId]);
        $dadosOs = $os->fetch(PDO::FETCH_ASSOC) ?: ['cliente' => '', 'total_os' => 0];

        if (pe_tem_coluna_observacao()) {
            $ins = $pdo->prepare(
                "INSERT INTO pagamento_os
                    (ordem_de_servico_id, cliente, total_os, total_pagamento,
                     forma_de_pagamento, data_pagamento, funcionario, status, observacao)
                 VALUES (?, ?, ?, ?, ?, NOW(), ?, 'pago', ?)"
            );
            $ins->execute([$osId, $dadosOs['cliente'], $dadosOs['total_os'], $valor, $forma, pe_usuario(), $observacao]);
        } else {
            $ins = $pdo->prepare(
                "INSERT INTO pagamento_os
                    (ordem_de_servico_id, cliente, total_os, total_pagamento,
                     forma_de_pagamento, data_pagamento, funcionario, status)
                 VALUES (?, ?, ?, ?, ?, NOW(), ?, 'pago')"
            );
            $ins->execute([$osId, $dadosOs['cliente'], $dadosOs['total_os'], $valor, $forma, pe_usuario()]);
        }

        $pagamentoId = (int) $pdo->lastInsertId();

        if ($tabela === 'pe_boletos') {
            $pdo->prepare("UPDATE pe_boletos SET pagamento_id = ?, status = 'paid', pago_em = NOW() WHERE local_reference = ?")
                ->execute([$pagamentoId, $localReference]);
        } else {
            $pdo->prepare("UPDATE pe_pos_sales SET pagamento_id = ?, reconciliation_state = 'settled' WHERE local_reference = ?")
                ->execute([$pagamentoId, $localReference]);
        }

        $pdo->commit();

        pe_log('info', $tabela === 'pe_boletos' ? 'boleto' : 'pos', "Cobrança {$localReference} lançada como pagamento {$pagamentoId} (R$ {$valor}).", $osId);

        return $pagamentoId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        pe_log('error', 'pagamento', 'Falha ao lançar pagamento: ' . $e->getMessage(), $osId);
        throw $e;
    }
}

/**
 * A coluna observacao pode nao existir em instalacoes antigas.
 * salvar_pagamento.php ja trata isso; aqui usamos a mesma checagem.
 */
function pe_tem_coluna_observacao(): bool
{
    static $tem = null;

    if ($tem !== null) {
        return $tem;
    }

    try {
        $r = pe_pdo()->query("SHOW COLUMNS FROM pagamento_os LIKE 'observacao'");
        $tem = $r && $r->fetch() !== false;
    } catch (Throwable $e) {
        $tem = false;
    }

    return $tem;
}
