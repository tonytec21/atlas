<?php

declare(strict_types=1);

namespace TCloud\ParcelaExpress\Pos;

/**
 * Persistencia local das vendas POS.
 *
 * Por que registrar do lado de ca se a Parcela Express ja guarda tudo:
 * porque existe uma janela — entre o POST sair e a resposta voltar — em que
 * a venda pode ter sido criada e voce nao saber. Sem registro local dessa
 * tentativa, ela vira dinheiro cobrado do cliente sem ato correspondente.
 */
interface PosSaleRepository
{
    /** @param array<string,mixed> $payload */
    public function markPending(string $localReference, array $payload): void;

    public function save(PosSale $sale): void;

    /** Houve resposta e ela foi de erro: a venda nao existe do outro lado. */
    public function markFailed(string $localReference, string $reason): void;

    /**
     * NAO houve resposta. Estado indeterminado — precisa de conciliacao
     * ativa contra PosSaleService::list() antes de qualquer decisao.
     */
    public function markUnknown(string $localReference, string $reason): void;

    /** @return array<int,array<string,mixed>> */
    public function findUnreconciled(\DateTimeInterface $since): array;
}

/**
 * Implementacao PDO. Ajuste o nome da tabela ao padrao do seu schema.
 */
final class PdoPosSaleRepository implements PosSaleRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly string $table = 'pe_pos_sales',
    ) {
    }

    public function markPending(string $localReference, array $payload): void
    {
        $sql = "INSERT INTO {$this->table}
                    (local_reference, request_payload, reconciliation_state, created_at)
                VALUES
                    (:ref, :payload, 'pending', NOW())
                ON DUPLICATE KEY UPDATE
                    request_payload = VALUES(request_payload),
                    updated_at = NOW()";

        $this->pdo->prepare($sql)->execute([
            ':ref' => $localReference,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function save(PosSale $sale): void
    {
        $sql = "INSERT INTO {$this->table}
                    (local_reference, remote_id, pre_capture_status, transaction_status,
                     amount_cents, charged_amount_cents, order_number, terminal_id,
                     raw_payload, reconciliation_state, created_at)
                VALUES
                    (:ref, :remote_id, :pre_status, :tx_status,
                     :amount, :charged, :order_number, :terminal_id,
                     :raw, :state, NOW())
                ON DUPLICATE KEY UPDATE
                    remote_id = VALUES(remote_id),
                    pre_capture_status = VALUES(pre_capture_status),
                    transaction_status = VALUES(transaction_status),
                    charged_amount_cents = VALUES(charged_amount_cents),
                    raw_payload = VALUES(raw_payload),
                    reconciliation_state = VALUES(reconciliation_state),
                    updated_at = NOW()";

        $this->pdo->prepare($sql)->execute([
            ':ref' => $sale->localReference ?? $sale->id,
            ':remote_id' => $sale->id,
            ':pre_status' => $sale->preCaptureStatus?->value,
            ':tx_status' => $sale->transactionStatus?->value,
            ':amount' => $sale->amountCents,
            ':charged' => $sale->chargedAmountCents,
            ':order_number' => $sale->orderNumber,
            ':terminal_id' => $sale->terminalId,
            ':raw' => json_encode($sale->raw, JSON_UNESCAPED_UNICODE),
            ':state' => $sale->isSettled() ? 'settled' : 'pending',
        ]);
    }

    public function markFailed(string $localReference, string $reason): void
    {
        $this->updateState($localReference, 'failed', $reason);
    }

    public function markUnknown(string $localReference, string $reason): void
    {
        $this->updateState($localReference, 'unknown', $reason);
    }

    public function findUnreconciled(\DateTimeInterface $since): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE reconciliation_state IN ('pending', 'unknown')
                  AND created_at >= :since
                ORDER BY created_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':since' => $since->format('Y-m-d H:i:s')]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function updateState(string $localReference, string $state, string $reason): void
    {
        $sql = "UPDATE {$this->table}
                SET reconciliation_state = :state,
                    last_error = :reason,
                    updated_at = NOW()
                WHERE local_reference = :ref";

        $this->pdo->prepare($sql)->execute([
            ':state' => $state,
            ':reason' => mb_substr($reason, 0, 500),
            ':ref' => $localReference,
        ]);
    }
}
