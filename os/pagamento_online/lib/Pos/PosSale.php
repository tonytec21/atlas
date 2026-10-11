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

    /**
     * Corpo de POST /v2/pos/payments/{seller_id}:
     *
     *   terminal_id, amount (reais), is_debit, is_pix, instalments,
     *   service_id (ate 10 alfanumericos), description, document,
     *   has_split_rules, split_rules
     *
     * O formato anterior (amount_cents, form_payment, pre_capture,
     * installment_plan) era palpite e a API recusava.
     *
     * @return array<string,mixed>
     */
    public function toArray(string $localReference, ?\TCloud\ParcelaExpress\Config $config = null): array
    {
        $serviceId = $this->serviceId !== null && $this->serviceId !== ''
            ? $this->serviceId
            : $localReference;
        $serviceId = substr(preg_replace('/[^a-zA-Z0-9]/', '', $serviceId) ?? '', 0, 10);

        if ($serviceId === '') {
            $serviceId = substr('POS' . time(), 0, 10);
        }

        $documento = preg_replace('/\D/', '', (string) ($this->customer['document'] ?? '')) ?? '';

        $payload = [
            'terminal_id' => $this->terminalId,
            'amount' => $config ? $config->paraApi($this->amountCents, 'pos') : $this->amountCents,
            'is_debit' => $this->paymentForm === PaymentForm::Debit,
            'instalments' => $this->paymentForm === PaymentForm::Credit ? $this->installments : 1,
            'service_id' => $serviceId,
            'is_pix' => $this->paymentForm === PaymentForm::Pix,
            'description' => mb_substr((string) ($this->description ?? ('Cobrança ' . $serviceId)), 0, 100),
            'document' => $documento,
            'has_split_rules' => $this->splitRules !== [],
            'split_rules' => $this->splitRules,
        ];

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
    public static function fromApi(array $data, ?string $localReference = null, ?\TCloud\ParcelaExpress\Config $config = null): self
    {
        $row = $data['data'] ?? $data;

        if (\is_array($row) && array_is_list($row)) {
            $row = $row[0] ?? [];
        }
        if (!\is_array($row)) {
            $row = [];
        }

        /* Valores: *_cents sempre em centavos; amount/total_amount/value na
           unidade configurada (centavos, por padrao). */
        $centavos = static function (array $r, array $chavesCents, array $chavesValor) use ($config): ?int {
            foreach ($chavesCents as $k) {
                if (isset($r[$k]) && is_numeric($r[$k])) {
                    return (int) $r[$k];
                }
            }
            foreach ($chavesValor as $k) {
                if (isset($r[$k]) && is_numeric($r[$k])) {
                    return $config ? $config->daApi($r[$k], 'pos') : (int) round((float) $r[$k]);
                }
            }
            return null;
        };

        $status = $row['status'] ?? $row['transaction_status'] ?? $row['payment_status'] ?? null;
        if (\is_array($status)) {
            $status = $status['name'] ?? $status['value'] ?? null;
        }

        $criado = null;
        if (!empty($row['created_at'])) {
            try {
                $criado = new \DateTimeImmutable((string) $row['created_at']);
            } catch (\Throwable $e) {
                $criado = null;
            }
        }

        return new self(
            id: (string) ($row['id'] ?? $row['sale_id'] ?? $row['transaction_id'] ?? $row['payment_id'] ?? ''),
            localReference: $localReference,
            preCaptureStatus: PreCaptureStatus::tryFromApi(
                $row['pre_capture_status'] ?? $row['pre_capture'] ?? null
            ),
            transactionStatus: TransactionStatus::tryFromApi($status),
            amountCents: $centavos($row, ['amount_cents'], ['amount', 'original_amount', 'value']),
            chargedAmountCents: $centavos($row, ['charged_amount_cents', 'total_amount_cents'], ['total_amount', 'charged_amount']),
            orderNumber: isset($row['order_number']) ? (string) $row['order_number'] : (isset($row['service_id']) ? (string) $row['service_id'] : null),
            terminalId: isset($row['terminal_id']) ? (string) $row['terminal_id'] : null,
            createdAt: $criado,
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
