<?php

declare(strict_types=1);

namespace TCloud\ParcelaExpress;

/**
 * Configuracao da integracao.
 *
 * No Atlas os valores vem da tabela pe_config (linha unica id=1), preenchida
 * pela pagina de configuracao restrita a administradores.
 */
final class Config
{
    public const ENV_SANDBOX = 'sandbox';
    public const ENV_PRODUCTION = 'production';

    private const BASE_URLS = [
        self::ENV_SANDBOX => 'https://sandbox.parcelaexpress.com.br',
        // TODO: confirmar a base de producao com a Parcela Express.
        self::ENV_PRODUCTION => 'https://api.parcelaexpress.com.br',
    ];

    public function __construct(
        public readonly string $environment,
        public readonly string $sellerId,
        public readonly string $username,
        public readonly string $password,
        public readonly ?string $baseUrlOverride = null,
        public readonly int $connectTimeout = 10,
        public readonly int $timeout = 30,
        /**
         * Intervalo entre consultas de status da venda, em segundos.
         *
         * Nao e timeout de long polling: a API responde na hora, entao este
         * valor e o descanso entre um ciclo e outro.
         */
        public readonly int $pollIntervalSeconds = 3,
        public readonly bool $verifyTls = true,
    ) {
        if (!isset(self::BASE_URLS[$environment])) {
            throw new \InvalidArgumentException("Ambiente invalido: {$environment}");
        }
    }

    /**
     * Monta a configuracao a partir da linha de pe_config.
     *
     * @param array<string,mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            environment: (string) ($row['ambiente'] ?? self::ENV_SANDBOX),
            sellerId: (string) ($row['seller_id'] ?? ''),
            username: (string) ($row['usuario'] ?? ''),
            password: (string) ($row['senha'] ?? ''),
            baseUrlOverride: ($row['base_url'] ?? '') !== '' ? (string) $row['base_url'] : null,
            pollIntervalSeconds: self::intervaloValido($row['timeout_poll_seg'] ?? null),
        );
    }

    /**
     * Normaliza o intervalo entre consultas.
     *
     * Instalacoes anteriores gravaram 60 neste campo, quando ele era timeout
     * de long polling. Manter 60 faria a tela demorar um minuto para reagir,
     * entao qualquer valor acima da faixa util volta ao padrao em vez de ser
     * apenas truncado.
     */
    private static function intervaloValido(mixed $valor): int
    {
        $n = (int) $valor;

        return ($n >= 1 && $n <= 30) ? $n : 3;
    }

    public function baseUrl(): string
    {
        return $this->baseUrlOverride ?? self::BASE_URLS[$this->environment];
    }

    public function isSandbox(): bool
    {
        return $this->environment === self::ENV_SANDBOX;
    }
}
