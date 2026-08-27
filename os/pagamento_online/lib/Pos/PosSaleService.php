<?php

declare(strict_types=1);

namespace TCloud\ParcelaExpress\Pos;

use TCloud\ParcelaExpress\ApiException;
use TCloud\ParcelaExpress\Config;
use TCloud\ParcelaExpress\Endpoints;
use TCloud\ParcelaExpress\HttpClient;

/**
 * Vendas na maquininha (POS), no modelo de pre-captura.
 *
 * Fluxo confirmado pela UI do CartExpress:
 *   1. define valor e forma de pagamento
 *   2. simula parcelamento (a tabela de custo efetivo vem da API)
 *   3. escolhe o terminal
 *   4. gera a venda -> ela e empurrada ao aparelho
 *   5. aguarda o operador concluir na maquininha (consultas espacadas)
 *   6. ou interrompe
 *
 * Regra de ouro: o valor cobrado NUNCA e calculado aqui. As taxas vem dos
 * "Planos de repasse" configurados para o cartorio e podem mudar sem aviso.
 * Sempre exiba e cobre o que a simulacao retornar.
 */
final class PosSaleService
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly Config $config,
        private readonly ?PosSaleRepository $repository = null,
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {
    }

    /**
     * Terminais disponiveis — popula o "Selecione o terminal".
     *
     * @return array<int,array<string,mixed>>
     */
    public function terminals(): array
    {
        $response = $this->http->get(
            Endpoints::build(Endpoints::path('POS_TERMINALS'), ['seller_id' => $this->config->sellerId])
        );

        return $response['data'] ?? $response['terminals'] ?? [];
    }

    /**
     * Tabela de parcelamento para um valor: parcelas, valor da parcela,
     * valor a cobrar e custo efetivo total.
     *
     * @return array<int,array<string,mixed>>
     */
    public function simulateInstallments(int $amountCents): array
    {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('O valor deve ser maior que zero.');
        }

        $response = $this->http->get(
            Endpoints::build(
                Endpoints::path('POS_INSTALLMENT_SIMULATION'),
                ['seller_id' => $this->config->sellerId]
            ),
            ['amount_cents' => $amountCents]
        );

        return $response['data'] ?? $response['installments'] ?? [];
    }

    /**
     * Cria a venda pre-capturada e a envia ao terminal.
     *
     * IDEMPOTENCIA: o $localReference e gerado pelo seu lado (UUID por
     * tentativa de cobranca) e viaja junto. Se a resposta se perder na rede,
     * a retentativa carrega a mesma referencia e a API tem como reconhecer
     * a venda ja criada em vez de cobrar o cliente duas vezes. Isso e
     * inegociavel em POS: o operador nao tem como saber se cobrou ou nao.
     */
    public function create(PosSaleRequest $request, string $localReference): PosSale
    {
        $request->validate();

        $payload = $request->toArray($localReference);

        $this->repository?->markPending($localReference, $payload);

        try {
            /* Com rateio, a v2 e a rota que aceita split_rules; sem rateio,
               a v1 basta e evita exigir campos que nao vamos preencher. */
            $rota = $request->splitRules !== []
                ? Endpoints::path('POS_SALE_CREATE_SPLIT')
                : Endpoints::path('POS_SALE_CREATE');

            $response = $this->http->post(
                Endpoints::build($rota, ['seller_id' => $this->config->sellerId]),
                $payload,
                idempotencyKey: $localReference,
            );
        } catch (ApiException $e) {
            // Erro de transporte deixa a venda em estado DESCONHECIDO, nao
            // falho: pode ter chegado no terminal. Marque para conciliacao
            // e nunca assuma que nao houve cobranca.
            if ($e->isTransportError()) {
                $this->repository?->markUnknown($localReference, $e->getMessage());

                $this->logger?->critical('Parcela Express: venda POS em estado desconhecido', [
                    'local_reference' => $localReference,
                    'error' => $e->getMessage(),
                ]);
            } else {
                $this->repository?->markFailed($localReference, $e->getMessage());
            }

            throw $e;
        }

        $sale = PosSale::fromApi($response, $localReference);
        $this->repository?->save($sale);

        return $sale;
    }

    /** Consulta pontual do status. */
    public function status(string $saleId): PosSale
    {
        $response = $this->http->get(
            Endpoints::build(Endpoints::path('POS_SALE_STATUS'), [
                'seller_id' => $this->config->sellerId,
                'sale_id' => $saleId,
            ])
        );

        return PosSale::fromApi($response);
    }

    /**
     * Aguarda o desfecho da venda no terminal.
     *
     * NAO ha long polling: o spec nao expoe rota de acompanhamento para POS,
     * entao usamos a consulta comum de venda
     * (GET /v1/sellers/{id}/sales/{saleId}) espacada por alguns segundos.
     * Reabrir sem intervalo seria um loop quente contra o servidor deles.
     *
     * @param int $maxWaitSeconds Prazo total.
     * @param callable|null $onTick Recebe (PosSale|null $sale, int $elapsed).
     */
    public function awaitOutcome(
        string $saleId,
        int $maxWaitSeconds = 300,
        ?callable $onTick = null,
    ): PosSale {
        $startedAt = time();
        $lastKnown = null;

        while (true) {
            $elapsed = time() - $startedAt;

            if ($elapsed >= $maxWaitSeconds) {
                throw new PosSaleTimeoutException(
                    "A venda {$saleId} nao teve desfecho em {$maxWaitSeconds}s.",
                    saleId: $saleId,
                    lastKnown: $lastKnown,
                );
            }

            $response = $this->http->get(
                Endpoints::build(Endpoints::path('POS_SALE_STATUS'), [
                    'seller_id' => $this->config->sellerId,
                    'sale_id' => $saleId,
                ]),
            );

            // Sem novidade: dorme antes do proximo ciclo. O sleep aqui e
            // obrigatorio — sem ele este ramo vira loop quente.
            if (isset($response['__not_modified']) || isset($response['__timeout'])) {
                if ($onTick !== null) {
                    $onTick($lastKnown, $elapsed);
                }

                sleep($this->config->pollIntervalSeconds);
                continue;
            }

            $sale = PosSale::fromApi($response, $lastKnown?->localReference);
            $lastKnown = $sale;

            $this->repository?->save($sale);
            if ($onTick !== null) {
                $onTick($sale, $elapsed);
            }

            if ($sale->isSettled()) {
                return $sale;
            }

            sleep($this->config->pollIntervalSeconds);
        }
    }

    /**
     * Cancela a venda no terminal.
     *
     * A rota nao leva o identificador da venda na URL — ele vai no corpo.
     * Confirmado no spec: POST /v1/pos/cancel_sale/{seller_id}.
     */
    public function interrupt(string $saleId): PosSale
    {
        $response = $this->http->post(
            Endpoints::build(Endpoints::path('POS_SALE_INTERRUPT'), [
                'seller_id' => $this->config->sellerId,
            ]),
            ['sale_id' => $saleId]
        );

        $sale = PosSale::fromApi($response, $saleId);
        $this->repository?->save($sale);

        return $sale;
    }

    /**
     * Vendas realizadas na maquina em um periodo. Use isto na rotina diaria
     * de conciliacao para fechar as vendas que ficaram em estado desconhecido.
     *
     * @return array<int,PosSale>
     */
    public function list(string $terminalId, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $response = $this->http->get(
            Endpoints::build(Endpoints::path('POS_SALE_LIST'), [
                'seller_id' => $this->config->sellerId,
                'terminal_id' => $terminalId,
            ]),
            [
                'start_date' => $from->format('Y-m-d'),
                'end_date' => $to->format('Y-m-d'),
            ]
        );

        $rows = $response['data'] ?? $response['sales'] ?? [];

        return array_map(static fn (array $row) => PosSale::fromApi($row), $rows);
    }
}

/** Venda que nao teve desfecho dentro do prazo. */
final class PosSaleTimeoutException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $saleId,
        public readonly ?PosSale $lastKnown = null,
    ) {
        parent::__construct($message);
    }
}
