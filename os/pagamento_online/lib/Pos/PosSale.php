<?php

declare(strict_types=1);

namespace TCloud\ParcelaExpress\Pos;

/**
 * Dados de entrada para gerar uma venda na maquininha.
 *
 * Espelha os campos da tela "Vendas > Maquina" do CartExpress.
 * Os nomes de chave em toArray() sao os que a API de cartao ja usa
 * (amount_cents, form_payment, installment_plan, split_rules...) —
 * confirmar no spec se o POS segue a mesma convencao.
 */
final class PosSaleRequest
{
    /**
     * @param int         $amountCents  Valor total do servico, em centavos.
     * @param string      $terminalId   Terminal que vai receber a venda.
     * @param int         $installments Numero de parcelas (1 para PIX/debito).
     * @param string|null $orderNumber  "N do pedido" — use o protocolo do ato.
     * @param array<string,mixed>|null $customer Dados do pagador (opcional na tela).
     * @param array<int,array<string,mixed>> $splitRules Divisao do valor.
     */
    public function __construct(
        public readonly int $amountCents,
        public readonly PaymentForm $paymentForm,
        public readonly string $terminalId,
        public readonly int $installments = 1,
        public readonly ?string $orderNumber = null,
        public readonly ?string $description = null,
        public readonly ?array $customer = null,
        public readonly array $splitRules = [],
        /** Amarra a venda ao ato/protocolo no Atlas. */
        public readonly ?string $protocol = null,
        public readonly ?string $serviceId = null,
    ) {
    }

    public function validate(): void
    {
        if ($this->amountCents <= 0) {
            throw new \InvalidArgumentException('O valor da venda deve ser maior que zero.');
        }

        if ($this->terminalId === '') {
            throw new \InvalidArgumentException('E obrigatorio informar o terminal.');
        }

        if ($this->installments < 1) {
            throw new \InvalidArgumentException('Numero de parcelas invalido.');
        }

        if (!$this->paymentForm->allowsInstallments() && $this->installments > 1) {
            throw new \InvalidArgumentException(
                sprintf('%s nao aceita parcelamento.', $this->paymentForm->value)
            );
        }

        if ($this->installments > $this->paymentForm->maxInstallments()) {
            throw new \InvalidArgumentException(
                sprintf('Maximo de %dx.', $this->paymentForm->maxInstallments())
            );
        }

        if ($this->splitRules !== []) {
            $total = array_sum(array_column($this->splitRules, 'amount'));

            if ($total !== $this->amountCents) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'A soma do split (%d) difere do valor da venda (%d).',
                        $total,
                        $this->amountCents
                    )
                );
            }
        }
    }

    /** @return array<string,mixed> */
    public function toArray(string $localReference): array
    {
        $payload = [
            'amount_cents' => $this->amountCents,
            'form_payment' => $this->paymentForm->value,
            'terminal_id' => $this->terminalId,
            'pre_capture' => true,
            'installment_plan' => ['number_installments' => $this->installments],
            'sale_id' => $localReference,
        ];

        if ($this->orderNumber !== null) {
            $payload['order_number'] = $this->orderNumber;
        }

        if ($this->description !== null) {
            $payload['description'] = $this->description;
        }

        if ($this->protocol !== null) {
            $payload['protocol'] = $this->protocol;
        }

        if ($this->serviceId !== null) {
            $payload['service_id'] = $this->serviceId;
        }

        if ($this->customer !== null) {
            $payload['customer'] = $this->customer;
        }

        if ($this->splitRules !== []) {
            $payload['has_split_rules'] = true;
            $payload['split_rules'] = $this->splitRules;
        }

        return $payload;
    }
}

/**
 * Estado de uma venda na maquininha.
 *
 * Guarda os dois status separadamente, como a listagem do CartExpress faz.
 */
final class PosSale
{
    /**
     * @param array<string,mixed> $raw Resposta bruta — guarde no banco.
     *                                 Em conciliacao financeira, o payload
     *                                 original vale mais que qualquer campo
     *                                 que voce achou que ia precisar.
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $localReference,
        public readonly ?PreCaptureStatus $preCaptureStatus,
        public readonly ?TransactionStatus $transactionStatus,
        public readonly ?int $amountCents,
        public readonly ?int $chargedAmountCents,
        public readonly ?string $orderNumber,
        public readonly ?string $terminalId,
        public readonly ?\DateTimeImmutable $createdAt,
        public readonly array $raw = [],
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromApi(array $data, ?string $localReference = null): self
    {
        $row = $data['data'] ?? $data;

        return new self(
            id: (string) ($row['id'] ?? $row['sale_id'] ?? $row['transaction_id'] ?? ''),
            localReference: $localReference ?? ($row['sale_id'] ?? null),
            preCaptureStatus: PreCaptureStatus::tryFromApi(
                $row['pre_capture_status'] ?? $row['pre_capture'] ?? null
            ),
            transactionStatus: TransactionStatus::tryFromApi($row['status'] ?? null),
            amountCents: isset($row['amount_cents']) ? (int) $row['amount_cents'] : null,
            chargedAmountCents: isset($row['charged_amount_cents'])
                ? (int) $row['charged_amount_cents']
                : null,
            orderNumber: $row['order_number'] ?? null,
            terminalId: $row['terminal_id'] ?? null,
            createdAt: isset($row['created_at'])
                ? new \DateTimeImmutable((string) $row['created_at'])
                : null,
            raw: $row,
        );
    }

    /** A venda chegou a um desfecho? (capturada, interrompida ou expirada) */
    public function isSettled(): bool
    {
        if ($this->transactionStatus?->isFinal() === true) {
            return true;
        }

        return $this->preCaptureStatus?->isFinal() === true
            && $this->preCaptureStatus !== PreCaptureStatus::Captured;
    }

    /** So isto autoriza dar baixa no ato. */
    public function isPaid(): bool
    {
        return $this->transactionStatus?->isSuccessful() === true;
    }

    public function isWaitingTerminal(): bool
    {
        return $this->preCaptureStatus?->isWaiting() === true;
    }
}
