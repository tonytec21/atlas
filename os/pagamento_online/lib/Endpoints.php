<?php

declare(strict_types=1);

namespace TCloud\ParcelaExpress;

/**
 * Mapa central de rotas da API Parcela Express.
 *
 * Confirmado contra o spec OpenAPI "Parcela Express API - Parceiros" v1.0
 * publicado em sandbox.parcelaexpress.com.br.
 *
 * Placeholders no formato {nome} sao substituidos por Endpoints::build().
 * Overrides gravados em pe_rotas tem precedencia — ver Endpoints::path().
 */
final class Endpoints
{
    /*
     * ROTAS CONFIRMADAS no spec OpenAPI "Parcela Express API - Parceiros" v1.0
     * (sandbox.parcelaexpress.com.br). Nao sao mais palpite.
     */

    // ---------------------------------------------------------------
    // Autenticacao
    // ---------------------------------------------------------------

    /** POST — autenticacao. Existe tambem /v2/auth/login. */
    public const AUTH_LOGIN = '/v1/auth/login';

    /** POST — renovacao de token. */
    public const AUTH_REFRESH = '/v1/auth/refresh';

    // ---------------------------------------------------------------
    // POS / maquininha
    // ---------------------------------------------------------------

    /**
     * GET — POS do estabelecimento.
     *
     * O recurso chama-se "pos", nao "terminals": e daqui que sai a lista do
     * "Selecione o terminal".
     */
    public const POS_TERMINALS = '/v1/sellers/{seller_id}/pos';

    /** GET — dados de um POS especifico. */
    public const POS_TERMINAL_SHOW = '/v1/sellers/{seller_id}/pos/{pos_id}';

    /**
     * GET — simulacao de parcelas.
     *
     * Note a inversao: e /simulation/sellers/{id}, nao /sellers/{id}/simulation.
     * Existe tambem POST na mesma rota, que devolve as taxas detalhadas, e
     * uma v2 em /v2/simulation/sellers/{seller_id}.
     */
    public const POS_INSTALLMENT_SIMULATION = '/v1/simulation/sellers/{seller_id}';

    /** POST — simulacao com taxas e split. */
    public const POS_INSTALLMENT_SIMULATION_FEE = '/v1/simulation/sellers/{seller_id}';

    /**
     * POST — cria a venda no terminal.
     *
     * A v2 (/v2/pos/payments/{seller_id}) aceita split; use aquela quando
     * houver regras de rateio.
     */
    public const POS_SALE_CREATE = '/v1/pos/payments/{seller_id}';

    /** POST — cria a venda no terminal com split. */
    public const POS_SALE_CREATE_SPLIT = '/v2/pos/payments/{seller_id}';

    /**
     * GET — status da venda.
     *
     * Nao ha rota especifica de POS: o acompanhamento usa a venda comum.
     * Tambem existe /v2/sellers/{sellerId}/sales/{saleId}.
     */
    public const POS_SALE_STATUS = '/v1/sellers/{seller_id}/sales/{sale_id}';

    /**
     * POST — cancela a venda no terminal.
     *
     * O identificador da venda vai no CORPO, nao na URL.
     */
    public const POS_SALE_INTERRUPT = '/v1/pos/cancel_sale/{seller_id}';

    /** GET — vendas de um terminal. */
    public const POS_SALE_LIST = '/v1/sellers/{seller_id}/sales/pos/{terminal_id}';

    // ---------------------------------------------------------------
    // Boleto
    // ---------------------------------------------------------------

    /**
     * POST — emite o boleto.
     *
     * O recurso e /v1/billets/{sellerId}, e nao aninhado sob /sellers.
     * A v2 (/v2/billets/{sellerId}) emite lote.
     */
    public const BOLETO_CREATE = '/v1/billets/{seller_id}';

    /** POST — emite lote de boletos. */
    public const BOLETO_CREATE_BATCH = '/v2/billets/{seller_id}';

    /** GET — boleto com status. */
    public const BOLETO_SHOW = '/v1/billets/{billet_id}';

    /** GET — URL do PDF. Vem em chamada separada da emissao. */
    public const BOLETO_URL = '/v1/billets/{billet_id}/url';

    /** POST — baixa do titulo. E POST com "void", nao DELETE. */
    public const BOLETO_CANCEL = '/v1/billets/{seller_id}/{billet_id}/void';

    /** GET — boletos do estabelecimento. */
    public const BOLETO_LIST = '/v1/sellers/{seller_id}/billets';

    /**
     * POST — marca o boleto como pago. SOMENTE EM SANDBOX.
     *
     * A propria API descreve como "em ambiente de desenvolvimento". Permite
     * testar o ciclo completo (emitir -> pagar -> conferir -> lancar) sem
     * esperar compensacao bancaria real.
     */
    public const BOLETO_PAGAR_SANDBOX = '/v1/billets/{billet_id}/pay';

    // ---------------------------------------------------------------
    // Outros recursos disponiveis (nao usados ainda pelo modulo)
    // ---------------------------------------------------------------

    /** GET — provedor de pagamento do estabelecimento. */
    public const SELLER_PAYMENT_PROVIDER = '/v1/sellers/{seller_id}/payment-provider';

    /** GET — metodos de pagamento aceitos pelo estabelecimento. */
    public const SELLER_PAYMENT_METHODS = '/v1/sellers/{seller_id}/accepted-payment-methods';

    /** POST — pagamento online com cartao (usado pelo checkout JS). */
    public const PAYMENT_CREATE = '/v2/payments/sellers/{seller_id}';

    /** POST — link de pagamento. */
    public const PAYMENT_LINK_CREATE = '/v2/payment-links/sellers/{seller_id}';

    /** POST — webhook de notificacao. */
    public const WEBHOOK_CREATE = '/v1/sellers/{seller_id}/webhooks';

    // ---------------------------------------------------------------

    /**
     * Rotas descobertas no spec da API e confirmadas pelo administrador.
     *
     * As constantes acima ja batem com o spec publicado. Este mapa cobre o
     * caso de a API mudar: pe_lib.php carrega o que estiver gravado em
     * pe_rotas e injeta aqui no bootstrap, com precedencia sobre a
     * constante. Assim uma mudanca de rota se resolve pela tela, sem
     * alteracao de codigo em producao.
     *
     * @var array<string,string>
     */
    private static array $overrides = [];

    /**
     * @param array<string,string> $mapa chave da constante => rota
     */
    public static function aplicarOverrides(array $mapa): void
    {
        foreach ($mapa as $chave => $rota) {
            $rota = trim((string) $rota);

            if ($rota !== '') {
                self::$overrides[strtoupper($chave)] = $rota;
            }
        }
    }

    /**
     * Rota efetiva de uma operacao: o override, se houver, senao a constante.
     */
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

        return (string) \constant($constante);
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

        if (preg_match('/\{(\w+)\}/', $route, $m) === 1) {
            throw new \InvalidArgumentException(
                sprintf('Parametro "%s" nao informado para a rota "%s".', $m[1], $route)
            );
        }

        return $route;
    }
}
