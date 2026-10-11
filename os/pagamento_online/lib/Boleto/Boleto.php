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

        /* A v2 devolve maiusculas; APPROVED/PAID/SETTLED/
           CONFIRMED como pago. */
        return match (strtolower(trim($value))) {
            'pending', 'open', 'registered', 'created', 'waiting', 'waiting_payment', 'issued',
            'aberto', 'registrado', 'pendente', 'emitido' => self::Pending,
            'paid', 'settled', 'approved', 'confirmed', 'succeeded', 'captured',
            'pago', 'liquidado', 'aprovado', 'compensado' => self::Paid,
            'overdue', 'expired', 'vencido', 'expirado' => self::Overdue,
            'canceled', 'cancelled', 'voided', 'refunded', 'baixado', 'cancelado' => self::Canceled,
            'failed', 'error', 'denied', 'falha' => self::Failed,
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
        /** Venda criada junto com o boleto: e por ela que se consulta o pagamento. */
        public readonly ?string $saleId = null,
        /** Link de pagamento (with_payment_link). */
        public readonly ?string $urlPagamento = null,
        /** Imagem do QR Code para pagar com PIX/cartao. */
        public readonly ?string $qrcodeUrl = null,
    ) {
    }

    /**
     * Le a resposta da emissao (v2: LISTA com um item por boleto) ou da
     * consulta (venda ou titulo).
     *
     * Campos da resposta: id, sale_id, expires_at, barcode, url,
     * billet_data.payment_link_url, billet_data.qrcode_url.
     *
     * @param array<string,mixed> $data
     */
    public static function fromApi(array $data, ?string $localReference = null, ?\TCloud\ParcelaExpress\Config $config = null): self
    {
        $row = $data['data'] ?? $data;

        if (is_array($row) && array_is_list($row)) {
            $row = $row[0] ?? [];
        }
        if (!is_array($row)) {
            $row = [];
        }

        $bd = is_array($row['billet_data'] ?? null) ? $row['billet_data'] : [];
        $bl = is_array($row['billet'] ?? null) ? $row['billet'] : [];
        $pega = static function (array $chaves) use ($row, $bd, $bl) {
            foreach ([$row, $bd, $bl] as $fonte) {
                foreach ($chaves as $k) {
                    if (isset($fonte[$k]) && $fonte[$k] !== '' && !is_array($fonte[$k])) {
                        return $fonte[$k];
                    }
                }
            }
            return null;
        };

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

        $valor = static function (array $chavesCents, array $chavesValor) use ($pega, $config): ?int {
            $c = $pega($chavesCents);
            if ($c !== null && is_numeric($c)) {
                return (int) $c;
            }
            $v = $pega($chavesValor);
            if ($v === null || !is_numeric($v)) {
                return null;
            }
            return $config ? $config->daApi($v, 'boleto') : (int) round((float) $v);
        };

        $status = $row['status'] ?? $bd['status'] ?? $bl['status'] ?? null;
        if (is_array($status)) {
            $status = $status['name'] ?? $status['value'] ?? null;
        }

        $linha = $pega(['digitable_line', 'linha_digitavel', 'digitableLine', 'pay_number', 'typeable_line']);
        $barras = $pega(['barcode', 'barcode_number', 'bar_code', 'codigo_barras']);
        /* Em alguns retornos o "barcode" ja e a linha digitavel (47 digitos). */
        if ($linha === null && $barras !== null && strlen(preg_replace('/\D/', '', (string) $barras) ?? '') === 47) {
            $linha = $barras;
        }

        $urlPdf = $pega(['pdf_url', 'url', 'billet_url', 'link', 'pdf']);
        $urlPag = $pega(['payment_link_url', 'payment_link', 'checkout_url']);
        $saleId = $row['sale_id'] ?? $bd['sale_id'] ?? null;

        return new self(
            id: (string) ($row['id'] ?? $row['billet_id'] ?? $bl['id'] ?? $row['charge_id'] ?? ''),
            localReference: $localReference ?? ($row['reference'] ?? null),
            status: BoletoStatus::tryFromApi($status),
            amountCents: $valor(['amount_cents', 'value_cents'], ['value', 'amount', 'original_amount']),
            paidAmountCents: $valor(['paid_amount_cents'], ['paid_value', 'paid_amount', 'amount_paid']),
            vencimento: $dt($pega(['due_date', 'expiration_date', 'expires_at', 'delivery_date'])),
            pagoEm: $dt($pega(['paid_at', 'payment_date', 'settled_at'])),
            linhaDigitavel: $linha !== null ? (string) $linha : null,
            codigoBarras: $barras !== null ? (string) $barras : null,
            pixCopiaCola: ($p = $pega(['pix_code', 'pix_copy_paste', 'emv', 'qr_code'])) !== null ? (string) $p : null,
            urlPdf: $urlPdf !== null ? (string) $urlPdf : null,
            raw: $row,
            saleId: $saleId !== null && $saleId !== '' ? (string) $saleId : null,
            urlPagamento: $urlPag !== null ? (string) $urlPag : null,
            qrcodeUrl: ($q = $pega(['qrcode_url', 'qr_code_url'])) !== null ? (string) $q : null,
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
