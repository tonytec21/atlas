<?php
/**
 * =====================================================================
 * api_auth.php — Autenticação, homologação e auditoria
 * ---------------------------------------------------------------------
 * ATLAS-OS-API-BUILD: 2026-07-31-v1
 *
 * MODELO DE CREDENCIAL
 * --------------------
 * Cada sistema integrador é cadastrado em api_sistemas e recebe:
 *
 *   client_id  — identificador público (ex.: atlas_a1b2c3d4e5f6)
 *   token      — segredo, exibido UMA ÚNICA VEZ no cadastro
 *
 * No banco guarda-se apenas o SHA-256 do token, mais um prefixo curto
 * para o operador reconhecer a credencial na tela. Token perdido não se
 * recupera: gera-se outro.
 *
 * O token viaja no cabeçalho:
 *
 *   Authorization: Bearer sk_hml_xxxxxxxxxxxx...
 *
 * CICLO DE HOMOLOGAÇÃO
 * --------------------
 *   pendente  -> cadastrado, SEM acesso. Toda requisição recebe 403.
 *   ativo     -> homologado, acesso liberado conforme o ambiente.
 *   suspenso  -> acesso bloqueado sem apagar a credencial nem o histórico.
 *
 * AMBIENTE
 * --------
 *   homologacao -> o sistema só enxerga e movimenta as O.S. que ele
 *                  próprio criou pela API em homologação (isolamento
 *                  total: não liquida ato de O.S. real por engano).
 *   producao    -> acesso ao acervo real da serventia.
 *
 * Essa separação é o que torna a homologação segura: o parceiro testa o
 * fluxo inteiro — criar O.S., pagar, liquidar — sem tocar em nada real.
 * =====================================================================
 */

require_once __DIR__ . '/api_config.php';

/* --------------------------------------------------------------------
 * Estado da requisição
 * ------------------------------------------------------------------ */

function &api_estado(): array
{
    static $st = [
        'sistema'      => null,
        'inicio'       => null,
        'rota'         => '',
        'metodo'       => '',
        'os_id'        => null,
        'idempotencia' => null,
        'log_id'       => null,
        'finalizado'   => false,
    ];
    return $st;
}

function api_sistema(): ?array
{
    $st = &api_estado();
    return $st['sistema'];
}

function api_sistema_id(): ?int
{
    $s = api_sistema();
    return $s ? (int) $s['id'] : null;
}

function api_ambiente(): string
{
    $s = api_sistema();
    return $s ? (string) $s['ambiente'] : 'homologacao';
}

/** Registra a O.S. envolvida, para a trilha de auditoria. */
function api_marcar_os(?int $osId): void
{
    $st = &api_estado();
    $st['os_id'] = $osId;
}

/* --------------------------------------------------------------------
 * Geração de credenciais
 * ------------------------------------------------------------------ */

function api_gerar_client_id(): string
{
    return 'atlas_' . bin2hex(random_bytes(6));
}

/**
 * Token opaco. O prefixo indica o ambiente a olho nu, o que evita o
 * clássico "colei o token de homologação em produção".
 */
function api_gerar_token(string $ambiente): string
{
    $pre = ($ambiente === 'producao') ? 'sk_prd_' : 'sk_hml_';
    return $pre . bin2hex(random_bytes(24));
}

function api_hash_token(string $token): string
{
    return hash('sha256', $token);
}

/* --------------------------------------------------------------------
 * Leitura do token
 * ------------------------------------------------------------------ */

function api_token_recebido(): ?string
{
    $h = null;

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $h = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        /* Alguns Apache com mod_rewrite entregam o cabeçalho assim. */
        $h = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        foreach ((array) apache_request_headers() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) {
                $h = $v;
                break;
            }
        }
    }

    if ($h && preg_match('/Bearer\s+(\S+)/i', $h, $m)) {
        return $m[1];
    }

    /* Alternativa para clientes que não conseguem enviar Authorization. */
    if (!empty($_SERVER['HTTP_X_API_TOKEN'])) {
        return trim($_SERVER['HTTP_X_API_TOKEN']);
    }

    return null;
}

/* --------------------------------------------------------------------
 * Autenticação
 * ------------------------------------------------------------------ */

/**
 * Autentica a requisição. Encerra com erro se algo não confere.
 */
function api_autenticar(): array
{
    api_migrar();

    $token = api_token_recebido();
    if (!$token) {
        api_erro(
            'nao_autenticado',
            'Envie o token no cabeçalho: Authorization: Bearer <token>.',
            401
        );
    }

    $pdo = api_pdo();
    $st  = $pdo->prepare("SELECT * FROM api_sistemas WHERE token_hash = ? LIMIT 1");
    $st->execute([api_hash_token($token)]);
    $sis = $st->fetch();

    if (!$sis) {
        api_erro('token_invalido', 'Token não reconhecido.', 401);
    }

    /* Homologação: o cadastro existe, mas ainda não foi liberado. */
    if ($sis['status'] === 'pendente') {
        api_erro(
            'sistema_pendente',
            'O sistema "' . $sis['nome'] . '" está cadastrado, mas ainda não foi homologado. '
            . 'Solicite a liberação à serventia.',
            403,
            ['client_id' => $sis['client_id'], 'status' => 'pendente']
        );
    }

    if ($sis['status'] === 'suspenso') {
        api_erro(
            'sistema_suspenso',
            'O acesso deste sistema está suspenso. Procure a serventia.',
            403,
            ['client_id' => $sis['client_id'], 'status' => 'suspenso']
        );
    }

    if ($sis['status'] !== 'ativo') {
        api_erro('sistema_inativo', 'Credencial sem acesso liberado.', 403);
    }

    /* Lista de IPs, quando configurada. */
    if (!empty($sis['ips_permitidos'])) {
        $lista = array_filter(array_map('trim', explode(',', $sis['ips_permitidos'])));
        if ($lista && !in_array(api_ip(), $lista, true)) {
            api_erro(
                'ip_nao_autorizado',
                'Requisição vinda de um IP fora da lista autorizada para este sistema.',
                403,
                ['ip' => api_ip()]
            );
        }
    }

    $estado = &api_estado();
    $estado['sistema'] = $sis;

    /* Marca o uso (best-effort: não derruba a requisição). */
    try {
        $pdo->prepare(
            "UPDATE api_sistemas
                SET ultimo_acesso_em = NOW(), ultimo_acesso_ip = :ip,
                    total_requisicoes = total_requisicoes + 1
              WHERE id = :id"
        )->execute([':ip' => api_ip(), ':id' => $sis['id']]);
    } catch (Throwable $e) {
        error_log('[api_auth] ' . $e->getMessage());
    }

    return $sis;
}

/**
 * Exige um escopo. Escopos disponíveis:
 *   os:ler  os:criar  pagamento:criar  ato:liquidar
 */
function api_exigir_escopo(string $escopo): void
{
    $s = api_sistema();
    if (!$s) {
        api_erro('nao_autenticado', 'Requisição não autenticada.', 401);
    }

    $lista = array_filter(array_map('trim', explode(',', (string) $s['escopos'])));
    if (in_array('*', $lista, true) || in_array($escopo, $lista, true)) {
        return;
    }

    api_erro(
        'escopo_insuficiente',
        'A credencial não possui o escopo "' . $escopo . '".',
        403,
        ['escopo_exigido' => $escopo, 'escopos_da_credencial' => $lista]
    );
}

/* --------------------------------------------------------------------
 * Isolamento de ambiente
 * ------------------------------------------------------------------ */

/**
 * Uma credencial de homologação só pode tocar O.S. criadas por ela
 * mesma em homologação. Em produção, o acervo é o real.
 */
function api_autorizar_os(int $osId): void
{
    if (api_ambiente() === 'producao') {
        return;
    }

    $pdo = api_pdo();
    $st  = $pdo->prepare("SELECT ambiente, sistema_id FROM api_os_vinculo WHERE os_id = ?");
    $st->execute([$osId]);
    $v = $st->fetch();

    if (!$v || $v['ambiente'] !== 'homologacao') {
        api_erro(
            'ambiente_incompativel',
            'Esta credencial é de HOMOLOGAÇÃO e só pode operar O.S. criadas por ela na própria '
            . 'homologação. A O.S. ' . $osId . ' pertence ao acervo real da serventia.',
            403,
            ['os' => $osId, 'ambiente_da_credencial' => 'homologacao']
        );
    }

    if ((int) $v['sistema_id'] !== api_sistema_id()) {
        api_erro(
            'ambiente_incompativel',
            'Esta O.S. de homologação pertence a outro sistema integrador.',
            403,
            ['os' => $osId]
        );
    }
}

/** Vincula a O.S. recém-criada ao sistema e ao ambiente. */
function api_vincular_os(int $osId): void
{
    try {
        api_pdo()->prepare(
            "INSERT INTO api_os_vinculo (os_id, sistema_id, ambiente, criado_em)
             VALUES (:os, :sis, :amb, NOW())
             ON DUPLICATE KEY UPDATE sistema_id = VALUES(sistema_id), ambiente = VALUES(ambiente)"
        )->execute([':os' => $osId, ':sis' => api_sistema_id(), ':amb' => api_ambiente()]);
    } catch (Throwable $e) {
        error_log('[api_vincular_os] ' . $e->getMessage());
    }
}

/* --------------------------------------------------------------------
 * Idempotência
 * --------------------------------------------------------------------
 * O cliente envia:  Idempotency-Key: <chave única da operação>
 *
 * Repetir a mesma chave devolve a resposta guardada, sem executar nada
 * de novo. É a proteção contra o caso clássico: a liquidação foi feita,
 * a resposta se perdeu na rede e o cliente reenviou.
 * ------------------------------------------------------------------ */

function api_chave_idempotencia(): ?string
{
    $k = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? null;

    if (!$k && function_exists('apache_request_headers')) {
        foreach ((array) apache_request_headers() as $nome => $v) {
            if (strcasecmp($nome, 'Idempotency-Key') === 0) {
                $k = $v;
                break;
            }
        }
    }

    $k = trim((string) $k);
    return $k !== '' ? mb_substr($k, 0, 80) : null;
}

/**
 * Se a chave já foi usada, responde com o resultado guardado e encerra.
 */
function api_idempotencia_verificar(string $rota, array $corpo): void
{
    $chave = api_chave_idempotencia();
    if (!$chave) {
        return;
    }

    $estado = &api_estado();
    $estado['idempotencia'] = $chave;

    $hash = hash('sha256', json_encode($corpo));

    $st = api_pdo()->prepare("SELECT * FROM api_idempotencia WHERE sistema_id = ? AND chave = ?");
    $st->execute([api_sistema_id(), $chave]);
    $reg = $st->fetch();

    if (!$reg) {
        return;
    }

    /* Mesma chave com corpo diferente é erro do cliente, não repetição. */
    if ($reg['corpo_hash'] !== $hash) {
        api_erro(
            'idempotencia_conflitante',
            'Esta Idempotency-Key já foi usada com outro conteúdo. Gere uma chave nova para uma operação nova.',
            409,
            ['chave' => $chave]
        );
    }

    $resp = json_decode($reg['resposta'], true);
    if (!is_array($resp)) {
        return;
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Atlas-Idempotencia: repetida');
        http_response_code((int) $reg['status_http']);
    }
    echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_idempotencia_guardar(string $rota, array $corpo, int $http, array $resposta): void
{
    $chave = api_chave_idempotencia();
    if (!$chave) {
        return;
    }

    /* Só vale guardar o que deu certo: uma falha deve poder ser repetida. */
    if ($http >= 400) {
        return;
    }

    try {
        api_pdo()->prepare(
            "INSERT INTO api_idempotencia (sistema_id, chave, rota, corpo_hash, status_http, resposta, criado_em)
             VALUES (:sis, :ch, :rota, :hash, :http, :resp, NOW())
             ON DUPLICATE KEY UPDATE resposta = VALUES(resposta), status_http = VALUES(status_http)"
        )->execute([
            ':sis'  => api_sistema_id(),
            ':ch'   => $chave,
            ':rota' => mb_substr($rota, 0, 255),
            ':hash' => hash('sha256', json_encode($corpo)),
            ':http' => $http,
            ':resp' => json_encode($resposta, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        error_log('[api_idempotencia] ' . $e->getMessage());
    }
}

/* --------------------------------------------------------------------
 * Auditoria
 * ------------------------------------------------------------------ */

function api_log_iniciar(string $metodo, string $rota): void
{
    $st = &api_estado();
    $st['inicio'] = microtime(true);
    $st['metodo'] = $metodo;
    $st['rota']   = $rota;
}

/**
 * Chamado automaticamente por api_responder(). Nunca lança.
 */
function api_log_finalizar(int $http, array $payload): void
{
    $st = &api_estado();
    if ($st['finalizado']) {
        return;
    }
    $st['finalizado'] = true;

    try {
        api_migrar();

        $sis = $st['sistema'];
        $ms  = $st['inicio'] ? (int) round((microtime(true) - $st['inicio']) * 1000) : null;

        $codigo = $payload['erro']['codigo']   ?? null;
        $msg    = $payload['erro']['mensagem'] ?? null;

        /* Corpo resumido, sem o token e sem campos longos. */
        $corpo = null;
        if (in_array($st['metodo'], ['POST', 'PUT', 'PATCH'], true)) {
            $c = api_corpo();
            unset($c['token'], $c['senha'], $c['password']);
            $corpo = mb_substr(json_encode($c, JSON_UNESCAPED_UNICODE), 0, 2000);
        }

        api_pdo()->prepare(
            "INSERT INTO api_log
             (sistema_id, client_id, metodo, rota, os_id, status_http, codigo_erro,
              mensagem, ip, idempotencia, corpo, duracao_ms, criado_em)
             VALUES (:sis, :cid, :met, :rota, :os, :http, :cod, :msg, :ip, :idem, :corpo, :ms, NOW())"
        )->execute([
            ':sis'   => $sis['id'] ?? null,
            ':cid'   => $sis['client_id'] ?? null,
            ':met'   => $st['metodo'],
            ':rota'  => mb_substr($st['rota'], 0, 255),
            ':os'    => $st['os_id'],
            ':http'  => $http,
            ':cod'   => $codigo ? mb_substr($codigo, 0, 60) : null,
            ':msg'   => $msg ? mb_substr($msg, 0, 500) : null,
            ':ip'    => api_ip(),
            ':idem'  => $st['idempotencia'],
            ':corpo' => $corpo,
            ':ms'    => $ms,
        ]);
    } catch (Throwable $e) {
        error_log('[api_log] ' . $e->getMessage());
    }
}

/**
 * Identificação do operador para gravar no campo "funcionario" das
 * tabelas do módulo — deixa claro na tela da O.S. que a origem foi a API.
 *
 * O nome informado pelo integrador é resolvido contra `funcionarios`
 * (por nome_completo OU por usuario) e gravado já como o `usuario`. Isso
 * é o que permite ao fluxo de caixa somar a liquidação no caixa do
 * próprio colaborador em vez de abrir um caixa à parte. A marca
 * "API/<sistema>:" continua no valor, para a auditoria da tela de logs.
 *
 * Não encontrando ninguém, grava o que foi informado — nada se perde, e
 * o caixa faz uma segunda tentativa de casar na leitura.
 */
function api_operador(?string $informado = null): string
{
    $s = api_sistema();
    $base = $s ? $s['nome'] : 'API';

    $informado = $informado !== null ? trim($informado) : '';
    if ($informado === '') {
        return mb_substr('API/' . $base, 0, 100);
    }

    return mb_substr('API/' . $base . ': ' . api_resolver_usuario($informado), 0, 100);
}

/**
 * Converte o nome do operador informado pelo integrador no `usuario`
 * cadastrado em `funcionarios`. Devolve o próprio valor se não achar.
 */
function api_resolver_usuario(string $pessoa): string
{
    static $cache = [];

    $pessoa = trim($pessoa);
    if ($pessoa === '') {
        return $pessoa;
    }

    $chave = mb_strtoupper($pessoa, 'UTF-8');
    if (array_key_exists($chave, $cache)) {
        return $cache[$chave] ?: $pessoa;
    }

    try {
        $st = api_pdo()->prepare(
            "SELECT usuario FROM funcionarios
              WHERE nome_completo = :p OR usuario = :p2
              ORDER BY (usuario = :p3) DESC, id ASC
              LIMIT 1"
        );
        $st->execute([':p' => $pessoa, ':p2' => $pessoa, ':p3' => $pessoa]);
        $usuario = $st->fetchColumn();
    } catch (Throwable $e) {
        error_log('[api_resolver_usuario] ' . $e->getMessage());
        return $pessoa;
    }

    $cache[$chave] = $usuario ?: '';

    return $usuario ?: $pessoa;
}
