<?php
/**
 * Auditoria do Fluxo de Caixa — somente administrador.
 * Lista todos os eventos registrados (inclusive tentativas bloqueadas),
 * com filtros, detalhes, exportação CSV e verificação de integridade.
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
require_once __DIR__ . '/caixa_saldo.php';
date_default_timezone_set('America/Sao_Paulo');

$conn = getDatabaseConnection();
cx_auditoria_estrutura($conn);

if (!cx_eh_admin($conn, $_SESSION['username'] ?? '')) {
    http_response_code(403);
    echo "<script>alert('A auditoria do caixa é restrita ao administrador.'); window.location.href='index.php';</script>";
    exit;
}

$h   = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$gp  = function ($k, $d = '') { return (isset($_GET[$k]) && !is_array($_GET[$k])) ? trim((string) $_GET[$k]) : $d; };
$fmt = function ($v) { return $v === null || $v === '' ? '—' : cx_moeda($v); };

// ============================ Filtros ============================
$filtroCaixa = ($gp('funcionario_caixa') !== '' || $gp('data_caixa') !== '' || $gp('caixa_id') !== '');
$temPeriodo  = isset($_GET['de']) || isset($_GET['ate']);

$de  = $gp('de',  ($temPeriodo || $filtroCaixa) ? '' : date('Y-m-d', strtotime('-6 days')));
$ate = $gp('ate', ($temPeriodo || $filtroCaixa) ? '' : date('Y-m-d'));
if ($de !== '' && !cx_data_valida($de))   { $de = ''; }
if ($ate !== '' && !cx_data_valida($ate)) { $ate = ''; }

$usuario     = $gp('usuario');
$funcCaixa   = $gp('funcionario_caixa');
$dataCaixa   = $gp('data_caixa');
if ($dataCaixa !== '' && !cx_data_valida($dataCaixa)) { $dataCaixa = ''; }
$caixaId     = (int) $gp('caixa_id', '0');
$acao        = $gp('acao');
$resultado   = $gp('resultado');
$busca       = $gp('busca');
if ($acao !== '' && !isset(CX_AUDIT_ACOES[$acao])) { $acao = ''; }
if ($resultado !== '' && !isset(CX_AUDIT_RESULTADOS[$resultado])) { $resultado = ''; }

$where = [];
$params = [];
if ($de !== '')        { $where[] = 'a.criado_em >= :de';  $params[':de']  = $de . ' 00:00:00'; }
if ($ate !== '')       { $where[] = 'a.criado_em <= :ate'; $params[':ate'] = $ate . ' 23:59:59'; }
if ($usuario !== '')   { $where[] = 'a.usuario = :usuario'; $params[':usuario'] = $usuario; }
if ($funcCaixa !== '') { $where[] = 'a.funcionario_caixa = :fc'; $params[':fc'] = $funcCaixa; }
if ($dataCaixa !== '') { $where[] = 'a.data_caixa = :dc'; $params[':dc'] = $dataCaixa; }
if ($caixaId > 0)      { $where[] = 'a.caixa_id = :cid'; $params[':cid'] = $caixaId; }
if ($acao !== '')      { $where[] = 'a.acao = :acao'; $params[':acao'] = $acao; }
if ($resultado !== '') { $where[] = 'a.resultado = :res'; $params[':res'] = $resultado; }
if ($busca !== '') {
    $where[] = '(a.descricao LIKE :b1 OR a.detalhes LIKE :b2 OR a.ip LIKE :b3)';
    $params[':b1'] = $params[':b2'] = $params[':b3'] = '%' . $busca . '%';
}
$sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$bind = function (PDOStatement $st) use ($params) {
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v);
    }
};

// ============================ Exportar CSV ============================
if ($gp('exportar') === 'csv') {
    $st = $conn->prepare('SELECT * FROM caixa_auditoria a' . $sqlWhere . ' ORDER BY a.id DESC');
    $bind($st);
    $st->execute();

    cx_auditoria_registrar($conn, [
        'acao' => 'auditoria_exportada', 'descricao' => 'Exportação CSV da auditoria do caixa.',
        'detalhes' => ['filtros' => array_diff_key($_GET, ['exportar' => 1])],
    ]);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditoria_caixa_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Data/hora', 'Usuário', 'Ação', 'Resultado', 'Funcionário do caixa', 'Data do caixa', 'Caixa ID',
                   'Referência', 'Valor', 'Saldo antes', 'Saldo depois', 'Descrição', 'Detalhes', 'IP', 'Navegador', 'Hash'], ';');
    $n = function ($v) { return $v === null ? '' : number_format((float) $v, 2, ',', ''); };
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $r['id'], date('d/m/Y H:i:s', strtotime($r['criado_em'])), $r['usuario'],
            CX_AUDIT_ACOES[$r['acao']] ?? $r['acao'], CX_AUDIT_RESULTADOS[$r['resultado']] ?? $r['resultado'],
            $r['funcionario_caixa'], $r['data_caixa'] ? cx_data_br($r['data_caixa']) : '', $r['caixa_id'],
            trim(($r['referencia_tipo'] ?? '') . ' #' . ($r['referencia_id'] ?? ''), ' #'),
            $n($r['valor']), $n($r['saldo_antes']), $n($r['saldo_depois']),
            $r['descricao'], $r['detalhes'], $r['ip'], $r['user_agent'], $r['hash'],
        ], ';');
    }
    fclose($out);
    exit;
}

// ============================ Indicadores ============================
$st = $conn->prepare("SELECT COUNT(*) AS total,
        COALESCE(SUM(a.resultado = 'sucesso'),0) AS sucesso,
        COALESCE(SUM(a.resultado = 'bloqueado'),0) AS bloqueado,
        COALESCE(SUM(a.resultado = 'erro'),0) AS erro,
        COALESCE(SUM(a.acao IN ('saida_removida','deposito_removido') AND a.resultado = 'sucesso'),0) AS remocoes,
        COALESCE(SUM(a.acao = 'saldo_inicial_lancado' AND a.resultado = 'sucesso'),0) AS saldos_admin,
        COUNT(DISTINCT a.usuario) AS usuarios
      FROM caixa_auditoria a" . $sqlWhere);
$bind($st);
$st->execute();
$kpi = $st->fetch(PDO::FETCH_ASSOC);

// ============================ Paginação ============================
$porPagina = 50;
$total     = (int) $kpi['total'];
$paginas   = max(1, (int) ceil($total / $porPagina));
$pagina    = min($paginas, max(1, (int) $gp('pagina', '1')));
$offset    = ($pagina - 1) * $porPagina;

$st = $conn->prepare('SELECT * FROM caixa_auditoria a' . $sqlWhere . ' ORDER BY a.id DESC LIMIT ' . $porPagina . ' OFFSET ' . $offset);
$bind($st);
$st->execute();
$eventos = $st->fetchAll(PDO::FETCH_ASSOC);

// Listas para os filtros
$usuariosLog = $conn->query('SELECT DISTINCT usuario FROM caixa_auditoria ORDER BY usuario')->fetchAll(PDO::FETCH_COLUMN);
$funcionarios = $conn->query("SELECT usuario, nome_completo FROM funcionarios ORDER BY nome_completo")->fetchAll(PDO::FETCH_ASSOC);
$nomes = [];
foreach ($funcionarios as $f) { $nomes[$f['usuario']] = $f['nome_completo']; }

// Integridade (sob demanda)
$integridade = null;
if ($gp('verificar') === '1') {
    $integridade = cx_auditoria_verificar($conn);
}

$urlCom = function (array $mudar) {
    $q = array_merge($_GET, $mudar);
    foreach ($q as $k => $v) { if ($v === null || $v === '') unset($q[$k]); }
    return 'auditoria.php?' . http_build_query($q);
};

$acoesRotulo = CX_AUDIT_ACOES + ['auditoria_exportada' => 'Auditoria exportada'];
$iconeAcao = [
    'caixa_aberto' => 'fa-unlock-alt', 'saida_cadastrada' => 'fa-sign-out', 'saida_removida' => 'fa-trash',
    'deposito_cadastrado' => 'fa-university', 'deposito_removido' => 'fa-trash', 'comprovante_anexado' => 'fa-paperclip',
    'caixa_fechado' => 'fa-lock', 'saldo_inicial_lancado' => 'fa-plus-square', 'auditoria_exportada' => 'fa-download',
    'fechamento_desfeito' => 'fa-undo',
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoria do Caixa</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <?php include(__DIR__ . '/../style/style_caixa.php'); ?>
    <?php include(__DIR__ . '/caixa_botoes.php'); ?>
    <style>
        .aud-panel{ background:#fff; border:1px solid #e5e9f0; border-radius:16px; padding:18px 18px 8px; box-shadow:0 1px 3px rgba(15,23,42,.05); margin:14px 0 16px; }
        .aud-label{ display:block; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; margin-bottom:4px; }
        .aud-panel .form-control{ border-radius:10px; }
        .aud-kpis{ display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:14px; }
        .aud-kpi{ background:#fff; border:1px solid #e5e9f0; border-left:4px solid #94a3b8; border-radius:12px; padding:10px 12px; }
        .aud-kpi .lb{ font-size:.68rem; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:.04em; }
        .aud-kpi .vl{ font-size:1.2rem; font-weight:800; color:#0f172a; }
        .aud-kpi.k1{ border-left-color:#6366f1; } .aud-kpi.k2{ border-left-color:#16a34a; } .aud-kpi.k3{ border-left-color:#dc2626; }
        .aud-kpi.k4{ border-left-color:#f59e0b; } .aud-kpi.k5{ border-left-color:#7c3aed; } .aud-kpi.k6{ border-left-color:#0891b2; }
        .aud-table-wrap{ background:#fff; border:1px solid #e5e9f0; border-radius:14px; overflow:auto; }
        .aud-table{ margin:0; font-size:.83rem; }
        .aud-table thead th{ position:sticky; top:0; background:#f8fafc; font-size:.68rem; text-transform:uppercase; letter-spacing:.03em; color:#475569; white-space:nowrap; border-bottom:1px solid #e2e8f0; }
        .aud-table td{ vertical-align:middle; }
        .aud-table td.num{ text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
        .aud-table tr.res-bloqueado{ background:#fff7f7; }
        .aud-table tr.res-erro{ background:#fffbeb; }
        .aud-res{ display:inline-flex; align-items:center; gap:4px; font-size:.7rem; font-weight:700; border-radius:999px; padding:2px 9px; white-space:nowrap; }
        .aud-res.sucesso{ background:#dcfce7; color:#166534; } .aud-res.bloqueado{ background:#fee2e2; color:#b91c1c; } .aud-res.erro{ background:#fef3c7; color:#92400e; }
        .aud-acao{ display:inline-flex; align-items:center; gap:6px; font-weight:600; white-space:nowrap; }
        .aud-acao .fa{ width:22px; height:22px; border-radius:6px; background:#eef2ff; color:#4338ca; display:inline-flex; align-items:center; justify-content:center; font-size:.75rem; }
        .aud-muted{ color:#64748b; font-size:.75rem; }
        .aud-integ{ border-radius:12px; padding:10px 14px; margin-bottom:14px; font-size:.9rem; }
        .aud-integ.ok{ background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; }
        .aud-integ.falha{ background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
        .aud-pre{ background:#0f172a; color:#e2e8f0; border-radius:10px; padding:12px; font-size:.78rem; white-space:pre-wrap; word-break:break-word; max-height:340px; overflow:auto; }
        .aud-dl dt{ font-size:.7rem; text-transform:uppercase; color:#64748b; letter-spacing:.03em; }
        .aud-dl dd{ margin-bottom:8px; word-break:break-all; }
        body.dark-mode .aud-panel, body.dark-mode .aud-kpi, body.dark-mode .aud-table-wrap{ background:#1e293b; border-color:#334155; }
        body.dark-mode .aud-kpi .vl, body.dark-mode .aud-table td{ color:#e2e8f0; }
        body.dark-mode .aud-table thead th{ background:#0f172a; color:#cbd5e1; }
        body.dark-mode .aud-table tr.res-bloqueado{ background:#3b1d1d; } body.dark-mode .aud-table tr.res-erro{ background:#3a2f12; }
    </style>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
        <section class="page-hero">
            <div class="title-row">
                <div class="title-icon"><i class="fa fa-shield" aria-hidden="true"></i></div>
                <div class="title-texts">
                    <h1>Auditoria do Caixa</h1>
                    <div class="subtitle muted">Tudo o que foi feito no fluxo de caixa: quem, quando, de onde, valores e saldo antes e depois — inclusive as tentativas bloqueadas.</div>
                </div>
            </div>
        </section>

        <form method="GET" class="aud-panel" autocomplete="off">
            <div class="form-row">
                <div class="form-group col-6 col-md-2">
                    <label class="aud-label" for="de">De</label>
                    <input type="date" class="form-control" id="de" name="de" value="<?php echo $h($de); ?>">
                </div>
                <div class="form-group col-6 col-md-2">
                    <label class="aud-label" for="ate">Até</label>
                    <input type="date" class="form-control" id="ate" name="ate" value="<?php echo $h($ate); ?>">
                </div>
                <div class="form-group col-12 col-md-4">
                    <label class="aud-label" for="acao">Ação</label>
                    <select class="form-control" id="acao" name="acao">
                        <option value="">Todas as ações</option>
                        <?php foreach (CX_AUDIT_ACOES as $k => $rot): ?>
                            <option value="<?php echo $h($k); ?>" <?php echo $acao === $k ? 'selected' : ''; ?>><?php echo $h($rot); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-12 col-md-4">
                    <label class="aud-label" for="resultado">Resultado</label>
                    <select class="form-control" id="resultado" name="resultado">
                        <option value="">Todos</option>
                        <?php foreach (CX_AUDIT_RESULTADOS as $k => $rot): ?>
                            <option value="<?php echo $h($k); ?>" <?php echo $resultado === $k ? 'selected' : ''; ?>><?php echo $h($rot); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-12 col-md-3">
                    <label class="aud-label" for="usuario">Quem fez</label>
                    <select class="form-control" id="usuario" name="usuario">
                        <option value="">Todos os usuários</option>
                        <?php foreach ($usuariosLog as $u): ?>
                            <option value="<?php echo $h($u); ?>" <?php echo $usuario === $u ? 'selected' : ''; ?>><?php echo $h(($nomes[$u] ?? $u) . ' (' . $u . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-12 col-md-3">
                    <label class="aud-label" for="funcionario_caixa">Caixa de</label>
                    <select class="form-control" id="funcionario_caixa" name="funcionario_caixa">
                        <option value="">Todos os funcionários</option>
                        <?php foreach ($funcionarios as $f): ?>
                            <option value="<?php echo $h($f['usuario']); ?>" <?php echo $funcCaixa === $f['usuario'] ? 'selected' : ''; ?>><?php echo $h($f['nome_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-6 col-md-2">
                    <label class="aud-label" for="data_caixa">Data do caixa</label>
                    <input type="date" class="form-control" id="data_caixa" name="data_caixa" value="<?php echo $h($dataCaixa); ?>">
                </div>
                <div class="form-group col-6 col-md-4">
                    <label class="aud-label" for="busca">Buscar</label>
                    <input type="search" class="form-control" id="busca" name="busca" placeholder="Descrição, título, arquivo, IP…" value="<?php echo $h($busca); ?>">
                </div>
            </div>
            <?php if ($caixaId > 0): ?><input type="hidden" name="caixa_id" value="<?php echo (int) $caixaId; ?>"><?php endif; ?>
            <div class="d-flex flex-wrap mb-2" style="gap:8px;">
                <button type="submit" class="cxf-btn cxf-btn-primary"><i class="fa fa-search"></i> Pesquisar</button>
                <a href="auditoria.php" class="cxf-btn cxf-btn-light"><i class="fa fa-eraser"></i> Limpar</a>
                <a href="<?php echo $h($urlCom(['verificar' => '1', 'pagina' => null])); ?>" class="cxf-btn cxf-btn-dark"><i class="fa fa-check-circle-o"></i> Verificar integridade</a>
                <a href="<?php echo $h($urlCom(['exportar' => 'csv', 'pagina' => null, 'verificar' => null])); ?>" class="cxf-btn cxf-btn-green ml-md-auto"><i class="fa fa-file-excel-o"></i> Exportar CSV</a>
                <a href="index.php?pular_abertura=1" class="cxf-btn cxf-btn-light"><i class="fa fa-arrow-left"></i> Fluxo de Caixa</a>
            </div>
        </form>

        <?php if ($integridade !== null): ?>
            <?php if ($integridade['ok']): ?>
                <div class="aud-integ ok"><i class="fa fa-check-circle"></i> <strong>Integridade confirmada.</strong>
                    Os <?php echo (int) $integridade['total']; ?> registros da auditoria estão íntegros: nenhum foi alterado ou apagado.</div>
            <?php else: ?>
                <div class="aud-integ falha"><i class="fa fa-exclamation-triangle"></i> <strong>Integridade comprometida no registro #<?php echo (int) $integridade['quebra_id']; ?>.</strong>
                    <?php echo $h($integridade['motivo']); ?> A auditoria foi modificada fora do sistema.</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="aud-kpis">
            <div class="aud-kpi k1"><div class="lb">Eventos</div><div class="vl"><?php echo (int) $kpi['total']; ?></div></div>
            <div class="aud-kpi k2"><div class="lb">Sucesso</div><div class="vl"><?php echo (int) $kpi['sucesso']; ?></div></div>
            <div class="aud-kpi k3"><div class="lb">Bloqueados</div><div class="vl"><?php echo (int) $kpi['bloqueado']; ?></div></div>
            <div class="aud-kpi k4"><div class="lb">Remoções</div><div class="vl"><?php echo (int) $kpi['remocoes']; ?></div></div>
            <div class="aud-kpi k5"><div class="lb">Saldos pelo admin</div><div class="vl"><?php echo (int) $kpi['saldos_admin']; ?></div></div>
            <div class="aud-kpi k6"><div class="lb">Usuários</div><div class="vl"><?php echo (int) $kpi['usuarios']; ?></div></div>
        </div>

        <div class="aud-table-wrap">
            <table class="table aud-table">
                <thead>
                    <tr>
                        <th>Data/hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Resultado</th>
                        <th>Caixa</th>
                        <th class="text-right">Valor</th>
                        <th class="text-right">Saldo antes → depois</th>
                        <th>Descrição</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$eventos): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4"><i class="fa fa-inbox"></i> Nenhum evento encontrado com estes filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($eventos as $e):
                    $det = $e['detalhes'] ? json_decode($e['detalhes'], true) : null;
                    $payload = [
                        'id' => (int) $e['id'], 'criado_em' => date('d/m/Y H:i:s', strtotime($e['criado_em'])),
                        'usuario' => $e['usuario'], 'nome' => $nomes[$e['usuario']] ?? '',
                        'acao' => $acoesRotulo[$e['acao']] ?? $e['acao'], 'resultado' => CX_AUDIT_RESULTADOS[$e['resultado']] ?? $e['resultado'],
                        'caixa' => trim(($e['funcionario_caixa'] ?? '') . ($e['data_caixa'] ? ' · ' . cx_data_br($e['data_caixa']) : '') . ($e['caixa_id'] ? ' (caixa #' . $e['caixa_id'] . ')' : '')),
                        'referencia' => $e['referencia_tipo'] ? ($e['referencia_tipo'] . ($e['referencia_id'] ? ' #' . $e['referencia_id'] : '')) : '',
                        'valor' => $fmt($e['valor']), 'saldo_antes' => $fmt($e['saldo_antes']), 'saldo_depois' => $fmt($e['saldo_depois']),
                        'descricao' => $e['descricao'], 'detalhes' => $det, 'ip' => $e['ip'], 'user_agent' => $e['user_agent'], 'hash' => $e['hash'],
                    ];
                ?>
                    <tr class="res-<?php echo $h($e['resultado']); ?>">
                        <td style="white-space:nowrap;"><?php echo $h(date('d/m/Y', strtotime($e['criado_em']))); ?><div class="aud-muted"><?php echo $h(date('H:i:s', strtotime($e['criado_em']))); ?></div></td>
                        <td><strong><?php echo $h($e['usuario']); ?></strong><div class="aud-muted"><?php echo $h($e['ip']); ?></div></td>
                        <td><span class="aud-acao"><i class="fa <?php echo $h($iconeAcao[$e['acao']] ?? 'fa-circle'); ?>"></i> <?php echo $h($acoesRotulo[$e['acao']] ?? $e['acao']); ?></span></td>
                        <td><span class="aud-res <?php echo $h($e['resultado']); ?>"><?php echo $h(CX_AUDIT_RESULTADOS[$e['resultado']] ?? $e['resultado']); ?></span></td>
                        <td style="white-space:nowrap;">
                            <?php if ($e['funcionario_caixa']): ?>
                                <a href="<?php echo $h('auditoria.php?' . http_build_query(['funcionario_caixa' => $e['funcionario_caixa'], 'data_caixa' => $e['data_caixa']])); ?>" title="Toda a história deste caixa">
                                    <?php echo $h($e['funcionario_caixa']); ?></a>
                                <div class="aud-muted"><?php echo $h($e['data_caixa'] ? cx_data_br($e['data_caixa']) : ''); ?></div>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="num"><?php echo $h($fmt($e['valor'])); ?></td>
                        <td class="num"><?php echo $h($fmt($e['saldo_antes'])); ?> → <?php echo $h($fmt($e['saldo_depois'])); ?></td>
                        <td style="min-width:240px;"><?php echo $h($e['descricao']); ?></td>
                        <td><button type="button" class="cxf-btn cxf-btn-sm cxf-btn-light aud-ver" title="Detalhes" data-ev="<?php echo $h(json_encode($payload, JSON_UNESCAPED_UNICODE)); ?>"><i class="fa fa-eye"></i></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($paginas > 1): ?>
        <nav class="mt-3" aria-label="Páginas">
            <ul class="pagination pagination-sm justify-content-center flex-wrap">
                <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $h($urlCom(['pagina' => $pagina - 1, 'verificar' => null])); ?>">&laquo;</a></li>
                <?php for ($p = max(1, $pagina - 3); $p <= min($paginas, $pagina + 3); $p++): ?>
                    <li class="page-item <?php echo $p === $pagina ? 'active' : ''; ?>"><a class="page-link" href="<?php echo $h($urlCom(['pagina' => $p, 'verificar' => null])); ?>"><?php echo $p; ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?php echo $pagina >= $paginas ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $h($urlCom(['pagina' => $pagina + 1, 'verificar' => null])); ?>">&raquo;</a></li>
            </ul>
            <div class="text-center aud-muted">Página <?php echo $pagina; ?> de <?php echo $paginas; ?> · <?php echo $total; ?> eventos</div>
        </nav>
        <?php endif; ?>

        <div class="text-right aud-muted mt-3 mb-2">Fluxo de Caixa · versão <?php echo $h(ATLAS_CAIXA_VERSAO); ?></div>
    </div>
</div>

<!-- Detalhes do evento -->
<div class="modal fade" id="audModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-shield"></i> Evento <span id="audId"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <dl class="row aud-dl" id="audDl"></dl>
                <h6 class="mt-2">Detalhes registrados</h6>
                <pre class="aud-pre" id="audDetalhes"></pre>
            </div>
        </div>
    </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script>
(function(){
    function esc(t){ return String(t == null ? '' : t).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    $(document).on('click', '.aud-ver', function(){
        var e = $(this).data('ev');
        if (typeof e === 'string') { try { e = JSON.parse(e); } catch (x) { return; } }
        $('#audId').text('#' + e.id);
        var campos = [
            ['Data/hora', e.criado_em], ['Usuário', e.usuario + (e.nome ? ' — ' + e.nome : '')], ['Ação', e.acao],
            ['Resultado', e.resultado], ['Caixa', e.caixa || '—'], ['Referência', e.referencia || '—'],
            ['Valor', e.valor], ['Saldo antes', e.saldo_antes], ['Saldo depois', e.saldo_depois],
            ['Descrição', e.descricao], ['IP', e.ip || '—'], ['Navegador', e.user_agent || '—'], ['Hash (integridade)', e.hash]
        ];
        $('#audDl').html(campos.map(function(c){
            return '<dt class="col-sm-3">' + esc(c[0]) + '</dt><dd class="col-sm-9">' + esc(c[1]) + '</dd>';
        }).join(''));
        $('#audDetalhes').text(e.detalhes ? JSON.stringify(e.detalhes, null, 2) : '—');
        $('#audModal').modal('show');
    });
})();
</script>
<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
