<?php
/**
 * Atlas · Tarefas — migração da tabela `guia_de_recebimento`.
 *
 * Prepara o banco para o histórico de guias:
 *   1. Cria as colunas de controle (emitido_por, criado_em, impressoes,
 *      ultima_impressao) caso ainda não existam.
 *   2. Remove qualquer índice UNIQUE em task_id — era ele que limitava o
 *      sistema a uma guia por tarefa.
 *   3. Cria um índice comum em task_id para o histórico consultar rápido.
 *   4. Preenche os dados das guias antigas.
 *
 * Execute uma única vez pelo navegador:
 *   http://localhost/atlas/tarefas/migracao_guia_recebimento.php
 *
 * É seguro rodar de novo: cada passo verifica antes se já foi aplicado.
 */

include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

$TABELA = 'guia_de_recebimento';
$log    = array();

function passo(&$log, $status, $texto)
{
    $log[] = array('status' => $status, 'texto' => $texto);
}

/* ---------- A tabela existe? ---------- */
$res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($TABELA) . "'");
if (!$res || $res->num_rows === 0) {
    passo($log, 'erro', 'A tabela <code>' . $TABELA . '</code> não existe neste banco. Nada foi alterado.');
} else {

    /* ---------- Colunas existentes ---------- */
    $colunas = array();
    $res = $conn->query("SHOW COLUMNS FROM `$TABELA`");
    while ($linha = $res->fetch_assoc()) {
        $colunas[] = strtolower($linha['Field']);
    }

    $novas = array(
        'emitido_por'     => "ADD COLUMN `emitido_por` VARCHAR(150) NULL DEFAULT NULL COMMENT 'Usuário do sistema que emitiu a guia'",
        'criado_em'       => "ADD COLUMN `criado_em` DATETIME NULL DEFAULT NULL COMMENT 'Data/hora da emissão da guia'",
        'impressoes'      => "ADD COLUMN `impressoes` INT NOT NULL DEFAULT 0 COMMENT 'Quantidade de impressões (1ª emissão + reimpressões)'",
        'ultima_impressao' => "ADD COLUMN `ultima_impressao` DATETIME NULL DEFAULT NULL COMMENT 'Data/hora da última impressão'",
    );

    foreach ($novas as $nome => $ddl) {
        if (in_array($nome, $colunas, true)) {
            passo($log, 'ok', "Coluna <code>$nome</code> já existia.");
            continue;
        }
        if ($conn->query("ALTER TABLE `$TABELA` $ddl")) {
            passo($log, 'novo', "Coluna <code>$nome</code> criada.");
        } else {
            passo($log, 'erro', "Falha ao criar <code>$nome</code>: " . htmlspecialchars($conn->error));
        }
    }

    /* ---------- Índice UNIQUE em task_id ----------
       É a trava que impedia a segunda guia da mesma tarefa. */
    $unicos = array();
    $res = $conn->query("SHOW INDEX FROM `$TABELA`");
    while ($linha = $res->fetch_assoc()) {
        if (strtolower($linha['Column_name']) === 'task_id' && (int) $linha['Non_unique'] === 0) {
            $unicos[$linha['Key_name']] = true;
        }
    }

    if (empty($unicos)) {
        passo($log, 'ok', 'Nenhum índice UNIQUE em <code>task_id</code> — o banco já aceita várias guias por tarefa.');
    } else {
        foreach (array_keys($unicos) as $indice) {
            if (strtoupper($indice) === 'PRIMARY') {
                passo($log, 'erro', 'A chave primária está em <code>task_id</code>. Ajuste manual necessário.');
                continue;
            }
            if ($conn->query("ALTER TABLE `$TABELA` DROP INDEX `" . $indice . "`")) {
                passo($log, 'novo', "Índice UNIQUE <code>$indice</code> removido — histórico liberado.");
            } else {
                passo($log, 'erro', "Falha ao remover <code>$indice</code>: " . htmlspecialchars($conn->error));
            }
        }
    }

    /* ---------- Índice comum de consulta ---------- */
    $temIndice = false;
    $res = $conn->query("SHOW INDEX FROM `$TABELA`");
    while ($linha = $res->fetch_assoc()) {
        if (strtolower($linha['Column_name']) === 'task_id') {
            $temIndice = true;
        }
    }
    if ($temIndice) {
        passo($log, 'ok', 'Índice de consulta em <code>task_id</code> já existia.');
    } elseif ($conn->query("ALTER TABLE `$TABELA` ADD INDEX `idx_guia_task_id` (`task_id`)")) {
        passo($log, 'novo', 'Índice <code>idx_guia_task_id</code> criado.');
    } else {
        passo($log, 'erro', 'Falha ao criar o índice: ' . htmlspecialchars($conn->error));
    }

    /* ---------- Dados das guias antigas ---------- */
    $conn->query("UPDATE `$TABELA` SET `criado_em` = `data_recebimento` WHERE `criado_em` IS NULL");
    $a = $conn->affected_rows;

    $conn->query("UPDATE `$TABELA` SET `emitido_por` = `funcionario` WHERE `emitido_por` IS NULL OR `emitido_por` = ''");
    $b = $conn->affected_rows;

    $conn->query("UPDATE `$TABELA` SET `impressoes` = 1 WHERE `impressoes` = 0");
    $c = $conn->affected_rows;

    passo($log, 'ok', "Guias antigas ajustadas — data de emissão: $a · emitente: $b · contador de impressões: $c.");
}

$totalGuias = 0;
$res = $conn->query("SELECT COUNT(*) AS t FROM `$TABELA`");
if ($res && ($l = $res->fetch_assoc())) {
    $totalGuias = (int) $l['t'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas · Migração da Guia de Recebimento</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <style>
        body { background: #f4f6f9; padding: 40px 15px; font-family: "Segoe UI", Arial, sans-serif; }
        .painel { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 12px;
                  box-shadow: 0 4px 18px rgba(0,0,0,.08); overflow: hidden; }
        .painel-topo { background: #2c3e50; color: #fff; padding: 22px 28px; }
        .painel-topo h1 { font-size: 1.35rem; margin: 0; }
        .painel-topo p { margin: 6px 0 0; opacity: .8; font-size: .9rem; }
        .painel-corpo { padding: 22px 28px; }
        .item { display: flex; gap: 12px; align-items: flex-start; padding: 10px 0;
                border-bottom: 1px solid #eef1f4; font-size: .93rem; }
        .item:last-child { border-bottom: 0; }
        .item i { width: 18px; text-align: center; margin-top: 3px; }
        .ok   i { color: #7f8c8d; }
        .novo i { color: #27ae60; }
        .erro i { color: #c0392b; }
        .erro { color: #c0392b; }
        code { background: #f0f2f5; padding: 1px 5px; border-radius: 4px; color: #2c3e50; }
        .rodape { background: #f8f9fa; padding: 16px 28px; border-top: 1px solid #e9ecef;
                  display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .rodape small { color: #7f8c8d; }
    </style>
</head>
<body>
    <div class="painel">
        <div class="painel-topo">
            <h1><i class="fa fa-database"></i> Migração — Guia de Recebimento</h1>
            <p>Habilita o histórico de guias e o registro de quem emitiu cada uma.</p>
        </div>
        <div class="painel-corpo">
            <?php foreach ($log as $l): ?>
                <div class="item <?php echo $l['status']; ?>">
                    <i class="fa <?php
                        echo $l['status'] === 'novo' ? 'fa-check-circle'
                           : ($l['status'] === 'erro' ? 'fa-times-circle' : 'fa-circle-o');
                    ?>"></i>
                    <span><?php echo $l['texto']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="rodape">
            <small><?php echo $totalGuias; ?> guia(s) na base · <?php echo date('d/m/Y H:i:s'); ?></small>
            <a href="index.php" class="btn btn-sm btn-primary">
                <i class="fa fa-arrow-left"></i> Voltar às tarefas
            </a>
        </div>
    </div>
</body>
</html>
