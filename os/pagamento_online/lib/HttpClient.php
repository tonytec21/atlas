<?php

declare(strict_types=1);

namespace TCloud\ParcelaExpress;

/**
 * Transporte HTTP da integracao.
 *
 * Responsabilidades:
 *  - montar URL a partir do Endpoints
 *  - anexar o token de acesso
 *  - reautenticar uma vez em caso de 401
 *  - retry com backoff em erro transitorio
 *  - retry com backoff em erro transitorio
 *
 * O formato exato da autenticacao ainda depende do spec. Isole a mudanca
 * nos metodos authenticate() e authHeaders() — o resto da classe nao muda.
 */
final class HttpClient
{
    private ?string $accessToken = null;
    private ?int $tokenExpiresAt = null;

    public function __construct(
        private readonly Config $config,
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {
        if (!\extension_loaded('curl')) {
            throw new \RuntimeException('A extensao curl do PHP e obrigatoria.');
        }
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    /**
     * @param bool $longPoll Marca a chamada como ciclo de acompanhamento:
     *                       timeout passa a significar "sem novidade" em vez
     *                       de erro. Nao altera o tempo limite.
     */
    public function get(string $route, array $query = [], bool $longPoll = false): array
    {
        $url = $route . ($query !== [] ? '?' . http_build_query($query) : '');

        return $this->request('GET', $url, null, longPoll: $longPoll);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function post(string $route, array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->request('POST', $route, $body, idempotencyKey: $idempotencyKey);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function put(string $route, array $body = []): array
    {
        return $this->request('PUT', $route, $body);
    }

    /** @return array<string,mixed> */
    public function delete(string $route): array
    {
        return $this->request('DELETE', $route, null);
    }

    /**
     * Executa a requisicao com retry.
     *
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(
        string $method,
        string $route,
        ?array $body,
        bool $longPoll = false,
        ?string $idempotencyKey = null,
        int $attempt = 1,
        bool $alreadyReauthenticated = false,
    ): array {
        $maxAttempts = 3;

        try {
            return $this->execute($method, $route, $body, $longPoll, $idempotencyKey);
        } catch (AuthenticationException $e) {
            // Token vencido no meio do caminho: renova uma unica vez.
            if ($alreadyReauthenticated) {
                throw $e;
            }

            $this->accessToken = null;
            $this->tokenExpiresAt = null;
            $this->limparSessao();

            return $this->request(
                $method, $route, $body, $longPoll, $idempotencyKey,
                attempt: $attempt,
                alreadyReauthenticated: true,
            );
        } catch (ApiException $e) {
            /* POST nunca e repetido automaticamente: a API nao documenta
               idempotencia, e repetir a criacao de uma venda ou de um boleto
               pode cobrar o cliente duas vezes. */
            $safeToRetry = $e->isRetryable() && $method === 'GET';

            if (!$safeToRetry || $attempt >= $maxAttempts) {
                throw $e;
            }

            // Backoff exponencial com jitter: 200ms, 400ms (+- 50ms).
            $delayMs = (200 * (2 ** ($attempt - 1))) + random_int(0, 50);
            usleep($delayMs * 1000);

            $this->logger?->warning('Parcela Express: retry', [
                'method' => $method,
                'route' => $route,
                'attempt' => $attempt + 1,
                'status' => $e->statusCode,
            ]);

            return $this->request(
                $method, $route, $body, $longPoll, $idempotencyKey,
                attempt: $attempt + 1,
                alreadyReauthenticated: $alreadyReauthenticated,
            );
        }
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function execute(
        string $method,
        string $route,
        ?array $body,
        bool $longPoll,
        ?string $idempotencyKey,
    ): array {
        $url = rtrim($this->config->baseUrl(), '/') . '/' . ltrim($route, '/');

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: TCloud-Atlas/' . (\defined('PE_VERSAO') ? PE_VERSAO : '2') . ' (+PHP ' . PHP_VERSION . ')',
        ];

        foreach ($this->authHeaders($route) as $header) {
            $headers[] = $header;
        }

        /* Sem cabecalho de idempotencia: a API nao o documenta. A protecao
           contra duplicidade fica no registro local. */

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $this->config->connectTimeout,
            CURLOPT_TIMEOUT => $this->config->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->config->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->config->verifyTls ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $this->aplicarTls($ch);

        if ($body !== null) {
            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );
        }

        $rawBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($rawBody === false) {
            /* Num ciclo de acompanhamento, timeout nao e falha: significa
               "ainda sem novidade". Quem chamou decide se tenta de novo. */
            if ($longPoll && \in_array($curlErrno, [CURLE_OPERATION_TIMEDOUT, 28], true)) {
                return ['__timeout' => true];
            }

            throw new ApiException(
                self::mensagemTransporte($curlErrno, $curlError, "{$method} {$route}"),
                statusCode: 0,
            );
        }

        $rawBody = (string) $rawBody;

        // 304: nada mudou desde a ultima leitura. Nao e erro.
        if ($status === 304) {
            return ['__not_modified' => true];
        }

        if ($status === 204 || $rawBody === '') {
            return [];
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ApiException(
                "Resposta nao-JSON de {$method} {$route}.",
                statusCode: $status,
                rawBody: $rawBody,
                previous: $e,
            );
        }

        if (!\is_array($payload)) {
            $payload = ['data' => $payload];
        }

        if ($status >= 400) {
            throw $this->buildException($status, $payload, $rawBody, $method, $route);
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function buildException(
        int $status,
        array $payload,
        string $rawBody,
        string $method,
        string $route,
    ): ApiException {
        $message = $this->extractMessage($payload)
            ?? sprintf('Erro HTTP %d em %s %s.', $status, $method, $route);

        if (str_contains(mb_strtolower($message), 'operation cost') && !str_contains($message, 'custo da operação')) {
            $message = self::traduzirErro($message);
        }

        return match (true) {
            $status === 401 || $status === 403 => new AuthenticationException(
                $message, $status, $payload, $rawBody
            ),
            $status === 400 || $status === 422 => new ValidationException(
                $message, $status, $payload, $rawBody
            ),
            default => new ApiException($message, $status, $payload, $rawBody),
        };
    }

    /** @param array<string,mixed> $payload */
    /**
     * Extrai a mensagem util do corpo de erro.
     *
     * O NestJS com ValidationPipe devolve {"message": ["campo x deve ...",
     * "campo y ..."], "error": "Bad Request"} — a lista e o que interessa.
     * Quando a excecao e lancada sem detalhes, message vira apenas
     * "Bad Request Exception", que nao diz nada; nesse caso preferimos
     * qualquer outro campo do corpo a repetir o texto vazio.
     *
     * @param array<string,mixed> $payload
     */
    private function extractMessage(array $payload): ?string
    {
        $inutil = static fn (string $t): bool => (bool) preg_match(
            '/^(bad request|unauthorized|forbidden|not found|internal server error)( exception)?$/i',
            trim($t)
        );

        /* Formato da v2: {"message": "...", "errors": ["...", ...]} */
        if (isset($payload['errors']) && \is_array($payload['errors']) && $payload['errors'] !== []) {
            $itens = [];
            foreach ($payload['errors'] as $campo => $err) {
                if (\is_array($err)) {
                    $err = $err['message'] ?? $err['msg'] ?? json_encode($err, JSON_UNESCAPED_UNICODE);
                }
                $err = trim((string) $err);
                if ($err !== '') {
                    $itens[] = \is_string($campo) ? $campo . ': ' . $err : $err;
                }
            }
            if ($itens !== []) {
                $principal = isset($payload['message']) && \is_string($payload['message']) && !$inutil($payload['message'])
                    ? trim($payload['message']) . ': '
                    : '';
                return $principal . implode(' | ', array_map([self::class, 'traduzirErro'], $itens));
            }
        }

        // Lista de validacao: e o caso mais informativo.
        if (isset($payload['message']) && \is_array($payload['message'])) {
            $itens = array_filter(array_map('strval', $payload['message']));

            if ($itens !== []) {
                return implode(' | ', array_map([self::class, 'traduzirErro'], $itens));
            }
        }

        $generico = null;

        foreach (['message', 'error', 'detail', 'title', 'description'] as $key) {
            if (!isset($payload[$key])) {
                continue;
            }

            $valor = \is_array($payload[$key])
                ? json_encode($payload[$key], JSON_UNESCAPED_UNICODE)
                : (string) $payload[$key];

            if ($valor === '') {
                continue;
            }

            if ($inutil($valor)) {
                $generico = $generico ?? $valor;
                continue;
            }

            return $valor;
        }

        /* Só sobrou texto genérico: melhor devolver o corpo inteiro, que ao
           menos mostra quais campos a API enxergou. */
        if ($generico !== null) {
            $corpo = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $corpo !== false && \strlen($corpo) <= 800
                ? $generico . ' — resposta: ' . $corpo
                : $generico;
        }

        return null;
    }

    // -------------------------------------------------------------------
    // Autenticacao (POST /v2/auth/login -> id_token + seller.id)
    // -------------------------------------------------------------------

    private ?string $loginSellerId = null;

    /** Nome e documento do estabelecimento, quando o login informa. @var array<string,string> */
    private array $loginSellerInfo = [];

    /** @return list<string> */
    private function authHeaders(string $route): array
    {
        // A rota de login nao leva token.
        if (str_contains($route, Endpoints::path('AUTH_LOGIN'))) {
            return [];
        }

        return ['Authorization: Bearer ' . $this->token()];
    }

    private function token(): string
    {
        $valid = $this->accessToken !== null
            && ($this->tokenExpiresAt === null || $this->tokenExpiresAt > time() + 30);

        if (!$valid && !$this->carregarSessao()) {
            $this->authenticate();
        }

        return (string) $this->accessToken;
    }

    /**
     * ID do estabelecimento usado nas rotas.
     *
     * Vem do PROPRIO LOGIN (seller.id). O valor digitado na
     * configuracao so e usado se o login nao trouxer o seller — antes ele era
     * a unica fonte, e um ID diferente do da conta fazia toda chamada falhar.
     */
    public function sellerId(): string
    {
        $this->token();

        $seller = $this->loginSellerId ?: $this->config->sellerId;

        if ($seller === '') {
            throw new AuthenticationException(
                'O login não informou o estabelecimento (seller) e nenhum ID foi configurado. '
                . 'Informe o ID do estabelecimento na configuração.',
                statusCode: 401,
            );
        }

        return $seller;
    }

    /** Nome/documento do estabelecimento devolvidos pelo login (para o teste de conexao). */
    public function loginSellerInfo(): array
    {
        return $this->loginSellerInfo;
    }

    /** Seller devolvido pelo login (ou null se ainda nao houve login). */
    public function loginSellerId(): ?string
    {
        return $this->loginSellerId;
    }

    private function chaveSessao(): string
    {
        return 'pe_token_' . md5($this->config->baseUrl() . '|' . $this->config->username . '|' . sha1($this->config->password));
    }

    /** Reaproveita o token da sessao PHP (evita um login a cada clique). */
    private function carregarSessao(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $s = $_SESSION[$this->chaveSessao()] ?? null;

        if (!\is_array($s) || empty($s['token']) || (int) ($s['expira'] ?? 0) <= time() + 60) {
            return false;
        }

        $this->accessToken = (string) $s['token'];
        $this->tokenExpiresAt = (int) $s['expira'];
        $this->loginSellerId = isset($s['seller']) && $s['seller'] !== '' ? (string) $s['seller'] : null;
        $this->loginSellerInfo = \is_array($s['seller_info'] ?? null) ? $s['seller_info'] : [];

        return true;
    }

    private function salvarSessao(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION[$this->chaveSessao()] = [
            'token' => $this->accessToken,
            'expira' => $this->tokenExpiresAt ?? (time() + 600),
            'seller' => $this->loginSellerId,
            'seller_info' => $this->loginSellerInfo,
        ];
    }

    private function limparSessao(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[$this->chaveSessao()]);
        }
    }

    /** Validade do JWT (campo exp), quando legivel. */
    private static function expiracaoJwt(string $token): ?int
    {
        $partes = explode('.', $token);

        if (\count($partes) !== 3) {
            return null;
        }

        $json = base64_decode(strtr($partes[1], '-_', '+/') . str_repeat('=', (4 - \strlen($partes[1]) % 4) % 4), true);
        $dados = \is_string($json) ? json_decode($json, true) : null;

        return \is_array($dados) && isset($dados['exp']) && is_numeric($dados['exp']) ? (int) $dados['exp'] : null;
    }

    /** Nome e documento do estabelecimento na resposta de login. */
    private static function infoSeller(array $payload): array
    {
        $sel = $payload['seller'] ?? $payload['data']['seller'] ?? $payload['user']['seller'] ?? null;
        if (!\is_array($sel)) {
            return [];
        }

        $pega = static function (array $chaves) use ($sel): string {
            foreach ($chaves as $k) {
                if (isset($sel[$k]) && (\is_string($sel[$k]) || \is_int($sel[$k])) && (string) $sel[$k] !== '') {
                    return (string) $sel[$k];
                }
            }
            return '';
        };

        return array_filter([
            'nome' => $pega(['name', 'trading_name', 'company_name', 'legal_name', 'razao_social', 'nome']),
            'documento' => $pega(['document', 'cnpj', 'cpf', 'social_security_number', 'document_number', 'tax_id']),
            'email' => $pega(['email']),
        ], static fn($v) => $v !== '');
    }

    /** Seller da resposta de login: seller.id, seller_id, sellerId... */
    private static function findSeller(array $payload): ?string
    {
        $candidatos = [
            $payload['seller']['id'] ?? null,
            $payload['data']['seller']['id'] ?? null,
            $payload['seller_id'] ?? null,
            $payload['sellerId'] ?? null,
            $payload['user']['seller_id'] ?? null,
            $payload['user']['seller']['id'] ?? null,
            $payload['data']['seller_id'] ?? null,
        ];

        foreach ($candidatos as $c) {
            if ((\is_string($c) || \is_int($c)) && (string) $c !== '') {
                return (string) $c;
            }
        }

        return null;
    }

    /**
     * Procura o token em qualquer profundidade da resposta.
     *
     * Cada gateway nomeia esse campo de um jeito: access_token, accessToken,
     * token, jwt, idToken, data.token, authentication.accessToken... Em vez
     * de tentar adivinhar a combinação certa, varremos a estrutura atrás de
     * uma chave com cara de token cujo valor seja uma string plausível.
     *
     * @param array<mixed> $payload
     */
    private static function findToken(array $payload, int $profundidade = 0): ?string
    {
        if ($profundidade > 5) {
            return null;
        }

        $chavesToken = '/^(access[_-]?token|auth[_-]?token|id[_-]?token|bearer[_-]?token|token|jwt|bearer)$/i';

        foreach ($payload as $chave => $valor) {
            if (is_string($valor) && $valor !== '' && is_string($chave) && preg_match($chavesToken, $chave)) {
                return $valor;
            }
        }

        foreach ($payload as $valor) {
            if (is_array($valor)) {
                $achado = self::findToken($valor, $profundidade + 1);

                if ($achado !== null) {
                    return $achado;
                }
            }
        }

        return null;
    }

    /**
     * Descreve a estrutura de uma resposta sem expor valores.
     *
     * Serve para o diagnóstico: mostra QUAIS campos vieram, para que o nome
     * correto do token seja identificado sem que senha ou token apareçam em
     * tela ou em log.
     *
     * @param array<mixed> $payload
     * @return list<string>
     */
    public static function describeShape(array $payload, string $prefixo = '', int $profundidade = 0): array
    {
        $campos = [];

        if ($profundidade > 4) {
            return $campos;
        }

        foreach ($payload as $chave => $valor) {
            $caminho = $prefixo === '' ? (string) $chave : $prefixo . '.' . $chave;

            if (is_array($valor)) {
                $filhos = self::describeShape($valor, $caminho, $profundidade + 1);
                $campos = $filhos !== [] ? array_merge($campos, $filhos) : array_merge($campos, [$caminho . ' (vazio)']);
                continue;
            }

            $tipo = get_debug_type($valor);

            if (is_string($valor)) {
                $tamanho = function_exists('mb_strlen') ? mb_strlen($valor) : strlen($valor);
                $tipo = 'string(' . $tamanho . ')';
            }

            $campos[] = $caminho . ': ' . $tipo;
        }

        return $campos;
    }

    /**
     * Executa o login e devolve a resposta bruta, sem lançar exceção.
     * Usado apenas pela tela de diagnóstico.
     *
     * @return array{status:int, body:string, json:array<mixed>|null, erro:string|null}
     */
    public function probeLogin(): array
    {
        $url = rtrim($this->config->baseUrl(), '/') . '/' . ltrim(Endpoints::path('AUTH_LOGIN'), '/');

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => $this->config->connectTimeout,
            CURLOPT_TIMEOUT => $this->config->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->config->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $this->config->verifyTls ? 2 : 0,
            CURLOPT_POSTFIELDS => json_encode([
                'email' => $this->config->username,
                'password' => $this->config->password,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $this->aplicarTls($ch);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch) ? self::mensagemTransporte(curl_errno($ch), curl_error($ch), 'login') : null;
        curl_close($ch);

        $body = is_string($body) ? $body : '';
        $json = null;

        if ($body !== '') {
            $decodificado = json_decode($body, true);
            $json = is_array($decodificado) ? $decodificado : null;
        }

        return ['status' => $status, 'body' => $body, 'json' => $json, 'erro' => $erro];
    }

    private function authenticate(): void
    {
        $this->limparSessao();

        if ($this->config->username === '' || $this->config->password === '') {
            throw new AuthenticationException('Usuário e senha da API não configurados.', statusCode: 401);
        }

        try {
            $response = $this->execute(
                'POST',
                Endpoints::path('AUTH_LOGIN'),
                [
                    'email' => $this->config->username,
                    'password' => $this->config->password,
                ],
                longPoll: false,
                idempotencyKey: null,
            );
        } catch (AuthenticationException|ValidationException $e) {
            throw new AuthenticationException(
                'Login recusado pela Parcela Express: confira o e-mail e a senha da API (os mesmos do portal). '
                . 'Detalhe: ' . $e->getMessage(),
                statusCode: $e->statusCode,
                payload: $e->payload,
                rawBody: $e->rawBody,
            );
        }

        $token = self::findToken($response);

        if ($token === null) {
            $campos = self::describeShape($response);

            throw new AuthenticationException(
                'O login respondeu, mas nenhum campo de token foi reconhecido. '
                . 'Campos recebidos: ' . ($campos === [] ? '(resposta vazia)' : implode(', ', $campos)) . '.',
                statusCode: 401,
                payload: null,
            );
        }

        $this->accessToken = $token;
        $this->loginSellerId = self::findSeller($response);
        $this->loginSellerInfo = self::infoSeller($response);

        $expiresIn = $response['expires_in']
            ?? $response['expiresIn']
            ?? $response['data']['expires_in']
            ?? $response['data']['expiresIn']
            ?? null;

        $this->tokenExpiresAt = is_numeric($expiresIn)
            ? time() + (int) $expiresIn
            : (self::expiracaoJwt($token) ?? time() + 1800);

        $this->salvarSessao();
    }

    // -------------------------------------------------------------------
    // TLS e mensagens de transporte
    // -------------------------------------------------------------------

    /** @param \CurlHandle $ch */
    private function aplicarTls($ch): void
    {
        if (!$this->config->verifyTls) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            return;
        }

        /* Usa o pacote do modulo quando o PHP nao tem um configurado — o caso
           do XAMPP no Windows, que derrubava as chamadas com erro 60. */
        $ini = (string) ini_get('curl.cainfo');
        $bundle = $this->config->caBundle();

        if (($ini === '' || !is_file($ini)) && $bundle !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $bundle);
        }
    }

    public static function mensagemTransporte(int $errno, string $erro, string $onde): string
    {
        return match (true) {
            \in_array($errno, [60, 77, 35, 51, 58, 59, 83], true) =>
                'Falha de certificado SSL ao conectar na Parcela Express (' . $erro . '). '
                . 'Confira se lib/cacert.pem existe ou, em último caso, desative "Verificar certificado SSL" na configuração.',
            \in_array($errno, [6, 7], true) =>
                'Não foi possível conectar à Parcela Express (' . $erro . '). Verifique a internet do servidor e a URL da API.',
            \in_array($errno, [28], true) =>
                'A Parcela Express demorou demais para responder (' . $onde . ').',
            default => "Falha de transporte ao chamar {$onde}: {$erro}",
        };
    }

    /** Deixa mensagens comuns da API legiveis para o operador. */
    public static function traduzirErro(string $erro): string
    {
        $e = mb_strtolower($erro);

        if (str_contains($e, 'less than operation cost') || str_contains($e, 'operation cost')) {
            return 'Valor abaixo do custo da operação na Parcela Express — o valor mínimo depende do plano da serventia ('
                . $erro . ')';
        }

        return match (true) {
            str_contains($e, 'social_security_number') => 'CPF/CNPJ do pagador ausente ou inválido (' . $erro . ')',
            str_contains($e, 'first_name') || str_contains($e, 'last_name') => 'Nome do pagador obrigatório (' . $erro . ')',
            str_contains($e, 'email') => 'E-mail do pagador ausente ou inválido (' . $erro . ')',
            str_contains($e, 'phone') => 'Telefone do pagador ausente ou inválido (' . $erro . ')',
            str_contains($e, 'postal_code') => 'CEP do pagador inválido (' . $erro . ')',
            str_contains($e, 'terminal') => 'Terminal inválido ou indisponível (' . $erro . ')',
            default => $erro,
        };
    }
}
