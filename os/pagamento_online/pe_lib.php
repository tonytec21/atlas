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

require_once __DIR__ . '/../atlas_tempo.php';

require_once __DIR__ . '/lib/Config.php';
require_once __DIR__ . '/lib/Endpoints.php';
require_once __DIR__ . '/lib/ApiException.php';
require_once __DIR__ . '/lib/HttpClient.php';
require_once __DIR__ . '/lib/Pos/PosSaleStatus.php';
require_once __DIR__ . '/lib/Pos/PosSale.php';
require_once __DIR__ . '/lib/Pos/PosSaleRepository.php';
require_once __DIR__ . '/lib/Pos/PosSaleService.php';
require_once __DIR__ . '/lib/Boleto/Boleto.php';
require_once __DIR__ . '/lib/Boleto/BoletoService.php';

use TCloud\ParcelaExpress\Config;
use TCloud\ParcelaExpress\ApiException;
use TCloud\ParcelaExpress\Endpoints;
use TCloud\ParcelaExpress\HttpClient;
use TCloud\ParcelaExpress\Pos\PdoPosSaleRepository;
use TCloud\ParcelaExpress\Pos\PosSale;
use TCloud\ParcelaExpress\Pos\PosSaleService;
use TCloud\ParcelaExpress\Boleto\Boleto;
use TCloud\ParcelaExpress\Boleto\BoletoService;

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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pe_rotas (
            chave      VARCHAR(60)  NOT NULL,
            rota       VARCHAR(255) NOT NULL,
            metodo     VARCHAR(10)  NULL,
            confirmado DATETIME     NULL,
            PRIMARY KEY (chave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

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

    return round($totalOs + $repasses - ($pago - $devolvido), 2);
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
    $faltas = [];

    if (empty($cfg['seller_id'])) $faltas[] = 'ID do estabelecimento (Id da Serventia)';
    if (empty($cfg['usuario']))   $faltas[] = 'Usuario da API';
    if (empty($cfg['senha']))     $faltas[] = 'Senha da API';

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

function pe_service(): PosSaleService
{
    static $service = null;

    if ($service instanceof PosSaleService) {
        return $service;
    }

    pe_rotas(); // injeta as rotas confirmadas antes de qualquer chamada

    $cfg = Config::fromArray(pe_config(true));
    $http = new HttpClient($cfg);

    $service = new PosSaleService(
        $http,
        $cfg,
        new PdoPosSaleRepository(pe_pdo(), 'pe_pos_sales')
    );

    return $service;
}

/**
 * Rotas confirmadas pelo administrador em pe_rotas.php.
 *
 * As constantes de lib/Endpoints.php ja batem com o spec publicado. Este
 * mapa existe para o caso de a API mudar: qualquer rota gravada aqui tem
 * precedencia, sem alteracao de codigo. Carregado uma vez por request.
 *
 * @return array<string,string>
 */
function pe_rotas(): array
{
    static $mapa = null;

    if ($mapa !== null) {
        return $mapa;
    }

    $mapa = [];

    try {
        pe_migrar();
        $r = pe_pdo()->query("SELECT chave, rota FROM pe_rotas");

        while ($linha = $r->fetch(PDO::FETCH_ASSOC)) {
            $mapa[(string) $linha['chave']] = (string) $linha['rota'];
        }
    } catch (Throwable $e) {
        // Sem mapa, valem os padroes de Endpoints.
    }

    Endpoints::aplicarOverrides($mapa);

    return $mapa;
}

function pe_boleto_service(): BoletoService
{
    static $service = null;

    if ($service instanceof BoletoService) {
        return $service;
    }

    pe_rotas();

    $cfg = Config::fromArray(pe_config(true));

    $service = new BoletoService(new HttpClient($cfg), $cfg);

    return $service;
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

/**
 * Traduz a forma de pagamento da API para o rotulo que a O.S. ja usa
 * hoje no select de "Efetuar Pagamento". Manter esses rotulos e o que
 * garante que recibo, liquidacao e relatorios nao precisem mudar.
 */
function pe_forma_para_rotulo(string $formPayment): string
{
    return match (strtolower($formPayment)) {
        'credit' => 'Crédito',
        'debit'  => 'Débito',
        'pix'    => 'PIX',
        default  => 'Crédito',
    };
}

/**
 * Grava a venda aprovada em pagamento_os, exatamente como salvar_pagamento.php
 * faria, e amarra a venda ao pagamento criado.
 *
 * Idempotente: se a venda ja tem pagamento_id, devolve o existente em vez de
 * lancar em duplicidade. Isso importa porque o polling pode ver o status
 * "aprovado" mais de uma vez.
 *
 * @return int id em pagamento_os
 */
function pe_registrar_pagamento(int $osId, PosSale $sale, string $formaRotulo, ?string $observacao = null): int
{
    $pdo = pe_pdo();
    pe_migrar($pdo);

    // Ja lancado? Devolve o mesmo id.
    $stmt = $pdo->prepare("SELECT pagamento_id FROM pe_pos_sales WHERE local_reference = ? LIMIT 1");
    $stmt->execute([$sale->localReference ?? $sale->id]);
    $existente = $stmt->fetchColumn();

    if ($existente) {
        return (int) $existente;
    }

    $os = $pdo->prepare("SELECT cliente, total_os FROM ordens_de_servico WHERE id = ? LIMIT 1");
    $os->execute([$osId]);
    $dadosOs = $os->fetch(PDO::FETCH_ASSOC) ?: ['cliente' => '', 'total_os' => 0];

    $valor = ($sale->chargedAmountCents ?? $sale->amountCents ?? 0) / 100;

    $pdo->beginTransaction();

    try {
        $ins = $pdo->prepare(
            "INSERT INTO pagamento_os
                (ordem_de_servico_id, cliente, total_os, total_pagamento,
                 forma_de_pagamento, data_pagamento, funcionario, status, observacao)
             VALUES (?, ?, ?, ?, ?, NOW(), ?, 'pago', ?)"
        );

        $ins->execute([
            $osId,
            $dadosOs['cliente'],
            $dadosOs['total_os'],
            $valor,
            $formaRotulo,
            pe_usuario(),
            $observacao,
        ]);

        $pagamentoId = (int) $pdo->lastInsertId();

        $upd = $pdo->prepare(
            "UPDATE pe_pos_sales
                SET pagamento_id = ?, ordem_de_servico_id = ?, reconciliation_state = 'settled'
              WHERE local_reference = ?"
        );
        $upd->execute([$pagamentoId, $osId, $sale->localReference ?? $sale->id]);

        $pdo->commit();

        pe_log('info', 'pos', "Venda {$sale->id} lancada como pagamento {$pagamentoId}.", $osId);

        return $pagamentoId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        pe_log('error', 'pos', 'Falha ao lancar pagamento: ' . $e->getMessage(), $osId);

        throw $e;
    }
}

/**
 * Lanca em pagamento_os um boleto CONFIRMADO como pago.
 *
 * Chamado apenas pela consulta de status, nunca pela emissao. A forma de
 * pagamento gravada e "Boleto", que ja existe no select da tela.
 *
 * Idempotente: se o boleto ja tem pagamento_id, devolve o existente.
 */
function pe_registrar_pagamento_boleto(int $osId, Boleto $boleto, string $localReference): int
{
    $pdo = pe_pdo();
    pe_migrar($pdo);

    $stmt = $pdo->prepare("SELECT pagamento_id FROM pe_boletos WHERE local_reference = ? LIMIT 1");
    $stmt->execute([$localReference]);
    $existente = $stmt->fetchColumn();

    if ($existente) {
        return (int) $existente;
    }

    $os = $pdo->prepare("SELECT cliente, total_os FROM ordens_de_servico WHERE id = ? LIMIT 1");
    $os->execute([$osId]);
    $dadosOs = $os->fetch(PDO::FETCH_ASSOC) ?: ['cliente' => '', 'total_os' => 0];

    $valor = ($boleto->paidAmountCents ?? $boleto->amountCents ?? 0) / 100;

    $observacao = null;

    if (pe_tem_coluna_observacao()) {
        $linha = $boleto->linhaFormatada();
        $observacao = 'Boleto' . ($linha ? ' ' . $linha : '')
            . ($boleto->pagoEm ? ' — pago em ' . $boleto->pagoEm->format('d/m/Y') : '');
    }

    $pdo->beginTransaction();

    try {
        $ins = $pdo->prepare(
            "INSERT INTO pagamento_os
                (ordem_de_servico_id, cliente, total_os, total_pagamento,
                 forma_de_pagamento, data_pagamento, funcionario, status, observacao)
             VALUES (?, ?, ?, ?, 'Boleto', NOW(), ?, 'pago', ?)"
        );

        $ins->execute([
            $osId,
            $dadosOs['cliente'],
            $dadosOs['total_os'],
            $valor,
            pe_usuario(),
            $observacao,
        ]);

        $pagamentoId = (int) $pdo->lastInsertId();

        $upd = $pdo->prepare(
            "UPDATE pe_boletos
                SET pagamento_id = ?, status = 'paid', paid_amount_cents = ?, pago_em = ?
              WHERE local_reference = ?"
        );
        $upd->execute([
            $pagamentoId,
            $boleto->paidAmountCents ?? $boleto->amountCents,
            $boleto->pagoEm?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
            $localReference,
        ]);

        $pdo->commit();

        pe_log('info', 'boleto', "Boleto {$boleto->id} confirmado e lancado como pagamento {$pagamentoId}.", $osId);

        return $pagamentoId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        pe_log('error', 'boleto', 'Falha ao lancar boleto pago: ' . $e->getMessage(), $osId);

        throw $e;
    }
}

/**
 * Traduz um erro da API em algo acionavel.
 *
 * "Cannot POST /v1/sellers/.../billets" e a pagina 404 do Express: o servidor
 * respondeu, mas nao conhece o caminho. Repetir esse texto ao operador nao
 * ajuda — o que resolve e mapear a rota em pe_rotas.php. Esta funcao existe
 * para que a mensagem diga isso, em vez do texto cru.
 */
function pe_mensagem_api(ApiException $e, string $chaveRota, string $rotaTentada): string
{
    if ($e->isTransportError()) {
        return 'Não houve resposta da Parcela Express. Verifique a conexão do servidor.';
    }

    if ($e->isRouteMissing()) {
        return sprintf(
            'A API não conhece a rota %s. Abra Configuração → Rotas da API e mapeie a operação "%s" '
            . 'com o caminho real do spec.',
            $rotaTentada,
            $chaveRota
        );
    }

    if ($e->statusCode === 401 || $e->statusCode === 403) {
        return 'A API recusou a operação (' . $e->statusCode . '). Verifique as credenciais e as permissões da conta.';
    }

    return $e->getMessage();
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
