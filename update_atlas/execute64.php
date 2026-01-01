<?php
// Inicia o buffer de saída
ob_start();

ini_set('display_errors', '1');
error_reporting(E_ALL);

include(__DIR__ . '/../os/db_connection.php');

// ======= AJUSTE AQUI SE NECESSÁRIO =======
$TABELA_ITENS       = 'modelos_de_orcamento_itens';
$TABELA_EMOLUMENTOS = 'tabela_emolumentos'; // <-- TROQUE AQUI se sua tabela tiver outro nome
$ARQUIVO_ATUALIZACAO_JSON = __DIR__ . '/atualizacao.json'; // <-- caminho do atualizacao.json (ajuste se precisar)
$VERSAO_EXECUTE = 64;
// ========================================

$status   = 'info';   // success | error | warning | info
$mensagem = '';

// ---------------- FUNÇÕES ----------------

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

/**
 * Atualiza o atualizacao.json SEM destruir estrutura existente.
 * - Mantém chaves que já existirem
 * - Tenta achar um array "executados/aplicados/historico/updates_log" e appendar
 * - Atualiza "ultima_execucao/ultima_versao/last_applied/last_update" se existirem; se não, cria "ultima_execucao"
 */
function atualizarAtualizacaoJson(string $jsonPath, int $versao, string $status, string $mensagem, array $extra = []): void
{
    $now = date('c');
    $registro = array_merge([
        'versao'       => $versao,
        'arquivo'      => "execute{$versao}.php",
        'status'       => $status,
        'mensagem'     => $mensagem,
        'executado_em' => $now,
    ], $extra);

    $data = [];
    if (file_exists($jsonPath)) {
        $raw = file_get_contents($jsonPath);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $data = $decoded;
    }

    // Atualiza chave de "última" se existir, senão cria uma padrão
    $keysLast = ['ultima_execucao', 'ultima_versao', 'last_applied', 'last_update', 'ultima_atualizacao'];
    $foundLastKey = false;
    foreach ($keysLast as $k) {
        if (array_key_exists($k, $data)) {
            $data[$k] = $versao;
            $foundLastKey = true;
        }
    }
    if (!$foundLastKey) {
        $data['ultima_execucao'] = $versao;
    }

    // Procura um array de log existente
    $logKeys = ['executados', 'aplicados', 'historico', 'updates_log', 'updates'];
    $logKeyFound = null;
    foreach ($logKeys as $k) {
        if (isset($data[$k]) && is_array($data[$k])) {
            $logKeyFound = $k;
            break;
        }
    }
    if (!$logKeyFound) {
        $logKeyFound = 'executados';
        if (!isset($data[$logKeyFound]) || !is_array($data[$logKeyFound])) {
            $data[$logKeyFound] = [];
        }
    }

    $data[$logKeyFound][] = $registro;

    // Garante pasta
    $dir = dirname($jsonPath);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    file_put_contents(
        $jsonPath,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

// ---------------- EXECUÇÃO ----------------

$updatedRows = 0;
$missingAtos = [];

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

    // Detecta ATOs faltando
    $missingAtos = findMissingAtos($conn, $TABELA_ITENS, $TABELA_EMOLUMENTOS);

    // Recalcula tudo em transação
    $conn->beginTransaction();
    $updatedRows = recalcularItens($conn, $TABELA_ITENS, $TABELA_EMOLUMENTOS);
    $conn->commit();

    $status = 'success';
    $mensagem = "Execute64 concluído com sucesso! Itens recalculados: {$updatedRows}.";

    // Atualiza atualizacao.json (sucesso)
    atualizarAtualizacaoJson($ARQUIVO_ATUALIZACAO_JSON, $VERSAO_EXECUTE, $status, $mensagem, [
        'tabela_itens'       => $TABELA_ITENS,
        'tabela_emolumentos' => $TABELA_EMOLUMENTOS,
        'itens_recalculados' => $updatedRows,
        'atos_faltando_qtd'  => count($missingAtos),
        'atos_faltando'      => $missingAtos,
    ]);

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

    $status = 'error';
    $mensagem = "Erro no Execute64: " . $e->getMessage();

    // Atualiza atualizacao.json (erro)
    try {
        atualizarAtualizacaoJson($ARQUIVO_ATUALIZACAO_JSON, $VERSAO_EXECUTE, $status, $mensagem);
    } catch (Throwable $ignored) {
        // não interrompe a tela por falha de log
    }

    echo "ERRO: " . $e->getMessage() . "\n";
}

// Captura e armazena a saída gerada
$output = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="style/css/toastr.min.css">
    <title>Execute64</title>
    <style>
        body{font-family: Arial, sans-serif; margin:20px;}
        .box{background:#f7f7f7;border:1px solid #ddd;border-radius:8px;padding:15px;}
        pre{white-space:pre-wrap;word-wrap:break-word;margin:0;}
    </style>
</head>
<body>

<div class="box">
    <strong>Resultado (log):</strong>
    <pre><?php echo htmlspecialchars($output, ENT_QUOTES, 'UTF-8'); ?></pre>
</div>

<script src="script/jquery-3.6.0.min.js"></script>
<script src="script/toastr.min.js"></script>

<script>
toastr.options = {
    "closeButton": true,
    "debug": false,
    "newestOnTop": true,
    "progressBar": true,
    "positionClass": "toast-bottom-left",
    "preventDuplicates": true,
    "onclick": null,
    "showDuration": "300",
    "hideDuration": "1000",
    "timeOut": "5000",
    "extendedTimeOut": "1200",
    "showEasing": "swing",
    "hideEasing": "linear",
    "showMethod": "fadeIn",
    "hideMethod": "fadeOut"
};

function verificarAtualizacoes() {
    toastr.info('Verificando atualizações...');

    setTimeout(() => {
        const status     = <?php echo json_encode($status); ?>;
        const mensagem   = <?php echo json_encode($mensagem); ?>;
        const missingQtd = <?php echo json_encode(count($missingAtos)); ?>;

        if (status === 'success') {
            toastr.success(mensagem);

            if (missingQtd > 0) {
                toastr.warning('Atenção: existem ATOS sem tarifa na tabela de emolumentos. Veja o log abaixo.');
            }

            // ✅ Recarrega automaticamente após 3 segundos
            setTimeout(() => {
                // força recarregar sem cache
                window.location.reload(true);
            }, 3000);

        } else if (status === 'error') {
            toastr.error(mensagem);

            // ❌ por padrão NÃO recarrega em erro (pra você ver o log)
            // Se quiser recarregar mesmo assim, descomente:
            /*
            setTimeout(() => {
                window.location.reload(true);
            }, 8000);
            */

        } else if (status === 'warning') {
            toastr.warning(mensagem);

            // opcional: recarregar também em warning
            /*
            setTimeout(() => {
                window.location.reload(true);
            }, 5000);
            */

        } else {
            toastr.info(mensagem);
        }
    }, 1200);
}

window.onload = verificarAtualizacoes;
</script>

</body>
</html>
