<?php
/**
 * Recalcula itens já gravados em modelos_de_orcamento_itens
 * com base na tabela de emolumentos (incluindo FERRFIS).
 *
 * Como executar:
 * - Pelo navegador: acesse este arquivo (uma vez) e ele exibirá o resultado.
 * - Pelo terminal: php recalcular_modelos_emolumentos.php
 *
 * Regras:
 * - NÃO altera itens manuais com ato = '0'
 * - Atualiza descricao, emolumentos, ferc, fadep, femp, ferrfis e total
 * - Aplica quantidade e desconto_legal (%)
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

include(__DIR__ . '/db_connection.php');

// ======= AJUSTE AQUI SE NECESSÁRIO =======
$TABELA_ITENS       = 'modelos_de_orcamento_itens';
$TABELA_EMOLUMENTOS = 'tabela_emolumentos'; // <-- TROQUE AQUI se sua tabela tiver outro nome
// ========================================

function ensureFerrfisColumnExists(PDO $conn, string $tableName): void
{
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND COLUMN_NAME = 'ferrfis'
    ");
    $stmt->execute([':table' => $tableName]);
    $exists = (int)$stmt->fetchColumn() > 0;

    if (!$exists) {
        $conn->exec("ALTER TABLE `{$tableName}` ADD COLUMN `ferrfis` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `femp`");
    }
}

function tableExists(PDO $conn, string $tableName): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
    ");
    $stmt->execute([':table' => $tableName]);
    return (int)$stmt->fetchColumn() > 0;
}

function columnExists(PDO $conn, string $tableName, string $columnName): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND COLUMN_NAME = :col
    ");
    $stmt->execute([':table' => $tableName, ':col' => $columnName]);
    return (int)$stmt->fetchColumn() > 0;
}

function findMissingAtos(PDO $conn, string $itensTable, string $emolTable): array
{
    $sql = "
        SELECT DISTINCT mi.ato
        FROM `{$itensTable}` mi
        LEFT JOIN `{$emolTable}` te ON te.ATO = mi.ato
        WHERE mi.ato IS NOT NULL
          AND mi.ato <> '0'
          AND te.ATO IS NULL
        ORDER BY mi.ato
    ";
    return $conn->query($sql)->fetchAll(PDO::FETCH_COLUMN);
}

function recalcularItens(PDO $conn, string $itensTable, string $emolTable): int
{
    /**
     * Observação:
     * - total é recalculado preferencialmente usando te.TOTAL (que já inclui ferrfis).
     * - se você preferir "total = soma dos campos", é só trocar a linha do mi.total.
     */
    $sql = "
        UPDATE `{$itensTable}` mi
        JOIN `{$emolTable}` te
          ON te.ATO = mi.ato
        SET
          mi.descricao   = te.DESCRICAO,
          mi.emolumentos = ROUND(te.EMOLUMENTOS * IFNULL(mi.quantidade,0) * (1 - IFNULL(mi.desconto_legal,0)/100), 2),
          mi.ferc        = ROUND(te.FERC       * IFNULL(mi.quantidade,0) * (1 - IFNULL(mi.desconto_legal,0)/100), 2),
          mi.fadep       = ROUND(te.FADEP      * IFNULL(mi.quantidade,0) * (1 - IFNULL(mi.desconto_legal,0)/100), 2),
          mi.femp        = ROUND(te.FEMP       * IFNULL(mi.quantidade,0) * (1 - IFNULL(mi.desconto_legal,0)/100), 2),
          mi.ferrfis     = ROUND(te.FERRFIS    * IFNULL(mi.quantidade,0) * (1 - IFNULL(mi.desconto_legal,0)/100), 2),
          mi.total       = ROUND(te.TOTAL      * IFNULL(mi.quantidade,0) * (1 - IFNULL(mi.desconto_legal,0)/100), 2)
        WHERE mi.ato IS NOT NULL
          AND mi.ato <> '0'
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->rowCount();
}

try {
    $conn = getDatabaseConnection();

    // Valida existência das tabelas
    if (!tableExists($conn, $TABELA_ITENS)) {
        throw new Exception("Tabela '{$TABELA_ITENS}' não encontrada no banco.");
    }
    if (!tableExists($conn, $TABELA_EMOLUMENTOS)) {
        throw new Exception("Tabela '{$TABELA_EMOLUMENTOS}' não encontrada no banco. Ajuste \$TABELA_EMOLUMENTOS no topo do arquivo.");
    }

    // Valida colunas obrigatórias na tabela de emolumentos
    $requiredCols = ['ATO', 'DESCRICAO', 'EMOLUMENTOS', 'FERC', 'FADEP', 'FEMP', 'FERRFIS', 'TOTAL'];
    foreach ($requiredCols as $col) {
        if (!columnExists($conn, $TABELA_EMOLUMENTOS, $col)) {
            throw new Exception("Coluna obrigatória '{$col}' não encontrada em '{$TABELA_EMOLUMENTOS}'.");
        }
    }

    // Garante a coluna ferrfis na tabela de itens
    ensureFerrfisColumnExists($conn, $TABELA_ITENS);

    // Detecta ATOs faltando (não impede o recálculo dos que existem)
    $missingAtos = findMissingAtos($conn, $TABELA_ITENS, $TABELA_EMOLUMENTOS);

    // Recalcula tudo em transação
    $conn->beginTransaction();
    $updatedRows = recalcularItens($conn, $TABELA_ITENS, $TABELA_EMOLUMENTOS);
    $conn->commit();

    echo "OK! Recalculo finalizado.\n";
    echo "Tabela de emolumentos: {$TABELA_EMOLUMENTOS}\n";
    echo "Itens recalculados em {$TABELA_ITENS}: {$updatedRows}\n\n";

    if (!empty($missingAtos)) {
        echo "ATENÇÃO: Existem ATOS em '{$TABELA_ITENS}' que não existem em '{$TABELA_EMOLUMENTOS}'.\n";
        echo "Esses itens NÃO foram atualizados (pois não há tarifa correspondente).\n";
        echo "ATOs faltando: " . implode(', ', $missingAtos) . "\n";
    } else {
        echo "Nenhum ATO faltando na tabela de emolumentos. Tudo certo.\n";
    }

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
