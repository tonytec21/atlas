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

            return $this->request(
                $method, $route, $body, $longPoll, $idempotencyKey,
                attempt: $attempt,
                alreadyReauthenticated: true,
            );
        } catch (ApiException $e) {
            $safeToRetry = $e->isRetryable()
                && ($method !== 'POST' || $idempotencyKey !== null);

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
            'User-Agent: TCloud-Atlas/1.0 (+PHP ' . PHP_VERSION . ')',
        ];

        foreach ($this->authHeaders($route) as $header) {
            $headers[] = $header;
        }

        if ($idempotencyKey !== null) {
            // TODO: confirmar no spec o nome do header de idempotencia.
            // Se a API nao suportar, a protecao fica por conta do sale_id
            // enviado no corpo — ver PosSaleService::create().
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

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
                "Falha de transporte ao chamar {$method} {$route}: {$curlError}",
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

        // Lista de validacao: e o caso mais informativo.
        if (isset($payload['message']) && \is_array($payload['message'])) {
            $itens = array_filter(array_map('strval', $payload['message']));

            if ($itens !== []) {
                return implode(' | ', $itens);
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
    // Autenticacao — AJUSTAR quando o spec chegar
    // -------------------------------------------------------------------

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

        if (!$valid) {
            $this->authenticate();
        }

        return (string) $this->accessToken;
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

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch) ?: null;
        curl_close($ch);

        $body = is_string($body) ? $body : '';
        $json = null;

        if ($body !== '') {
            $decodificado = json_decode($body, true);
            $json = is_array($decodificado) ? $decodificado : null;
        }

        return ['status' => $status, 'body' => $body, 'json' => $json, 'erro' => $erro];
    }

    /**
     * TODO: confirmar rota e nomes dos campos no spec.
     */
    private function authenticate(): void
    {
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

        $token = self::findToken($response);

        if ($token === null) {
            $campos = self::describeShape($response);

            throw new AuthenticationException(
                'O login respondeu, mas nenhum campo de token foi reconhecido. '
                . 'Campos recebidos: ' . ($campos === [] ? '(resposta vazia)' : implode(', ', $campos))
                . '. Use "Verificar instalação" para inspecionar a resposta.',
                statusCode: 0,
                payload: null,
            );
        }

        $this->accessToken = $token;

        $expiresIn = $response['expires_in']
            ?? $response['expiresIn']
            ?? $response['data']['expires_in']
            ?? $response['data']['expiresIn']
            ?? null;

        $this->tokenExpiresAt = is_numeric($expiresIn) ? time() + (int) $expiresIn : null;
    }
}
