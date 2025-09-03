<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/checar_acesso_caixa.php'); // mesmo nível de acesso do modelo
date_default_timezone_set('America/Sao_Paulo');

/* =========================================================================
   Helpers
   ========================================================================= */
function getClientIp(): string {
    $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            return htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
        }
    }
    return '0.0.0.0';
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function brDatetime(?string $s): string {
    if (!$s) return '';
    try {
        $dt = new DateTime($s, new DateTimeZone('America/Sao_Paulo'));
        return $dt->format('d/m/Y H:i');
    } catch (Throwable $e) { return $s; }
}

/* =========================================================================
   0) MIGRAÇÃO: tabela de log (executa silenciosamente)
   ========================================================================= */
try {
    $connAtlasMig = new mysqli("localhost","root","","atlas");
    if (!$connAtlasMig->connect_error) {
        $connAtlasMig->set_charset("utf8mb4");
        $sqlLog = <<<SQL
CREATE TABLE IF NOT EXISTS os_liberacao_log (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  ordem_servico_id     INT NOT NULL,
  usuario_id           INT NULL,
  usuario_nome         VARCHAR(255) NULL,
  ip                   VARCHAR(45)  NULL,
  user_agent           VARCHAR(255) NULL,
  antes_liquidados     INT NOT NULL DEFAULT 0,   -- contagem considerada para a liberação (hoje)
  antes_manuais        INT NOT NULL DEFAULT 0,   -- contagem considerada para a liberação (hoje)
  antes_itens_afetados INT NOT NULL DEFAULT 0,   -- itens com qtd/status preenchidos
  deletados_liquidados INT NOT NULL DEFAULT 0,
  deletados_manuais    INT NOT NULL DEFAULT 0,
  itens_atualizados    INT NOT NULL DEFAULT 0,
  criado_em            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_os (ordem_servico_id),
  INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;
        $connAtlasMig->query($sqlLog);
    }
    if ($connAtlasMig && $connAtlasMig->ping()) $connAtlasMig->close();
} catch (\Throwable $e) {
    // silencioso
}

/* =========================================================================
   1) ENDPOINTS AJAX (JSON) – resumo e liberação com regra "somente hoje"
   ========================================================================= */
if (isset($_POST['acao']) && in_array($_POST['acao'], ['resumo','liberar'], true)) {
    if (function_exists('ob_get_level')) { while (ob_get_level()) { @ob_end_clean(); } }
    $prevDisplay = ini_get('display_errors');
    $prevReporting = error_reporting();
    @ini_set('display_errors','0');
    @ini_set('log_errors','1');
    @ini_set('error_log', __DIR__ . '/logs/liberar_os.log');
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

    header('Content-Type: application/json; charset=utf-8');

    $acao = $_POST['acao'];
    $osId = isset($_POST['os_id']) ? (int)$_POST['os_id'] : 0;

    $out = [
        'ok' => false,
        'message' => '',
        'os_id' => $osId,
        'resumo' => [
            'liquidados_hoje'        => 0,
            'liquidados_anteriores'  => 0,
            'manuais_hoje'           => 0,
            'manuais_anteriores'     => 0,
            'itens_com_liquidacao'   => 0,
            'bloqueado_por_anteriores' => false
        ]
    ];

    if ($osId <= 0) {
        echo json_encode(['ok'=>false,'message'=>'Informe um ID válido de Ordem de Serviço.']);
        error_reporting($prevReporting); @ini_set('display_errors', $prevDisplay); exit;
    }

    try {
        $connAtlas = new mysqli("localhost","root","","atlas");
        if ($connAtlas->connect_error) {
            echo json_encode(['ok'=>false,'message'=>'Falha na conexão com banco atlas: '.$connAtlas->connect_error]);
            error_reporting($prevReporting); @ini_set('display_errors', $prevDisplay); exit;
        }
        $connAtlas->set_charset("utf8mb4");

        // Contagens "hoje"
        $sqlLiqHoje = "SELECT COUNT(*) FROM atlas.atos_liquidados WHERE ordem_servico_id = ? AND DATE(`data`) = CURDATE()";
        $sqlManHoje = "SELECT COUNT(*) FROM atlas.atos_manuais_liquidados WHERE ordem_servico_id = ? AND DATE(`data`) = CURDATE()";
        // Contagens "anteriores"
        $sqlLiqAnt  = "SELECT COUNT(*) FROM atlas.atos_liquidados WHERE ordem_servico_id = ? AND DATE(`data`) < CURDATE()";
        $sqlManAnt  = "SELECT COUNT(*) FROM atlas.atos_manuais_liquidados WHERE ordem_servico_id = ? AND DATE(`data`) < CURDATE()";
        // Itens com liquidação/status (para exibir no resumo)
        $sqlItens   = "SELECT COUNT(*) FROM atlas.ordens_de_servico_itens WHERE ordem_servico_id = ? AND (quantidade_liquidada IS NOT NULL OR status IS NOT NULL)";

        $stmt = $connAtlas->prepare($sqlLiqHoje); $stmt->bind_param("i",$osId); $stmt->execute(); $stmt->bind_result($liqHoje); $stmt->fetch(); $stmt->close();
        $stmt = $connAtlas->prepare($sqlManHoje); $stmt->bind_param("i",$osId); $stmt->execute(); $stmt->bind_result($manHoje); $stmt->fetch(); $stmt->close();
        $stmt = $connAtlas->prepare($sqlLiqAnt);  $stmt->bind_param("i",$osId); $stmt->execute(); $stmt->bind_result($liqAnt);  $stmt->fetch(); $stmt->close();
        $stmt = $connAtlas->prepare($sqlManAnt);  $stmt->bind_param("i",$osId); $stmt->execute(); $stmt->bind_result($manAnt);  $stmt->fetch(); $stmt->close();
        $stmt = $connAtlas->prepare($sqlItens);   $stmt->bind_param("i",$osId); $stmt->execute(); $stmt->bind_result($itens);   $stmt->fetch(); $stmt->close();

        $bloqueado = ($liqAnt + $manAnt) > 0;

        $out['resumo'] = [
            'liquidados_hoje'        => (int)$liqHoje,
            'liquidados_anteriores'  => (int)$liqAnt,
            'manuais_hoje'           => (int)$manHoje,
            'manuais_anteriores'     => (int)$manAnt,
            'itens_com_liquidacao'   => (int)$itens,
            'bloqueado_por_anteriores' => $bloqueado
        ];

        if ($acao === 'resumo') {
            $out['ok'] = true;
            echo json_encode($out);
            if ($connAtlas && $connAtlas->ping()) $connAtlas->close();
            error_reporting($prevReporting); @ini_set('display_errors', $prevDisplay); exit;
        }

        // LIBERAR: regra "somente hoje"
        if ($bloqueado) {
            echo json_encode(['ok'=>false,'message'=>'Bloqueado: existem atos liquidados em data anterior a hoje. Não é permitido desfazer.','resumo'=>$out['resumo']]);
            if ($connAtlas && $connAtlas->ping()) $connAtlas->close();
            error_reporting($prevReporting); @ini_set('display_errors', $prevDisplay); exit;
        }

        $totalHoje = (int)$liqHoje + (int)$manHoje;
        if ($totalHoje === 0) {
            echo json_encode(['ok'=>false,'message'=>'Nada para desfazer hoje nesta O.S.','resumo'=>$out['resumo']]);
            if ($connAtlas && $connAtlas->ping()) $connAtlas->close();
            error_reporting($prevReporting); @ini_set('display_errors', $prevDisplay); exit;
        }

        // Transação
        $connAtlas->begin_transaction();

        // Apaga SOMENTE registros de HOJE
        $del1 = 0; $del2 = 0;

        $sqlDel1 = "DELETE FROM atlas.atos_liquidados WHERE ordem_servico_id = ? AND DATE(`data`) = CURDATE()";
        $stmtDel1 = $connAtlas->prepare($sqlDel1);
        $stmtDel1->bind_param("i", $osId);
        $okDel1 = $stmtDel1->execute();
        $del1 = $stmtDel1->affected_rows;
        $stmtDel1->close();

        $sqlDel2 = "DELETE FROM atlas.atos_manuais_liquidados WHERE ordem_servico_id = ? AND DATE(`data`) = CURDATE()";
        $stmtDel2 = $connAtlas->prepare($sqlDel2);
        $stmtDel2->bind_param("i", $osId);
        $okDel2 = $stmtDel2->execute();
        $del2 = $stmtDel2->affected_rows;
        $stmtDel2->close();

        // Limpa itens afetados (apenas os que tinham valores)
        $sqlUpd = "UPDATE atlas.ordens_de_servico_itens
                   SET quantidade_liquidada = NULL,
                       status               = NULL
                   WHERE ordem_servico_id = ?
                     AND (quantidade_liquidada IS NOT NULL OR status IS NOT NULL)";
        $stmtUpd = $connAtlas->prepare($sqlUpd);
        $stmtUpd->bind_param("i", $osId);
        $okUpd = $stmtUpd->execute();
        $updAffected = $stmtUpd->affected_rows;
        $stmtUpd->close();

        if (!$okDel1 || !$okDel2 || !$okUpd) {
            $connAtlas->rollback();
            echo json_encode(['ok'=>false,'message'=>'Falha ao desfazer liquidação (DELETE/UPDATE).']);
            if ($connAtlas && $connAtlas->ping()) $connAtlas->close();
            error_reporting($prevReporting); @ini_set('display_errors', $prevDisplay); exit;
        }

        $connAtlas->commit();

        // Log
        $usuarioId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $usuarioNome = $_SESSION['NAME_USER'] ?? ($_SESSION['username'] ?? null);
        $ip          = getClientIp();
        $ua          = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);

        $sqlInsLog = "INSERT INTO os_liberacao_log
            (ordem_servico_id, usuario_id, usuario_nome, ip, user_agent,
             antes_liquidados, antes_manuais, antes_itens_afetados,
             deletados_liquidados, deletados_manuais, itens_atualizados)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtLog = $connAtlas->prepare($sqlInsLog);
        $stmtLog->bind_param(
            "iisssiiiiii",
            $osId, $usuarioId, $usuarioNome, $ip, $ua,
            $liqHoje, $manHoje, $itens,
            $del1, $del2, $updAffected
        );
        $stmtLog->execute();
        $stmtLog->close();

        echo json_encode([
            'ok' => true,
            'message' => 'Liquidação de hoje desfeita com sucesso.',
            'resultado' => [
                'deletados_liquidados' => (int)$del1,
                'deletados_manuais'    => (int)$del2,
                'itens_atualizados'    => (int)$updAffected
            ]
        ]);
        if ($connAtlas && $connAtlas->ping()) $connAtlas->close();
    } catch (\Throwable $e) {
        echo json_encode(['ok'=>false,'message'=>'Erro: '.$e->getMessage()]);
    }

    error_reporting($prevReporting); @ini_set('display_errors', $prevDisplay);
    exit;
}

/* =========================================================================
   2) LISTAGEM DE LOGS PARA A TELA
   ========================================================================= */
$logs = [];
try {
    $connAtlasList = new mysqli("localhost","root","","atlas");
    if (!$connAtlasList->connect_error) {
        $connAtlasList->set_charset("utf8mb4");
        $rs = $connAtlasList->query("SELECT id, ordem_servico_id, usuario_id, usuario_nome, ip, criado_em,
                                            antes_liquidados, antes_manuais, antes_itens_afetados,
                                            deletados_liquidados, deletados_manuais, itens_atualizados
                                     FROM os_liberacao_log
                                     ORDER BY criado_em DESC
                                     LIMIT 200");
        if ($rs) {
            while ($row = $rs->fetch_assoc()) { $logs[] = $row; }
            $rs->free();
        }
    }
    if ($connAtlasList && $connAtlasList->ping()) $connAtlasList->close();
} catch (\Throwable $e) { /* silencioso */ }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Liberação de O.S. (Desfazer Liquidações)</title>
    <link rel="stylesheet" href="style/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/css/font-awesome.min.css">
    <link rel="stylesheet" href="style/css/style.css">
    <link rel="icon" href="style/img/favicon.png" type="image/png">

    <!-- MDI via CDN (mesmo do modelo) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">

    <link rel="stylesheet" href="style/css/dataTables.bootstrap4.min.css">
    <style>
        /* =======================================================================
           TOKENS / THEME
        ======================================================================= */
        :root{
            --brand:#4F46E5;
            --brand-2:#6366F1;
            --brand-start:#0ea5e9;
            --brand-end:#7c3aed;
            --bg:#f6f7fb;
            --card:#ffffff;
            --muted:#6b7280;
            --text:#1f2937;
            --border:#e5e7eb;
            --shadow:0 10px 25px rgba(16,24,40,.06);
            --soft-shadow:0 6px 18px rgba(16,24,40,.08);
        }
        body.light-mode{ background:var(--bg); color:var(--text); }
        body.dark-mode{
            --bg:#0f141a; --card:#1a2129; --text:#e5e7eb; --muted:#9aa6b2; --border:#2a3440;
            --shadow:0 10px 25px rgba(0,0,0,.35); --soft-shadow:0 6px 18px rgba(0,0,0,.4);
            background:var(--bg); color:var(--text);
        }
        .muted{ color:var(--muted)!important; }

        /* HERO */
        .page-hero{
            background:linear-gradient(180deg, rgba(79,70,229,.10), rgba(79,70,229,0));
            border-radius:18px; padding:18px 18px 10px; margin:20px 0 12px; box-shadow:var(--soft-shadow);
        }
        .page-hero .title-row{ display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .title-icon{
            width:44px;height:44px;border-radius:12px;background:#EEF2FF;color:#3730A3;
            display:flex;align-items:center;justify-content:center;font-size:20px;
        }
        body.dark-mode .title-icon{ background:#262f3b;color:#c7d2fe; }
        .page-hero h1{ font-weight:800; margin:0; }

        /* CARDS */
        .form-card, .list-card{
            background:var(--card); border:1px solid var(--border);
            border-radius:16px; padding:16px; box-shadow:var(--shadow);
        }
        .form-card label{
            font-size:.78rem; text-transform:uppercase; letter-spacing:.04em;
            color:var(--muted); margin-bottom:6px; font-weight:700;
        }
        .form-card .form-control, .form-card select{
            background:transparent; color:var(--text);
            border:1px solid var(--border); border-radius:10px;
        }
        .form-card .form-control:focus, .form-card select:focus{
            border-color:#a5b4fc; box-shadow:0 0 0 .2rem rgba(99,102,241,.15);
        }
        .toolbar-actions{ display:flex; gap:.5rem; flex-wrap:wrap; }
        .btn-gradient{
            background:linear-gradient(135deg, var(--brand), var(--brand-2));
            color:#fff; border:none;
        }
        .btn-gradient:hover{ filter:brightness(.96); color:#fff; }
        .btn-outline-secondary{ border-radius:10px; }
        .badge-soft{ display:inline-block; padding:.35rem .6rem; border-radius:999px; font-size:.75rem; }
        .badge-soft-info{ background:rgba(59,130,246,.15); color:#3b82f6; }
        .badge-soft-success{ background:rgba(16,185,129,.15); color:#10b981; }
        .badge-soft-danger{ background:rgba(239,68,68,.15); color:#ef4444; }

        .summary-grid{ display:grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap:12px; }
        @media (max-width: 1200px){ .summary-grid{ grid-template-columns: repeat(2,minmax(0,1fr)); } }
        @media (max-width: 576px){ .summary-grid{ grid-template-columns: 1fr; } }
        .summary-item{ background:var(--card); border:1px solid var(--border); border-radius:12px; padding:12px; }

        table.dataTable thead th{ white-space:nowrap; }

        /* ===== Logs: Tabela no desktop, Cards no mobile ===== */
        .logs-table-wrap { display:block; }
        .logs-cards-wrap { display:none; }
        @media (max-width: 767.98px){
            .logs-table-wrap { display:none; }
            .logs-cards-wrap { display:grid; gap:12px; }
        }
        .log-card{
            background:var(--card); border:1px solid var(--border);
            border-radius:14px; padding:14px; box-shadow:var(--shadow);
            display:flex; gap:12px; align-items:flex-start;
        }
        .log-card .icon{
            width:40px; height:40px; border-radius:10px;
            background:#EEF2FF; color:#3730A3; display:flex; align-items:center; justify-content:center;
            flex-shrink:0;
        }
        body.dark-mode .log-card .icon{ background:#262f3b;color:#c7d2fe; }
        .log-card .line1{ font-weight:800; margin-bottom:2px; }
        .log-card .chips{ display:flex; gap:6px; flex-wrap:wrap; }
        .chip{ border:1px solid var(--border); border-radius:999px; padding:.15rem .5rem; font-size:.75rem; }
        .chip.good{ background:rgba(16,185,129,.12); color:#10b981; border-color: rgba(16,185,129,.25); }
        .chip.warn{ background:rgba(59,130,246,.12); color:#3b82f6; border-color: rgba(59,130,246,.25); }
        .chip.neutral{ background:rgba(148,163,184,.12); color:#64748b; border-color: rgba(148,163,184,.25); }
    </style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">

        <!-- HERO / TÍTULO -->
        <section class="page-hero">
        <div class="title-row">
            <div class="title-icon"><i class="mdi mdi-clipboard-arrow-left-outline"></i></div>
            <div>
            <h1>Liberação de O.S. (Desfazer Liquidações)</h1>
            <div class="subtitle muted">
                Só é permitido desfazer se todos os atos da O.S. foram liquidados <strong>hoje</strong>.
                Qualquer registro anterior a hoje bloqueia a liberação.
            </div>
            </div>

            <!-- AÇÃO: Pesquisar O.S (alinha à direita; “quebra” para a linha de baixo no mobile) -->
            <div class="ml-auto toolbar-actions">
            <a href="os/index.php" class="btn btn-outline-secondary" aria-label="Pesquisar O.S">
                <i class="mdi mdi-file-search-outline"></i> Pesquisar O.S
            </a>
            </div>
        </div>
        </section>


        <!-- FORM CARD -->
        <div class="form-card mb-3">
            <form id="formLiberar" onsubmit="return false;">
                <div class="row align-items-end">
                    <div class="form-group col-md-4">
                        <label for="os_id">ID da Ordem de Serviço</label>
                        <input type="number" min="1" class="form-control" id="os_id" name="os_id" placeholder="Ex.: 1768" required>
                    </div>
                    <div class="form-group col-md-8">
                        <div class="d-flex toolbar-actions" style="margin-top: 6px;">
                            <button id="btnResumo" type="button" class="btn btn-outline-secondary">
                                <i class="mdi mdi-magnify"></i> Ver resumo
                            </button>
                            <button id="btnLiberar" type="button" class="btn btn-gradient" disabled>
                                <i class="mdi mdi-undo-variant"></i> Desfazer liquidação (somente hoje)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Resumo -->
                <div id="resumoWrap" style="display:none;">
                    <hr>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="muted text-uppercase" style="font-weight:700; letter-spacing:.04em;">Atos liquidados (hoje)</div>
                            <div class="h4 mb-0" id="sum_liq_hoje">0</div>
                        </div>
                        <div class="summary-item">
                            <div class="muted text-uppercase" style="font-weight:700; letter-spacing:.04em;">Atos manuais (hoje)</div>
                            <div class="h4 mb-0" id="sum_man_hoje">0</div>
                        </div>
                        <div class="summary-item">
                            <div class="muted text-uppercase" style="font-weight:700; letter-spacing:.04em;">Registros anteriores a hoje</div>
                            <div class="h4 mb-0" id="sum_anteriores">0</div>
                        </div>
                        <div class="summary-item">
                            <div class="muted text-uppercase" style="font-weight:700; letter-spacing:.04em;">Itens com liquidação / status</div>
                            <div class="h4 mb-0" id="sum_itens">0</div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <span class="badge-soft badge-soft-info" id="sum_os_badge">OS: –</span>
                        <span class="badge-soft badge-soft-danger" id="sum_bloqueio" style="display:none;">Bloqueado por registros anteriores</span>
                        <span class="badge-soft badge-soft-success" id="sum_ok" style="display:none;">Elegível (somente registros de hoje)</span>
                    </div>
                </div>
            </form>
        </div>

        <!-- LISTA DE LOGS -->
        <div class="list-card">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <h5 class="mb-2" style="font-weight:800;">Últimas liberações registradas</h5>
            </div>

            <!-- DESKTOP: TABELA -->
            <div class="logs-table-wrap">
                <div class="table-responsive">
                    <table id="tabelaLogs" class="table table-striped table-bordered" style="zoom:100%; width:100%;">
                        <thead>
                            <tr>
                                <th>Quando</th>
                                <th>OS</th>
                                <th>Usuário</th>
                                <th>IP</th>
                                <th>Antes (atos/manuais/itens)</th>
                                <th>Ação (atos/manuais/itens)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?=h(brDatetime($log['criado_em']))?></td>
                                <td><?=h($log['ordem_servico_id'])?></td>
                                <td><?=h($log['usuario_nome'] ?: ('#'.$log['usuario_id']))?></td>
                                <td><?=h($log['ip'])?></td>
                                <td><?=h($log['antes_liquidados'])?> / <?=h($log['antes_manuais'])?> / <?=h($log['antes_itens_afetados'])?></td>
                                <td><?=h($log['deletados_liquidados'])?> / <?=h($log['deletados_manuais'])?> / <?=h($log['itens_atualizados'])?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MOBILE: CARDS -->
            <div class="logs-cards-wrap">
                <?php foreach ($logs as $log): ?>
                <div class="log-card">
                    <div class="icon"><i class="mdi mdi-history"></i></div>
                    <div class="content">
                        <div class="line1">OS <?=h($log['ordem_servico_id'])?> • <span class="muted"><?=h(brDatetime($log['criado_em']))?></span></div>
                        <div class="muted" style="margin-bottom:6px;">Por: <strong><?=h($log['usuario_nome'] ?: ('#'.$log['usuario_id']))?></strong> • IP <?=h($log['ip'])?></div>
                        <div class="chips" style="margin-bottom:6px;">
                            <span class="chip warn">Antes: <?=h($log['antes_liquidados'])?>/<?=h($log['antes_manuais'])?>/<?=h($log['antes_itens_afetados'])?></span>
                            <span class="chip good">Ação: <?=h($log['deletados_liquidados'])?>/<?=h($log['deletados_manuais'])?>/<?=h($log['itens_atualizados'])?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>

    </div>
</div>

<script src="script/jquery-3.5.1.min.js"></script>
<script src="script/bootstrap.min.js"></script>
<script src="script/bootstrap.bundle.min.js"></script>
<script src="script/jquery.mask.min.js"></script>
<script src="script/jquery.dataTables.min.js"></script>
<script src="script/dataTables.bootstrap4.min.js"></script>
<script src="script/sweetalert2.js"></script>
<script>
(function(){
    function showToast(type, title, text){
        Swal.fire({ icon:type, title:title, text:text, showConfirmButton:false, timer:5000 });
    }
    function setResumo(res){
        if(!res || !res.resumo) return;
        var r = res.resumo;

        $('#sum_liq_hoje').text(r.liquidados_hoje || 0);
        $('#sum_man_hoje').text(r.manuais_hoje || 0);
        var anteriores = (r.liquidados_anteriores||0) + (r.manuais_anteriores||0);
        $('#sum_anteriores').text(anteriores);
        $('#sum_itens').text(r.itens_com_liquidacao || 0);
        $('#sum_os_badge').text('OS: ' + (res.os_id || '—'));
        $('#resumoWrap').show();

        if (r.bloqueado_por_anteriores){
            $('#sum_bloqueio').show(); $('#sum_ok').hide();
            $('#btnLiberar').prop('disabled', true);
        } else {
            if ((r.liquidados_hoje || 0) + (r.manuais_hoje || 0) > 0){
                $('#sum_bloqueio').hide(); $('#sum_ok').show();
                $('#btnLiberar').prop('disabled', false);
            } else {
                $('#sum_bloqueio').hide(); $('#sum_ok').hide();
                $('#btnLiberar').prop('disabled', true);
            }
        }
    }

    // DataTable somente quando a tabela estiver visível (desktop)
    function initOrDestroyDataTable(){
        var isDesktop = window.matchMedia('(min-width: 768px)').matches;
        var $tbl = $('#tabelaLogs');

        if (isDesktop){
            if ($.fn.DataTable.isDataTable($tbl)){
                $tbl.DataTable().columns.adjust().draw(false);
            } else {
                $tbl.DataTable({
                    language:{ url:'https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json' },
                    order:[[0,'desc']]
                });
                // pequeno ajuste após criar
                setTimeout(function(){ $tbl.DataTable().columns.adjust(); }, 50);
            }
        } else {
            if ($.fn.DataTable.isDataTable($tbl)){
                // NÃO remover do DOM! Apenas destruir a instância
                $tbl.DataTable().destroy();
            }
        }
    }

    // debounce do resize
    var resizeTimer = null;
    $(window).on('resize', function(){
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function(){
            initOrDestroyDataTable();
        }, 180);
    });

    // inicializa conforme o viewport atual
    initOrDestroyDataTable();

    // Botão RESUMO
    $('#btnResumo').on('click', function(){
        var os_id = parseInt($('#os_id').val(),10);
        if (!os_id || os_id <= 0){
            showToast('warning','Atenção','Informe um ID válido de Ordem de Serviço.');
            return;
        }
        $('#btnLiberar').prop('disabled', true);
        $.ajax({
            method:'POST',
            data:{acao:'resumo', os_id: os_id},
            success:function(r){
                if (!r || !r.ok){
                    showToast('error','Erro', (r && r.message) ? r.message : 'Falha ao consultar o resumo.');
                    return;
                }
                setResumo(r);
                showToast('success','Resumo carregado','Verifique as restrições antes de desfazer.');
            },
            error:function(xhr){
                showToast('error','Erro HTTP','Status: '+xhr.status);
            }
        });
    });

    // Botão LIBERAR
    $('#btnLiberar').on('click', function(){
        var os_id = parseInt($('#os_id').val(),10);
        if (!os_id || os_id <= 0){
            showToast('warning','Atenção','Informe um ID válido de Ordem de Serviço.');
            return;
        }

        Swal.fire({
            title:'Confirmar liberação (somente hoje)?',
            html:'Esta ação irá <b>apagar somente os atos liquidados HOJE</b> da OS <b>'+os_id+
                 '</b> e <b>limpar</b> status dos itens correspondentes.<br><br>Deseja continuar?',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'Sim, desfazer',
            cancelButtonText:'Cancelar'
        }).then(function(result){
            if (!result.isConfirmed) return;

            $('#btnLiberar').prop('disabled', true);
            $.ajax({
                method:'POST',
                data:{acao:'liberar', os_id: os_id},
                success:function(r){
                    if (!r || !r.ok){
                        showToast('error','Não foi possível desfazer', (r && r.message) ? r.message : 'Verifique as restrições.');
                        $('#btnLiberar').prop('disabled', false);
                        return;
                    }
                    showToast('success','Concluído', 'Liquidação de hoje desfeita com sucesso.');
                    setTimeout(function(){ window.location.reload(); }, 900);
                },
                error:function(xhr){
                    showToast('error','Erro HTTP','Status: '+xhr.status);
                    $('#btnLiberar').prop('disabled', false);
                }
            });
        });
    });

    // Enter no input aciona RESUMO
    $('#os_id').on('keypress', function(e){
        if (e.which === 13){ $('#btnResumo').click(); }
    });
})();
</script>

<br><br><br>
<?php include(__DIR__ . '/rodape.php'); ?>
</body>
</html>
