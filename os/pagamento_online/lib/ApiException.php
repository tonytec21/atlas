<?php

declare(strict_types=1);

namespace TCloud\ParcelaExpress;

/**
 * Erro retornado pela API ou pelo transporte.
 *
 * Guarda o corpo bruto da resposta porque, em integracao de pagamento,
 * o payload de erro e a unica prova do que aconteceu numa conciliacao.
 */
class ApiException extends \RuntimeException
{
    /**
     * @param array<string,mixed>|null $payload
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly ?array $payload = null,
        public readonly ?string $rawBody = null,
        public readonly ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /** Falha de rede, DNS ou timeout — nao houve resposta da API. */
    public function isTransportError(): bool
    {
        return $this->statusCode === 0;
    }

    /**
     * A API respondeu, mas nao conhece este caminho.
     *
     * O Express devolve 404 com "Cannot POST /rota" em texto puro; o NestJS
     * devolve 404 com JSON. Os dois significam a mesma coisa e pedem a mesma
     * providencia: mapear a rota certa, nao mexer em credencial.
     */
    public function isRouteMissing(): bool
    {
        if ($this->statusCode === 404 || $this->statusCode === 405) {
            return true;
        }

        $corpo = (string) $this->rawBody;

        return $corpo !== '' && preg_match('/Cannot (GET|POST|PUT|PATCH|DELETE)\s/i', $corpo) === 1;
    }

    /**
     * A requisicao pode ser repetida com seguranca?
     *
     * Cuidado: para POST de criacao de venda, so repita se estiver enviando
     * chave de idempotencia. Ver PosSaleService::create().
     */
    public function isRetryable(): bool
    {
        return $this->isTransportError()
            || $this->statusCode === 429
            || $this->statusCode >= 500;
    }
}

/** Token invalido, expirado ou ausente. */
final class AuthenticationException extends ApiException
{
}

/** Payload rejeitado por validacao (tipicamente 400/422). */
final class ValidationException extends ApiException
{
    /** @return array<string,mixed> */
    public function fieldErrors(): array
    {
        return $this->payload['errors'] ?? $this->payload['message'] ?? [];
    }
}
