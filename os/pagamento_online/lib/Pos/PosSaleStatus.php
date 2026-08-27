<?php

declare(strict_types=1);

namespace TCloud\ParcelaExpress\Pos;

/**
 * A listagem "Vendas realizadas na maquina" do CartExpress mostra DUAS colunas
 * distintas: "Status" e "Pre-captura". Sao dois ciclos de vida diferentes e
 * precisam ser guardados separadamente — colapsar os dois num campo so e a
 * origem classica de erro de conciliacao.
 *
 * Os valores string abaixo sao PROVISORIOS. Quando o spec chegar, ajuste os
 * cases para bater com os enums da API; o resto do codigo usa apenas os
 * metodos de classificacao (isFinal, isSuccessful, etc.) e nao muda.
 */

/** Ciclo de vida do envio da venda ao terminal fisico. */
enum PreCaptureStatus: string
{
    /** Venda criada, aguardando o operador na maquininha. */
    case Pending = 'pending';

    /** Enviada ao terminal, aparecendo na tela do aparelho. */
    case SentToTerminal = 'sent_to_terminal';

    /** Operador concluiu a operacao no terminal. */
    case Captured = 'captured';

    /** "Interromper venda" — cancelada antes da captura. */
    case Interrupted = 'interrupted';

    /** Expirou sem captura. */
    case Expired = 'expired';

    public function isFinal(): bool
    {
        return match ($this) {
            self::Captured, self::Interrupted, self::Expired => true,
            self::Pending, self::SentToTerminal => false,
        };
    }

    public function isWaiting(): bool
    {
        return !$this->isFinal();
    }

    public static function tryFromApi(mixed $value): ?self
    {
        return \is_string($value) ? self::tryFrom(strtolower($value)) : null;
    }
}

/** Ciclo de vida financeiro da transacao. */
enum TransactionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    /** So isso autoriza dar baixa no ato/protocolo do cartorio. */
    public function isSuccessful(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Recusa do emissor e cenario NORMAL, nao erro de sistema — sobretudo em
     * 13x a 18x, que a propria tela avisa estarem sujeitas a aprovacao do banco.
     */
    public function isDeclined(): bool
    {
        return $this === self::Denied;
    }

    public static function tryFromApi(mixed $value): ?self
    {
        return \is_string($value) ? self::tryFrom(strtolower($value)) : null;
    }
}

/** Formas de pagamento oferecidas na maquininha. */
enum PaymentForm: string
{
    case Pix = 'pix';
    case Debit = 'debit';
    case Credit = 'credit';

    public function allowsInstallments(): bool
    {
        return $this === self::Credit;
    }

    /** Maximo observado na tela do CartExpress. */
    public function maxInstallments(): int
    {
        return $this === self::Credit ? 21 : 1;
    }

    /** Faixa que depende de aprovacao do banco emissor. */
    public function requiresIssuerApproval(int $installments): bool
    {
        return $this === self::Credit && $installments >= 13 && $installments <= 18;
    }
}
