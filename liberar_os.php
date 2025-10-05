<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
include(__DIR__ . '/checar_acesso_caixa.php');
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
   0) MIGRAÇÃO: tabela de log
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
  antes_liquidados     INT NOT NULL DEFAULT 0,
  antes_manuais        INT NOT NULL DEFAULT 0,
  antes_itens_afetados INT NOT NULL DEFAULT 0,
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
} catch (\Throwable $e) { }

/* =========================================================================
   1) ENDPOINTS AJAX (JSON)
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

        // Verificar se a OS existe
        $sqlCheckOS = "SELECT COUNT(*) FROM atlas.ordens_de_servico WHERE id = ?";
        $stmtCheck = $connAtlas->prepare($sqlCheckOS);
        $stmtCheck->bind_param("i", $osId);
        $stmtCheck->execute();
        $stmtCheck->bind_result($osExists);
        $stmtCheck->fetch();
        $stmtCheck->close();

        if (!$osExists) {
            echo json_encode(['ok'=>false,'message'=>'Ordem de Serviço não encontrada.']);
            if ($connAtlas && $connAtlas->ping()) $connAtlas->close();
            error_reporting($prevReporting); @ini_set('display_errors', $prevDisplay); exit;
        }

        // Contagens
        $sqlLiqHoje = "SELECT COUNT(*) FROM atlas.atos_liquidados WHERE ordem_servico_id = ? AND DATE(`data`) = CURDATE()";
        $sqlManHoje = "SELECT COUNT(*) FROM atlas.atos_manuais_liquidados WHERE ordem_servico_id = ? AND DATE(`data`) = CURDATE()";
        $sqlLiqAnt  = "SELECT COUNT(*) FROM atlas.atos_liquidados WHERE ordem_servico_id = ? AND DATE(`data`) < CURDATE()";
        $sqlManAnt  = "SELECT COUNT(*) FROM atlas.atos_manuais_liquidados WHERE ordem_servico_id = ? AND DATE(`data`) < CURDATE()";
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

        // LIBERAR
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

        $sqlUpd = "UPDATE atlas.ordens_de_servico_itens
                   SET quantidade_liquidada = NULL, status = NULL
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
   2) LISTAGEM DE LOGS
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
} catch (\Throwable $e) { }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liberação de O.S. - Atlas</title>
    <link rel="stylesheet" href="style/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/css/font-awesome.min.css">
    <link rel="stylesheet" href="style/css/style.css">
    <link rel="icon" href="style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="style/css/dataTables.bootstrap4.min.css">
    
    <style>
        /* ===================== DESIGN SYSTEM ===================== */
        :root {
            --brand-primary: #4f46e5;
            --brand-secondary: #818cf8;
            --brand-success: #10b981;
            --brand-warning: #f59e0b;
            --brand-error: #ef4444;
            --brand-info: #06b6d4;

            --text-primary: #111827;
            --text-secondary: #4b5563;
            --text-tertiary: #9ca3af;

            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --bg-tertiary: #f3f4f6;
            --bg-elevated: #ffffff;

            --border-primary: #e5e7eb;
            --border-secondary: #d1d5db;

            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-error: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --gradient-info: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);

            --shadow: 0 1px 3px rgba(16,24,40,.1), 0 1px 2px rgba(16,24,40,.06);
            --shadow-md: 0 4px 6px -1px rgba(16,24,40,.1), 0 2px 4px -1px rgba(16,24,40,.06);
            --shadow-lg: 0 10px 15px -3px rgba(16,24,40,.1), 0 4px 6px -2px rgba(16,24,40,.05);
            --shadow-xl: 0 20px 25px -5px rgba(16,24,40,.1), 0 10px 10px -5px rgba(16,24,40,.04);

            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 18px;

            --space-xs: 4px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
        }

        .dark-mode {
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --text-tertiary: #9ca3af;
            --bg-primary: #111827;
            --bg-secondary: #1f2937;
            --bg-tertiary: #374151;
            --bg-elevated: #1f2937;
            --border-primary: #374151;
            --border-secondary: #4b5563;
        }

        body {
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        /* ===================== PAGE HERO ===================== */
        .page-hero {
            background: var(--gradient-primary);
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
            margin-bottom: var(--space-xl);
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .page-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .title-row {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }

        .title-icon i {  
        font-size: 32px;  
        color: var(--text-primary);  
        position: relative;  
        z-index: 1;  
        }  

        .dark-mode .title-icon {
        color: white; 
        }

        .page-hero h1 {
            font-size: 32px;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.02em;
        }

        .page-hero .subtitle {
            font-size: 15px;
            opacity: 0.95;
            margin-top: 8px;
            line-height: 1.5;
        }

        .toolbar-actions {
            margin-left: auto;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* ===================== CARDS ===================== */
        .form-card, .list-card {
            background: var(--bg-elevated);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-lg);
            margin-bottom: var(--space-lg);
        }

        /* ===================== FORM CONTROLS ===================== */
        .form-control, select.form-control {
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .form-control:focus, select.form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
            outline: none;
        }

        label {
            font-weight: 700;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        /* ===================== BUTTONS ===================== */
        .btn {
            border-radius: var(--radius-md);
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border: none;
            padding: 12px 24px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: var(--shadow);
        }

        .btn-gradient {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-outline-secondary {
            background: transparent;
            border: 2px solid var(--border-primary);
            color: var(--text-primary);
        }

        .btn-outline-secondary:hover {
            background: var(--bg-secondary);
            border-color: var(--brand-primary);
            color: var(--brand-primary);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* ===================== SUMMARY GRID ===================== */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-md);
            margin-top: var(--space-lg);
        }

        .summary-item {
            background: var(--bg-secondary);
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-md);
            transition: all 0.3s ease;
        }

        .summary-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--brand-primary);
        }

        .summary-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .summary-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
        }

        /* ===================== BADGES ===================== */
        .badge-soft {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .badge-soft-info {
            background: rgba(6, 182, 212, 0.15);
            color: #06b6d4;
        }

        .badge-soft-success {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .badge-soft-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .badge-soft-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        /* ===================== TABLE MODERN ===================== */
        .table-modern {
            border-collapse: separate;
            border-spacing: 0;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            background: var(--bg-elevated);
        }

        .table-modern thead th {
            background: var(--bg-tertiary);
            border-bottom: 2px solid var(--border-primary);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            padding: 16px;
        }

        .table-modern tbody tr {
            transition: all 0.2s ease;
        }

        .table-modern tbody tr:hover {
            background: var(--bg-secondary);
        }

        .table-modern tbody td {
            padding: 16px;
            border-bottom: 1px solid var(--border-primary);
            color: var(--text-primary);
            vertical-align: middle;
        }

        /* ===================== LOG CARDS (MOBILE) ===================== */
        .log-card {
            background: var(--bg-elevated);
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-lg);
            padding: var(--space-md);
            box-shadow: var(--shadow-md);
            display: flex;
            gap: var(--space-md);
            align-items: flex-start;
            transition: all 0.3s ease;
        }

        .log-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--brand-primary);
        }

        .log-card .icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 24px;
            box-shadow: var(--shadow);
        }

        .log-card .content {
            flex: 1;
        }

        .log-card .line1 {
            font-weight: 800;
            font-size: 16px;
            margin-bottom: 4px;
            color: var(--text-primary);
        }

        .log-card .line2 {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }

        .log-card .chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 2px solid var(--border-primary);
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .chip.warn {
            background: rgba(245, 158, 11, 0.12);
            color: #f59e0b;
            border-color: rgba(245, 158, 11, 0.25);
        }

        .chip.good {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
            border-color: rgba(16, 185, 129, 0.25);
        }

        .chip.info {
            background: rgba(6, 182, 212, 0.12);
            color: #06b6d4;
            border-color: rgba(6, 182, 212, 0.25);
        }

        /* ===================== RESPONSIVE ===================== */
        .logs-table-wrap { display: block; }
        .logs-cards-wrap { display: none; }

        @media (max-width: 767.98px) {
            .logs-table-wrap { display: none; }
            .logs-cards-wrap { display: grid; gap: var(--space-md); }

            .page-hero h1 { font-size: 24px; }
            .title-icon { width: 52px; height: 52px; font-size: 26px; }
            .toolbar-actions { width: 100%; margin-left: 0; margin-top: var(--space-md); }
            .btn { width: 100%; }
            .summary-value { font-size: 28px; }
        }

        /* ===================== HR ===================== */
        hr {
            border: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--border-primary), transparent);
            margin: var(--space-xl) 0;
        }

        /* ===================== SECTION TITLE ===================== */
        .section-title {
            font-weight: 800;
            font-size: 20px;
            color: var(--text-primary);
            margin-bottom: var(--space-lg);
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--brand-primary);
        }

        /* ===================== ANIMATIONS ===================== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-card, .list-card, .log-card {
            animation: fadeInUp 0.4s ease;
        }

        /* ===================== LOADING STATE ===================== */
        .btn.loading {
            pointer-events: none;
            position: relative;
        }

        .btn.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid transparent;
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ===================== EMPTY STATE ===================== */
        .empty-state {
            text-align: center;
            padding: var(--space-xl);
            color: var(--text-tertiary);
        }

        .empty-state i {
            font-size: 64px;
            opacity: 0.3;
            margin-bottom: var(--space-md);
        }

        /* ===================== SCROLL TO TOP ===================== */
        #scrollTop {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            border: none;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #scrollTop:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }
    </style>
</head>
<body>
<?php include(__DIR__ . '/menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">

        <!-- ===================== PAGE HERO ===================== -->
        <section class="page-hero">
            <div class="title-row">
                <div class="title-icon">
                    <i class="mdi mdi-clipboard-arrow-left-outline"></i>
                </div>
                <div style="flex: 1;">
                    <h1>Liberação de Ordem de Serviço</h1>
                    <div class="subtitle">
                        <i class="mdi mdi-information-outline"></i>
                        <strong>Regra:</strong> Só é permitido desfazer se <strong>todos os atos</strong> da O.S. foram liquidados <strong>hoje</strong>. 
                        Qualquer registro anterior bloqueia a liberação.
                    </div>
                </div>
                <div class="toolbar-actions">
                    <a href="os/index.php" class="btn btn-outline-secondary">
                        <i class="mdi mdi-file-search-outline"></i> Pesquisar O.S
                    </a>
                </div>
            </div>
        </section>

        <!-- ===================== FORM CARD ===================== -->
        <div class="form-card">
            <form id="formLiberar" onsubmit="return false;">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="os_id">
                            <i class="mdi mdi-pound"></i> ID da Ordem de Serviço
                        </label>
                        <input type="number" 
                               min="1" 
                               class="form-control" 
                               id="os_id" 
                               name="os_id" 
                               placeholder="Ex.: 1768" 
                               required
                               autocomplete="off">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label style="opacity: 0;">Ações</label>
                        <div class="d-flex" style="gap: 10px; flex-wrap: wrap;">
                            <button id="btnResumo" type="button" class="btn btn-outline-secondary">
                                <i class="mdi mdi-magnify"></i> Ver Resumo
                            </button>
                            <button id="btnLiberar" type="button" class="btn btn-gradient" disabled>
                                <i class="mdi mdi-undo-variant"></i> Desfazer Liquidação
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ===================== RESUMO ===================== -->
                <div id="resumoWrap" style="display:none;">
                    <hr>
                    <h5 class="section-title">
                        <i class="mdi mdi-chart-box-outline"></i>
                        Resumo da Ordem de Serviço
                    </h5>
                    
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-label">
                                <i class="mdi mdi-check-circle"></i> Atos Liquidados (Hoje)
                            </div>
                            <div class="summary-value" id="sum_liq_hoje">0</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">
                                <i class="mdi mdi-pencil"></i> Atos Manuais (Hoje)
                            </div>
                            <div class="summary-value" id="sum_man_hoje">0</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">
                                <i class="mdi mdi-calendar-clock"></i> Registros Anteriores
                            </div>
                            <div class="summary-value" id="sum_anteriores">0</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">
                                <i class="mdi mdi-format-list-bulleted"></i> Itens Afetados
                            </div>
                            <div class="summary-value" id="sum_itens">0</div>
                        </div>
                    </div>

                    <div style="margin-top: var(--space-lg); display: flex; gap: 10px; flex-wrap: wrap;">
                        <span class="badge-soft badge-soft-info" id="sum_os_badge">
                            <i class="mdi mdi-file-document"></i> OS: –
                        </span>
                        <span class="badge-soft badge-soft-danger" id="sum_bloqueio" style="display:none;">
                            <i class="mdi mdi-lock"></i> Bloqueado por registros anteriores
                        </span>
                        <span class="badge-soft badge-soft-success" id="sum_ok" style="display:none;">
                            <i class="mdi mdi-check-circle"></i> Elegível para liberação
                        </span>
                    </div>
                </div>
            </form>
        </div>

        <!-- ===================== LISTA DE LOGS ===================== -->
        <div class="list-card">
            <h5 class="section-title">
                <i class="mdi mdi-history"></i>
                Histórico de Liberações
            </h5>

            <?php if (empty($logs)): ?>
            <div class="empty-state">
                <i class="mdi mdi-clipboard-text-off-outline"></i>
                <p>Nenhuma liberação registrada ainda.</p>
            </div>
            <?php else: ?>

            <!-- DESKTOP: TABELA -->
            <div class="logs-table-wrap">
                <div class="table-responsive">
                    <table id="tabelaLogs" class="table table-modern" style="width:100%;">
                        <thead>
                            <tr>
                                <th><i class="mdi mdi-calendar-clock"></i> Quando</th>
                                <th><i class="mdi mdi-file-document"></i> OS</th>
                                <th><i class="mdi mdi-account"></i> Usuário</th>
                                <th><i class="mdi mdi-ip-network"></i> IP</th>
                                <th><i class="mdi mdi-information"></i> Antes</th>
                                <th><i class="mdi mdi-delete-sweep"></i> Removido</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?=h(brDatetime($log['criado_em']))?></td>
                                <td><strong><?=h($log['ordem_servico_id'])?></strong></td>
                                <td><?=h($log['usuario_nome'] ?: ('#'.$log['usuario_id']))?></td>
                                <td><?=h($log['ip'])?></td>
                                <td>
                                    <span class="chip warn">
                                        <i class="mdi mdi-numeric"></i>
                                        <?=h($log['antes_liquidados'])?>/<?=h($log['antes_manuais'])?>/<?=h($log['antes_itens_afetados'])?>
                                    </span>
                                </td>
                                <td>
                                    <span class="chip good">
                                        <i class="mdi mdi-delete"></i>
                                        <?=h($log['deletados_liquidados'])?>/<?=h($log['deletados_manuais'])?>/<?=h($log['itens_atualizados'])?>
                                    </span>
                                </td>
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
                    <div class="icon">
                        <i class="mdi mdi-history"></i>
                    </div>
                    <div class="content">
                        <div class="line1">
                            OS <?=h($log['ordem_servico_id'])?> 
                            <span style="color: var(--text-tertiary); font-weight: 600;">•</span> 
                            <?=h(brDatetime($log['criado_em']))?>
                        </div>
                        <div class="line2">
                            <i class="mdi mdi-account"></i> <?=h($log['usuario_nome'] ?: ('#'.$log['usuario_id']))?>
                            <span style="margin: 0 6px;">•</span>
                            <i class="mdi mdi-ip-network"></i> <?=h($log['ip'])?>
                        </div>
                        <div class="chips">
                            <span class="chip warn">
                                <i class="mdi mdi-information"></i>
                                Antes: <?=h($log['antes_liquidados'])?>/<?=h($log['antes_manuais'])?>/<?=h($log['antes_itens_afetados'])?>
                            </span>
                            <span class="chip good">
                                <i class="mdi mdi-delete"></i>
                                Removido: <?=h($log['deletados_liquidados'])?>/<?=h($log['deletados_manuais'])?>/<?=h($log['itens_atualizados'])?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ===================== SCROLL TO TOP ===================== -->
<button id="scrollTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">
    <i class="mdi mdi-arrow-up"></i>
</button>

<!-- ===================== SCRIPTS ===================== -->
<script src="script/jquery-3.5.1.min.js"></script>
<script src="script/bootstrap.min.js"></script>
<script src="script/bootstrap.bundle.min.js"></script>
<script src="script/jquery.mask.min.js"></script>
<script src="script/jquery.dataTables.min.js"></script>
<script src="script/dataTables.bootstrap4.min.js"></script>
<script src="script/sweetalert2.js"></script>

<script>
(function(){
    'use strict';

    // ===================== HELPERS =====================
    function showToast(type, title, text) {
        const iconColors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#06b6d4'
        };

        Swal.fire({
            icon: type,
            title: title,
            text: text,
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true,
            confirmButtonColor: iconColors[type] || '#4f46e5'
        });
    }

    function setLoading(btn, loading) {
        if (loading) {
            btn.addClass('loading').prop('disabled', true);
            btn.data('original-text', btn.html());
            btn.html('<i class="mdi mdi-loading mdi-spin"></i> Processando...');
        } else {
            btn.removeClass('loading').prop('disabled', false);
            btn.html(btn.data('original-text'));
        }
    }

    function setResumo(res) {
        if (!res || !res.resumo) return;
        var r = res.resumo;

        $('#sum_liq_hoje').text(r.liquidados_hoje || 0);
        $('#sum_man_hoje').text(r.manuais_hoje || 0);
        var anteriores = (r.liquidados_anteriores || 0) + (r.manuais_anteriores || 0);
        $('#sum_anteriores').text(anteriores);
        $('#sum_itens').text(r.itens_com_liquidacao || 0);
        $('#sum_os_badge').html('<i class="mdi mdi-file-document"></i> OS: ' + (res.os_id || '—'));
        $('#resumoWrap').slideDown(300);

        if (r.bloqueado_por_anteriores) {
            $('#sum_bloqueio').show();
            $('#sum_ok').hide();
            $('#btnLiberar').prop('disabled', true);
        } else {
            if ((r.liquidados_hoje || 0) + (r.manuais_hoje || 0) > 0) {
                $('#sum_bloqueio').hide();
                $('#sum_ok').show();
                $('#btnLiberar').prop('disabled', false);
            } else {
                $('#sum_bloqueio').hide();
                $('#sum_ok').hide();
                $('#btnLiberar').prop('disabled', true);
            }
        }
    }

    // ===================== DATATABLE =====================
    function initOrDestroyDataTable() {
        var isDesktop = window.matchMedia('(min-width: 768px)').matches;
        var $tbl = $('#tabelaLogs');

        if (isDesktop) {
            if ($.fn.DataTable.isDataTable($tbl)) {
                $tbl.DataTable().columns.adjust().draw(false);
            } else {
                $tbl.DataTable({
                    language: { 
                        url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json' 
                    },
                    order: [[0, 'desc']],
                    pageLength: 25,
                    responsive: true
                });
                setTimeout(function(){ 
                    $tbl.DataTable().columns.adjust(); 
                }, 50);
            }
        } else {
            if ($.fn.DataTable.isDataTable($tbl)) {
                $tbl.DataTable().destroy();
            }
        }
    }

    var resizeTimer = null;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            initOrDestroyDataTable();
        }, 180);
    });

    // ===================== SCROLL TO TOP =====================
    window.addEventListener('scroll', function() {
        const scrollTop = document.getElementById('scrollTop');
        if (window.pageYOffset > 300) {
            scrollTop.style.opacity = '1';
            scrollTop.style.pointerEvents = 'auto';
        } else {
            scrollTop.style.opacity = '0';
            scrollTop.style.pointerEvents = 'none';
        }
    });

    // ===================== INIT =====================
    $(document).ready(function() {
        initOrDestroyDataTable();

        // Carregar modo dark/light
        $.ajax({
            url: 'load_mode.php',
            method: 'GET',
            success: function(mode) {
                $('body').removeClass('light-mode dark-mode').addClass(mode);
            }
        });

        // Focus no input ao carregar
        $('#os_id').focus();
    });

    // ===================== BUTTON: RESUMO =====================
    $('#btnResumo').on('click', function() {
        var os_id = parseInt($('#os_id').val(), 10);
        if (!os_id || os_id <= 0) {
            showToast('warning', 'Atenção', 'Informe um ID válido de Ordem de Serviço.');
            $('#os_id').focus();
            return;
        }

        var $btn = $(this);
        setLoading($btn, true);
        $('#btnLiberar').prop('disabled', true);

        $.ajax({
            method: 'POST',
            data: { acao: 'resumo', os_id: os_id },
            success: function(r) {
                setLoading($btn, false);
                if (!r || !r.ok) {
                    showToast('error', 'Erro', (r && r.message) ? r.message : 'Falha ao consultar o resumo.');
                    return;
                }
                setResumo(r);
                showToast('success', 'Resumo Carregado', 'Verifique as informações antes de desfazer.');
            },
            error: function(xhr) {
                setLoading($btn, false);
                showToast('error', 'Erro HTTP', 'Status: ' + xhr.status);
            }
        });
    });

    // ===================== BUTTON: LIBERAR =====================
    $('#btnLiberar').on('click', function() {
        var os_id = parseInt($('#os_id').val(), 10);
        if (!os_id || os_id <= 0) {
            showToast('warning', 'Atenção', 'Informe um ID válido de Ordem de Serviço.');
            $('#os_id').focus();
            return;
        }

        Swal.fire({
            title: 'Confirmar Liberação?',
            html: '<p style="font-size: 15px; line-height: 1.6;">Esta ação irá <strong>apagar somente os atos liquidados HOJE</strong> da OS <strong>' + os_id + '</strong> e <strong>limpar o status</strong> dos itens correspondentes.</p><p style="margin-top: 12px; color: #ef4444; font-weight: 600;"><i class="mdi mdi-alert"></i> Esta ação não pode ser desfeita!</p>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="mdi mdi-check"></i> Sim, desfazer',
            cancelButtonText: '<i class="mdi mdi-close"></i> Cancelar',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            reverseButtons: true
        }).then(function(result) {
            if (!result.isConfirmed) return;

            var $btn = $('#btnLiberar');
            setLoading($btn, true);

            $.ajax({
                method: 'POST',
                data: { acao: 'liberar', os_id: os_id },
                success: function(r) {
                    setLoading($btn, false);
                    if (!r || !r.ok) {
                        showToast('error', 'Não foi possível desfazer', (r && r.message) ? r.message : 'Verifique as restrições.');
                        return;
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Liberação Concluída!',
                        html: '<p>A liquidação de hoje foi desfeita com sucesso.</p>' +
                              '<p style="margin-top: 10px;"><strong>Atos removidos:</strong> ' + (r.resultado.deletados_liquidados || 0) + '</p>' +
                              '<p><strong>Atos manuais removidos:</strong> ' + (r.resultado.deletados_manuais || 0) + '</p>' +
                              '<p><strong>Itens atualizados:</strong> ' + (r.resultado.itens_atualizados || 0) + '</p>',
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'OK'
                    }).then(function() {
                        window.location.reload();
                    });
                },
                error: function(xhr) {
                    setLoading($btn, false);
                    showToast('error', 'Erro HTTP', 'Status: ' + xhr.status);
                }
            });
        });
    });

    // ===================== ENTER KEY =====================
    $('#os_id').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#btnResumo').click();
        }
    });

})();
</script>

<br><br><br>
<?php include(__DIR__ . '/rodape.php'); ?>
</body>
</html>