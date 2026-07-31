<?php
/**
 * =====================================================================
 * diagnostico_fuso.php — Conferência do fuso horário do módulo O.S.
 * ---------------------------------------------------------------------
 * ATLAS-TEMPO-BUILD: 2026-07-31-fuso-unico
 *
 * Página SOMENTE LEITURA. Ela:
 *   1. mostra o fuso do PHP e o do MySQL (sessão, global e do sistema);
 *   2. mede exatamente quantas horas o servidor de banco estava deslocado,
 *      comparando NOW() no fuso padrão do servidor com o relógio do PHP;
 *   3. gera o SQL pronto para corrigir os registros ANTIGOS, já com o
 *      recorte de data para não tocar no que for gravado a partir de agora.
 *
 * Nada é alterado no banco por esta página. Copie o SQL e rode no
 * phpMyAdmin depois de conferir o preview.
 * =====================================================================
 */
include(__DIR__ . '/session_check.php');
checkSession();
require_once __DIR__ . '/db_connection.php';

$pdo = getDatabaseConnection();

$esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

/* ------------------------------------------------------------------ *
 * 1. Leitura dos fusos
 * ------------------------------------------------------------------ */
$phpTz      = date_default_timezone_get();
$phpAgora   = new DateTime('now');
$sessaoTz   = (string) $pdo->query("SELECT @@session.time_zone")->fetchColumn();
$globalTz   = (string) $pdo->query("SELECT @@global.time_zone")->fetchColumn();
$sistemaTz  = (string) $pdo->query("SELECT @@system_time_zone")->fetchColumn();
$mysqlAgora = (string) $pdo->query("SELECT NOW()")->fetchColumn();

/* Diferença que AINDA existe (deve ser 0 depois da correção). */
$difAtualSeg = strtotime($mysqlAgora) - $phpAgora->getTimestamp();

/* ------------------------------------------------------------------ *
 * 2. Mede o deslocamento HISTÓRICO
 *    (NOW() como era antes: no fuso padrão do servidor)
 * ------------------------------------------------------------------ */
$driftMin  = null;
$mysqlAntes = null;
try {
    $pdo->exec("SET time_zone = " . $pdo->quote($globalTz ?: 'SYSTEM'));
    $mysqlAntes = (string) $pdo->query("SELECT NOW()")->fetchColumn();
    $driftMin = (int) round((time() - strtotime($mysqlAntes)) / 60);
} catch (Throwable $e) {
    $mysqlAntes = null;
} finally {
    /* Restaura o fuso correto da sessão. */
    atlas_alinhar_fuso($pdo);
}

/* ------------------------------------------------------------------ *
 * 3. Tabelas gravadas por NOW()/DEFAULT CURRENT_TIMESTAMP
 *    (as gravadas por date() do PHP já estavam certas e ficam de fora)
 * ------------------------------------------------------------------ */
$alvos = [
    'ordens_de_servico'        => ['data_criacao', 'cancelado_em', 'data_entrega'],
    'atos_liquidados'          => ['data_liquidacao'],
    'atos_manuais_liquidados'  => ['data_liquidacao'],
    'pagamento_os'             => ['data_pagamento'],
    'anexos_os'                => ['data'],
    'logs_ordens_de_servico'   => ['data_edicao'],
    'os_entregas'              => ['data_entrega'],
];

$existentes = [];
foreach ($alvos as $tabela => $colunas) {
    foreach ($colunas as $coluna) {
        $st = $pdo->prepare(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c"
        );
        $st->execute([':t' => $tabela, ':c' => $coluna]);
        $tipo = $st->fetchColumn();
        if (!$tipo) {
            continue;
        }

        $linha = ['tabela' => $tabela, 'coluna' => $coluna, 'tipo' => $tipo,
                  'qtd' => null, 'min' => null, 'max' => null];
        try {
            $r = $pdo->query(
                "SELECT COUNT(*) q, MIN(`$coluna`) mi, MAX(`$coluna`) ma
                   FROM `$tabela` WHERE `$coluna` IS NOT NULL"
            )->fetch(PDO::FETCH_ASSOC);
            $linha['qtd'] = (int) $r['q'];
            $linha['min'] = $r['mi'];
            $linha['max'] = $r['ma'];
        } catch (Throwable $e) {
            /* tabela inacessível: apenas não exibe os totais */
        }
        $existentes[] = $linha;
    }
}

/* ------------------------------------------------------------------ *
 * 4. Monta o SQL de correção
 * ------------------------------------------------------------------ */
$corte = date('Y-m-d H:i:s');
$sql   = '';

if ($driftMin !== null && $driftMin !== 0) {
    $sinal = $driftMin > 0 ? '+' : '-';
    $abs   = abs($driftMin);

    $sql .= "-- =====================================================================\n";
    $sql .= "-- Correcao do deslocamento de fuso nos registros ANTIGOS do modulo O.S.\n";
    $sql .= "-- Gerado em {$corte} — deslocamento medido: {$sinal}{$abs} minuto(s)\n";
    $sql .= "-- (o MySQL gravava " . ($driftMin > 0 ? 'ATRASADO' : 'ADIANTADO') . " em relacao ao horario real)\n";
    $sql .= "--\n";
    $sql .= "-- ANTES DE RODAR: faca backup do banco (mysqldump ou export do phpMyAdmin).\n";
    $sql .= "-- Rode UMA UNICA VEZ. Rodar duas vezes desloca os dados de novo.\n";
    $sql .= "-- O recorte '<= {$corte}' protege tudo que for gravado a partir de agora.\n";
    $sql .= "-- =====================================================================\n\n";

    foreach ($existentes as $c) {
        if (!$c['qtd']) {
            continue;
        }
        $sql .= "-- {$c['tabela']}.{$c['coluna']} ({$c['qtd']} registro(s))\n";
        $sql .= "UPDATE `{$c['tabela']}`\n";
        $sql .= "   SET `{$c['coluna']}` = DATE_ADD(`{$c['coluna']}`, INTERVAL {$driftMin} MINUTE)\n";
        $sql .= " WHERE `{$c['coluna']}` IS NOT NULL\n";
        $sql .= "   AND `{$c['coluna']}` <= '{$corte}';\n\n";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de fuso horário — O.S.</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <style>
        .card-fuso{border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:16px;height:100%}
        .card-fuso .rot{font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.03em}
        .card-fuso .val{font-size:1.05rem;font-weight:700;color:#0f172a;word-break:break-all}
        pre.sqlbox{background:#0f172a;color:#e2e8f0;padding:16px;border-radius:10px;font-size:.78rem;
                   max-height:420px;overflow:auto;white-space:pre}
        .ok-badge{background:#dcfce7;color:#166534;padding:4px 12px;border-radius:999px;font-weight:700;font-size:.8rem}
        .no-badge{background:#fee2e2;color:#991b1b;padding:4px 12px;border-radius:999px;font-weight:700;font-size:.8rem}
    </style>
</head>
<body>
<?php @include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">
    <h3 class="m-0">Diagnóstico de fuso horário</h3>
    <p class="text-muted" style="font-size:.88rem">
      Módulo O.S. — página somente leitura. Nenhuma alteração é feita no banco por aqui.
    </p>
    <hr>

    <div class="row mb-4">
      <div class="col-md-4 mb-3">
        <div class="card-fuso">
          <div class="rot">Relógio do PHP</div>
          <div class="val"><?= $esc($phpAgora->format('d/m/Y H:i:s')) ?></div>
          <div class="rot mt-2">Fuso: <?= $esc($phpTz) ?></div>
        </div>
      </div>
      <div class="col-md-4 mb-3">
        <div class="card-fuso">
          <div class="rot">NOW() do MySQL (sessão corrigida)</div>
          <div class="val"><?= $esc(date('d/m/Y H:i:s', strtotime($mysqlAgora))) ?></div>
          <div class="rot mt-2">time_zone da sessão: <?= $esc($sessaoTz) ?></div>
        </div>
      </div>
      <div class="col-md-4 mb-3">
        <div class="card-fuso">
          <div class="rot">Situação</div>
          <div class="val mt-1">
            <?php if (abs($difAtualSeg) <= 60): ?>
              <span class="ok-badge"><i class="fa fa-check"></i> PHP e MySQL alinhados</span>
            <?php else: ?>
              <span class="no-badge"><i class="fa fa-exclamation-triangle"></i>
                <?= $esc(round($difAtualSeg / 60)) ?> min de diferença</span>
            <?php endif; ?>
          </div>
          <div class="rot mt-2">
            Global: <?= $esc($globalTz) ?> &nbsp;|&nbsp; Sistema: <?= $esc($sistemaTz) ?>
          </div>
        </div>
      </div>
    </div>

    <div class="alert <?= ($driftMin === null || $driftMin === 0) ? 'alert-success' : 'alert-warning' ?>">
      <?php if ($driftMin === null): ?>
        Não foi possível medir o fuso padrão do servidor de banco.
      <?php elseif ($driftMin === 0): ?>
        <b>O servidor de banco já estava no horário certo.</b> Os registros antigos não precisam de correção.
      <?php else: ?>
        <b>Deslocamento histórico medido: <?= $esc($driftMin) ?> minuto(s)</b>
        (<?= $esc(round($driftMin / 60, 2)) ?> h).
        No fuso padrão do servidor (<?= $esc($globalTz) ?>) o MySQL responde
        <b><?= $esc($mysqlAntes ? date('d/m/Y H:i:s', strtotime($mysqlAntes)) : '—') ?></b>,
        enquanto o horário real é <b><?= $esc($phpAgora->format('d/m/Y H:i:s')) ?></b>.
        Tudo que foi gravado com <code>NOW()</code> ou <code>DEFAULT CURRENT_TIMESTAMP</code>
        antes desta atualização está deslocado nessa mesma medida.
      <?php endif; ?>
    </div>

    <h5>Colunas gravadas pelo banco</h5>
    <p class="text-muted" style="font-size:.85rem">
      Colunas alimentadas por <code>NOW()</code> / <code>DEFAULT CURRENT_TIMESTAMP</code>.
      As que são gravadas pelo PHP (repasses, devoluções, guias, comprovantes de pagamento)
      já saíam com a hora certa e por isso não entram na correção.
    </p>
    <div class="table-responsive mb-4">
      <table class="table table-sm table-bordered table-striped">
        <thead><tr><th>Tabela</th><th>Coluna</th><th>Tipo</th><th class="text-right">Registros</th><th>Mais antigo</th><th>Mais recente</th></tr></thead>
        <tbody>
        <?php foreach ($existentes as $c): ?>
          <tr>
            <td><?= $esc($c['tabela']) ?></td>
            <td><?= $esc($c['coluna']) ?></td>
            <td><small class="text-muted"><?= $esc($c['tipo']) ?></small></td>
            <td class="text-right"><?= number_format((int) $c['qtd'], 0, ',', '.') ?></td>
            <td><small><?= $esc($c['min'] ?: '—') ?></small></td>
            <td><small><?= $esc($c['max'] ?: '—') ?></small></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$existentes): ?>
          <tr><td colspan="6" class="text-center text-muted">Nenhuma das colunas esperadas foi encontrada.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($sql !== ''): ?>
      <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
        <h5 class="m-0">SQL de correção dos registros antigos</h5>
        <button class="btn btn-outline-primary btn-sm" onclick="copiarSql()"><i class="fa fa-copy"></i> Copiar</button>
      </div>
      <div class="alert alert-danger" style="font-size:.85rem">
        <b>Faça backup do banco antes.</b> Rode este bloco <b>uma única vez</b> —
        executar duas vezes desloca os dados de novo, agora para o outro lado.
      </div>
      <pre class="sqlbox" id="sqlbox"><?= $esc($sql) ?></pre>
    <?php endif; ?>

    <p class="text-muted mt-4" style="font-size:.82rem">
      Se o deslocamento persistir depois desta atualização, verifique o fuso do próprio
      servidor MySQL (variável <code>default-time-zone</code> no <code>my.ini</code>) e o
      relógio do Windows. A sessão já é forçada para <code>-03:00</code> em toda conexão do módulo.
    </p>
  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script>
function copiarSql() {
    var t = document.getElementById('sqlbox');
    if (!t) return;
    var r = document.createRange();
    r.selectNodeContents(t);
    var s = window.getSelection();
    s.removeAllRanges();
    s.addRange(r);
    try { document.execCommand('copy'); alert('SQL copiado.'); } catch (e) { alert('Selecione e copie manualmente.'); }
    s.removeAllRanges();
}
</script>
</body>
</html>
