<?php

declare(strict_types=1);

namespace TCloud\ParcelaExpress;

/**
 * Configuracao da integracao.
 *
 * No Atlas os valores vem da tabela pe_config (linha unica id=1), preenchida
 * pela pagina de configuracao restrita a administradores.
 *
 * v2.0 — integracao com a API v2:
 *  - o ID do estabelecimento (seller) vem do PROPRIO LOGIN (/v2/auth/login
 *    devolve seller.id). O campo seller_id da configuracao virou reserva,
 *    usado apenas se o login nao trouxer o seller;
 *  - os valores vao para a API em CENTAVOS (inteiro). Boleto e maquininha
 *    tem a unidade ajustavel em separado;
 *  - TLS verificado com o pacote de certificados que acompanha o modulo
 *    (lib/cacert.pem) — o XAMPP no Windows nao traz um, e era isso que
 *    derrubava as chamadas com "SSL certificate problem".
 */
final class Config
{
    public const ENV_SANDBOX = 'sandbox';
    public const ENV_PRODUCTION = 'production';

    public const UNIDADE_REAIS = 'reais';
    public const UNIDADE_CENTAVOS = 'centavos';

    /** Conta de homologação (sandbox) da Parcela Express. */
    public const HOMOLOGACAO_EMAIL = 'george@parcelaexpress.com.br';
    public const HOMOLOGACAO_SENHA = '123456';

    private const BASE_URLS = [
        self::ENV_SANDBOX => 'https://sandbox.parcelaexpress.com.br',
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
        /** Intervalo entre consultas de status da venda, em segundos. */
        public readonly int $pollIntervalSeconds = 3,
        public readonly bool $verifyTls = true,
        /** Unidade do valor do BOLETO na API: 'centavos' (padrao) ou 'reais'. */
        public readonly string $valueUnit = self::UNIDADE_CENTAVOS,
        /** Unidade do valor da venda na MAQUININHA: 'centavos' (padrao) ou 'reais'. */
        public readonly string $posUnit = self::UNIDADE_CENTAVOS,
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
        $ambiente = (string) ($row['ambiente'] ?? self::ENV_SANDBOX) === self::ENV_PRODUCTION
            ? self::ENV_PRODUCTION : self::ENV_SANDBOX;

        /* Em sandbox, a opção "usar conta de homologação" troca as credenciais
           da serventia pela conta de testes da Parcela Express. */
        $homologacao = $ambiente === self::ENV_SANDBOX && !empty($row['usar_homologacao']);

        $unidade = (string) ($row['unidade_boleto'] ?? self::UNIDADE_CENTAVOS);
        $unidadePos = (string) ($row['unidade_pos'] ?? self::UNIDADE_CENTAVOS);

        return new self(
            environment: $ambiente,
            sellerId: $homologacao ? '' : trim((string) ($row['seller_id'] ?? '')),
            username: $homologacao ? self::HOMOLOGACAO_EMAIL : trim((string) ($row['usuario'] ?? '')),
            password: $homologacao ? self::HOMOLOGACAO_SENHA : trim((string) ($row['senha'] ?? '')),
            baseUrlOverride: trim((string) ($row['base_url'] ?? '')) !== '' ? rtrim(trim((string) $row['base_url']), '/') : null,
            pollIntervalSeconds: self::intervaloValido($row['timeout_poll_seg'] ?? null),
            verifyTls: !array_key_exists('verificar_ssl', $row) || (int) $row['verificar_ssl'] === 1,
            valueUnit: $unidade === self::UNIDADE_REAIS ? self::UNIDADE_REAIS : self::UNIDADE_CENTAVOS,
            posUnit: $unidadePos === self::UNIDADE_REAIS ? self::UNIDADE_REAIS : self::UNIDADE_CENTAVOS,
        );
    }

    /**
     * Normaliza o intervalo entre consultas (instalacoes antigas gravaram 60).
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

    /** Pacote de certificados que acompanha o modulo (ou null se ausente). */
    public function caBundle(): ?string
    {
        $arquivo = __DIR__ . '/cacert.pem';

        return is_file($arquivo) ? $arquivo : null;
    }

    /**
     * Centavos -> valor no formato que a API espera.
     *
     * v2.0.1: o padrao passou a ser CENTAVOS. Enviando 13.37 a API respondeu
     * "The amount: 13.37 given is less than operation cost" — ela le o
     * numero como centavos (R$ 0,13).
     *
     * @param string $tipo 'boleto' ou 'pos'
     */
    public function paraApi(int $centavos, string $tipo = 'boleto'): int|float
    {
        return $this->unidade($tipo) === self::UNIDADE_CENTAVOS
            ? $centavos
            : round($centavos / 100, 2);
    }

    /** Valor vindo da API -> centavos. */
    public function daApi(mixed $valor, string $tipo = 'boleto'): ?int
    {
        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return null;
        }

        return $this->unidade($tipo) === self::UNIDADE_CENTAVOS
            ? (int) round((float) $valor)
            : (int) round(((float) $valor) * 100);
    }

    private function unidade(string $tipo): string
    {
        return $tipo === 'pos' ? $this->posUnit : $this->valueUnit;
    }
}
