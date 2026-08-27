<?php

declare(strict_types=1);

namespace TCloud\ParcelaExpress\Boleto;

/**
 * Ciclo de vida do boleto.
 *
 * Diferenca essencial em relacao ao POS: emitir um boleto NAO e receber.
 * O dinheiro so existe quando o status vira Paid. Por isso a emissao nunca
 * lanca em pagamento_os — quem lanca e a confirmacao de pagamento.
 */
enum BoletoStatus: string
{
    /** Emitido, aguardando pagamento. */
    case Pending = 'pending';

    /** Pago e confirmado pelo banco. */
    case Paid = 'paid';

    /** Vencido sem pagamento. */
    case Overdue = 'overdue';

    /** Baixado/cancelado pela serventia. */
    case Canceled = 'canceled';

    /** Falha na emissao: a API respondeu erro, o titulo nao existe. */
    case Failed = 'failed';

    /**
     * Nao houve resposta da API. O titulo PODE ter sido criado do outro
     * lado — estado indeterminado, exige conferencia no portal antes de
     * qualquer decisao.
     */
    case Unknown = 'unknown';

    public function isFinal(): bool
    {
        return $this === self::Paid || $this === self::Canceled;
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    /** Ainda pode ser pago pelo cliente. */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Overdue;
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando pagamento',
            self::Paid => 'Pago',
            self::Overdue => 'Vencido',
            self::Canceled => 'Baixado',
            self::Failed => 'Falha na emissão',
            self::Unknown => 'Emissão não confirmada',
        };
    }

    public static function tryFromApi(mixed $value): ?self
    {
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower($value)) {
            'pending', 'open', 'registered', 'aberto', 'registrado' => self::Pending,
            'paid', 'settled', 'pago', 'liquidado' => self::Paid,
            'overdue', 'expired', 'vencido' => self::Overdue,
            'canceled', 'cancelled', 'baixado', 'cancelado' => self::Canceled,
            'failed', 'error', 'falha' => self::Failed,
            default => null,
        };
    }
}

/**
 * Boleto emitido.
 */
final class Boleto
{
    /**
     * @param array<string,mixed> $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $localReference,
        public readonly ?BoletoStatus $status,
        public readonly ?int $amountCents,
        public readonly ?int $paidAmountCents,
        public readonly ?\DateTimeImmutable $vencimento,
        public readonly ?\DateTimeImmutable $pagoEm,
        public readonly ?string $linhaDigitavel,
        public readonly ?string $codigoBarras,
        public readonly ?string $pixCopiaCola,
        public readonly ?string $urlPdf,
        public readonly array $raw = [],
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromApi(array $data, ?string $localReference = null): self
    {
        $row = $data['data'] ?? $data;

        $dt = static function (mixed $v): ?\DateTimeImmutable {
            if (!is_string($v) || $v === '') {
                return null;
            }

            try {
                return new \DateTimeImmutable($v);
            } catch (\Throwable $e) {
                return null;
            }
        };

        return new self(
            id: (string) ($row['id'] ?? $row['billet_id'] ?? $row['charge_id'] ?? ''),
            localReference: $localReference ?? ($row['reference'] ?? null),
            status: BoletoStatus::tryFromApi($row['status'] ?? null),
            // A API usa 'value' na emissao; aceitamos as duas grafias.
            amountCents: isset($row['value'])
                ? (int) $row['value']
                : (isset($row['amount_cents']) ? (int) $row['amount_cents'] : null),
            paidAmountCents: isset($row['paid_value'])
                ? (int) $row['paid_value']
                : (isset($row['paid_amount_cents']) ? (int) $row['paid_amount_cents'] : null),
            vencimento: $dt($row['due_date'] ?? $row['expiration_date'] ?? null),
            pagoEm: $dt($row['paid_at'] ?? $row['payment_date'] ?? null),
            linhaDigitavel: $row['digitable_line'] ?? $row['linha_digitavel'] ?? $row['pay_number'] ?? null,
            codigoBarras: $row['barcode'] ?? $row['barcode_number'] ?? null,
            pixCopiaCola: $row['pix_code'] ?? $row['qr_code'] ?? null,
            urlPdf: $row['pdf_url'] ?? $row['url'] ?? $row['link'] ?? null,
            raw: $row,
        );
    }

    public function isPaid(): bool
    {
        return $this->status?->isPaid() === true;
    }

    /** Linha digitável na formatação bancária de 5 blocos. */
    public function linhaFormatada(): ?string
    {
        if ($this->linhaDigitavel === null) {
            return null;
        }

        $d = preg_replace('/\D/', '', $this->linhaDigitavel) ?? '';

        if (strlen($d) !== 47) {
            return $this->linhaDigitavel;
        }

        return sprintf(
            '%s.%s %s.%s %s.%s %s %s',
            substr($d, 0, 5),
            substr($d, 5, 5),
            substr($d, 10, 5),
            substr($d, 15, 6),
            substr($d, 21, 5),
            substr($d, 26, 6),
            substr($d, 32, 1),
            substr($d, 33, 14)
        );
    }
}
