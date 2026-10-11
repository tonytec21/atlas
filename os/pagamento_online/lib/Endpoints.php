<?php

declare(strict_types=1);

namespace TCloud\ParcelaExpress;

/**
 * Mapa central de rotas da API Parcela Express.
 *
 * v2.0 — rotas da API v2:
 *
 *   POST /v2/auth/login                      -> id_token + seller.id
 *   POST /v2/billets/{seller_id}             -> boleto (corpo = LISTA de boletos)
 *   POST /v2/pos/payments/{seller_id}        -> venda na maquininha
 *   GET  /v2/sellers/{seller_id}/sales/{id}  -> status da venda (POS e boleto)
 *   GET  /v2/pos/terminals/{seller_id}       -> terminais
 *
 * As rotas v1 usadas antes nao funcionavam com a conta da serventia.
 *
 * Placeholders no formato {nome} sao substituidos por Endpoints::build().
 * Overrides gravados em pe_rotas tem precedencia — ver Endpoints::path().
 */
final class Endpoints
{
    // ---------------------------------------------------------------
    // Autenticacao
    // ---------------------------------------------------------------

    /** POST — login. Responde { id_token, seller: { id } }. */
    public const AUTH_LOGIN = '/v2/auth/login';

    // ---------------------------------------------------------------
    // POS / maquininha
    // ---------------------------------------------------------------

    /** GET — terminais do estabelecimento (com rotas alternativas abaixo). */
    public const POS_TERMINALS = '/v2/pos/terminals/{seller_id}';

    /** Alternativas tentadas, em ordem, se POS_TERMINALS nao responder. */
    public const POS_TERMINALS_ALTERNATIVAS = [
        '/v2/sellers/{seller_id}/terminals',
        '/v1/sellers/{seller_id}/pos',
    ];

    /** GET — simulacao de parcelas (opcional: sem ela a tela mostra as formas basicas). */
    public const POS_INSTALLMENT_SIMULATION = '/v1/simulation/sellers/{seller_id}';

    /** POST — cria a venda no terminal. */
    public const POS_SALE_CREATE = '/v2/pos/payments/{seller_id}';

    /** GET — status da venda. */
    public const POS_SALE_STATUS = '/v2/sellers/{seller_id}/sales/{sale_id}';

    /** POST — cancela a venda no terminal (id da venda no corpo). */
    public const POS_SALE_INTERRUPT = '/v1/pos/cancel_sale/{seller_id}';

    /** GET — vendas de um terminal. */
    public const POS_SALE_LIST = '/v1/sellers/{seller_id}/sales/pos/{terminal_id}';

    // ---------------------------------------------------------------
    // Boleto
    // ---------------------------------------------------------------

    /** POST — emite boleto(s). O corpo e uma LISTA; a resposta tambem. */
    public const BOLETO_CREATE = '/v2/billets/{seller_id}';

    /**
     * GET — situacao do boleto. Consultada pela VENDA (sale_id devolvido na
     * emissao). BOLETO_SHOW fica como reserva para boletos
     * antigos que so tem o id do titulo.
     */
    public const BOLETO_STATUS = '/v2/sellers/{seller_id}/sales/{sale_id}';

    /** GET — boleto pelo id do titulo (reserva). */
    public const BOLETO_SHOW = '/v1/billets/{billet_id}';

    /** GET — URL do PDF (reserva: na v2 a URL ja vem na emissao). */
    public const BOLETO_URL = '/v1/billets/{billet_id}/url';

    /** POST — baixa do titulo. */
    public const BOLETO_CANCEL = '/v1/billets/{seller_id}/{billet_id}/void';

    /** GET — boletos do estabelecimento. */
    public const BOLETO_LIST = '/v1/sellers/{seller_id}/billets';

    /** POST — marca o boleto como pago. SOMENTE EM SANDBOX. */
    public const BOLETO_PAGAR_SANDBOX = '/v1/billets/{billet_id}/pay';

    // ---------------------------------------------------------------

    /**
     * Rotas que a versao anterior do modulo usava. Se um override gravado em
     * pe_rotas for igual a uma delas, ele e ignorado: era o padrao antigo (que
     * nao funcionava), nao uma escolha do administrador.
     */
    private const ROTAS_ANTIGAS = [
        'AUTH_LOGIN' => ['/v1/auth/login'],
        'POS_TERMINALS' => ['/v1/sellers/{seller_id}/pos'],
        'POS_SALE_CREATE' => ['/v1/pos/payments/{seller_id}'],
        'POS_SALE_STATUS' => ['/v1/sellers/{seller_id}/sales/{sale_id}'],
        'BOLETO_CREATE' => ['/v1/billets/{seller_id}'],
    ];

    /** @var array<string,string> */
    private static array $overrides = [];

    /**
     * @param array<string,string> $mapa chave da constante => rota
     */
    public static function aplicarOverrides(array $mapa): void
    {
        foreach ($mapa as $chave => $rota) {
            $chave = strtoupper((string) $chave);
            $rota = trim((string) $rota);

            if ($rota === '') {
                continue;
            }

            $normal = preg_replace('/\{\w+\}/', '{}', $rota);
            foreach (self::ROTAS_ANTIGAS[$chave] ?? [] as $antiga) {
                if (preg_replace('/\{\w+\}/', '{}', $antiga) === $normal) {
                    continue 2;   // padrao antigo: ignora
                }
            }

            self::$overrides[$chave] = $rota;
        }
    }

    /** Rota efetiva de uma operacao: o override, se houver, senao a constante. */
    public static function path(string $chave): string
    {
        $chave = strtoupper($chave);

        if (isset(self::$overrides[$chave])) {
            return self::$overrides[$chave];
        }

        $constante = self::class . '::' . $chave;

        if (!\defined($constante)) {
            throw new \InvalidArgumentException("Rota desconhecida: {$chave}");
        }

        $valor = \constant($constante);

        return \is_array($valor) ? (string) reset($valor) : (string) $valor;
    }

    /** Rotas com override aplicado, para exibicao no diagnostico. */
    public static function comOverride(): array
    {
        return array_keys(self::$overrides);
    }

    /**
     * Substitui os placeholders da rota.
     *
     * @param array<string,string|int> $params
     */
    public static function build(string $route, array $params = []): string
    {
        foreach ($params as $key => $value) {
            $route = str_replace('{' . $key . '}', rawurlencode((string) $value), $route);
        }

        /* {sellerId} (camelCase) tambem vale para seller_id. */
        if (isset($params['seller_id'])) {
            $route = str_replace('{sellerId}', rawurlencode((string) $params['seller_id']), $route);
        }
        if (isset($params['sale_id'])) {
            $route = str_replace('{saleId}', rawurlencode((string) $params['sale_id']), $route);
        }

        if (preg_match('/\{(\w+)\}/', $route, $m) === 1) {
            throw new \InvalidArgumentException(
                sprintf('Parametro "%s" nao informado para a rota "%s".', $m[1], $route)
            );
        }

        return $route;
    }
}
