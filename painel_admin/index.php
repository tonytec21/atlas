<?php
/**
 * Atlas — Painel do Administrador
 * Auditoria de todos os módulos, acessos, faturamento, desempenho da equipe
 * e da serventia, integridade das trilhas e análise automática com IA.
 */
include(__DIR__ . '/../session_check.php');
checkSession();
require_once __DIR__ . '/lib/ia.php';

if (!pa_eh_admin()) {
    http_response_code(403);
    ?><!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acesso restrito</title>
    <style>body{font-family:system-ui,sans-serif;background:#f4f6fb;display:grid;place-items:center;min-height:100vh;margin:0;color:#0f172a}
    .c{background:#fff;padding:34px 30px;border-radius:18px;box-shadow:0 10px 40px rgba(15,23,42,.12);max-width:420px;text-align:center}
    a{display:inline-block;margin-top:16px;background:#4f46e5;color:#fff;padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:600}</style></head>
    <body><div class="c"><h2>Acesso restrito</h2><p>O Painel do Administrador é exclusivo do administrador da serventia. A tentativa de acesso foi registrada na auditoria.</p><a href="../index.php">Voltar ao Atlas</a></div></body></html><?php
    exit;
}

// Manutenção da auditoria (selagem, gatilhos de tabelas novas, prazo de guarda); no máximo a cada 15 min
aud_manutencao();

if (empty($_SESSION['painel_csrf'])) {
    $_SESSION['painel_csrf'] = bin2hex(random_bytes(24));
}
$u = pa_usuario();
$mode = 'light-mode';
try {
    $m = pa_val('SELECT modo FROM modo_usuario WHERE usuario = ?', [$u['usuario']]);
    if ($m) {
        $mode = $m;
    }
} catch (Throwable $e) {
}
$serv = pa_one('SELECT razao_social, cidade FROM cadastro_serventia LIMIT 1') ?: [];
$v = function ($f) { return @filemtime(__DIR__ . '/' . $f) ?: time(); };
$primeiroNome = explode(' ', trim($u['nome']))[0] ?: $u['usuario'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas — Painel do Administrador</title>
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/all.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="stylesheet" href="assets/painel.css?v=<?php echo $v('assets/painel.css'); ?>">
    <script src="../script/jquery-3.6.0.min.js"></script>
</head>
<body class="<?php echo htmlspecialchars($mode); ?> pa-body">
<?php include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content pa-main">
<div class="pa" id="pa">

    <section class="pa-hero">
        <div class="pa-hero-top">
            <div>
                <h1><span class="pa-hero-ico"><i class="fas fa-shield-alt"></i></span> Painel do Administrador</h1>
                <div class="pa-sub">Olá, <b><?php echo htmlspecialchars($primeiroNome); ?></b> · <?php echo htmlspecialchars(pa_utf8($serv['razao_social'] ?? 'Serventia')); ?><?php echo !empty($serv['cidade']) ? ' · ' . htmlspecialchars(pa_utf8($serv['cidade'])) : ''; ?></div>
            </div>
            <div class="pa-hero-acoes">
                <button class="pa-hbtn" id="paAtualizar" title="Atualizar"><i class="fas fa-sync-alt"></i><span class="pa-txt-l">Atualizar</span></button>
                <button class="pa-hbtn" id="paImprimir" title="Imprimir"><i class="fas fa-print"></i><span class="pa-txt-l">Imprimir</span></button>
                <button class="pa-hbtn" id="paConfig" title="Configurações"><i class="fas fa-cog"></i><span class="pa-txt-l">Configurações</span></button>
                <button class="pa-hbtn pa-ia" id="paIaBtn"><i class="fas fa-magic"></i> Análise com IA</button>
            </div>
        </div>
        <div class="pa-periodo">
            <div class="pa-chips" id="paChips">
                <button data-p="hoje">Hoje</button>
                <button data-p="7d">7 dias</button>
                <button data-p="30d">30 dias</button>
                <button data-p="mes" class="ativo">Este mês</button>
                <button data-p="mes_ant">Mês anterior</button>
                <button data-p="ano">Este ano</button>
                <button data-p="custom"><i class="far fa-calendar-alt"></i> Período</button>
            </div>
            <div class="pa-datas" id="paDatas">
                <input type="date" id="paIni"> <span style="color:rgba(255,255,255,.7)">até</span> <input type="date" id="paFim">
                <button class="pa-hbtn" id="paAplicar" style="padding:6px 12px">Aplicar</button>
            </div>
            <div class="pa-periodo-info" id="paPeriodoInfo"></div>
        </div>
    </section>

    <div class="pa-abas-wrap">
        <nav class="pa-abas" id="paAbas">
            <button data-aba="geral" class="ativo"><i class="fas fa-th-large"></i> Visão geral</button>
            <button data-aba="faturamento"><i class="fas fa-coins"></i> Faturamento</button>
            <button data-aba="equipe"><i class="fas fa-users"></i> Equipe</button>
            <button data-aba="serventia"><i class="fas fa-landmark"></i> Serventia</button>
            <button data-aba="auditoria"><i class="fas fa-fingerprint"></i> Auditoria</button>
            <button data-aba="acessos"><i class="fas fa-door-open"></i> Acessos</button>
            <button data-aba="integridade"><i class="fas fa-lock"></i> Integridade</button>
            <button data-aba="ia"><i class="fas fa-magic"></i> Análise IA</button>
        </nav>
    </div>

    <div id="paConteudo"></div>

    <div class="pa-rodape">Painel do Administrador · v<?php echo PAINEL_ADMIN_VERSAO; ?> · Auditoria central v<?php echo ATLAS_AUD_LIB; ?></div>
</div>
</div>

<div class="pa-gaveta-fundo" id="paGavetaFundo"></div>
<aside class="pa-gaveta" id="paGaveta" aria-hidden="true">
    <div class="pa-gaveta-h"><div id="paGavetaTit"></div><button class="pa-gaveta-x" id="paGavetaX" aria-label="Fechar"><i class="fas fa-times"></i></button></div>
    <div class="pa-gaveta-c" id="paGavetaC"></div>
</aside>

<div class="pa-modal-fundo" id="paModal">
    <div class="pa-modal">
        <div class="pa-modal-h"><i class="fas fa-cog" style="color:var(--pa-primary)"></i><h2>Configurações do painel</h2><button class="pa-gaveta-x" id="paModalX" aria-label="Fechar"><i class="fas fa-times"></i></button></div>
        <div class="pa-modal-c">
            <div class="pa-subabas" id="paCfgAbas">
                <button data-c="ia" class="ativo"><i class="fas fa-magic"></i> Análise com IA</button>
                <button data-c="aud"><i class="fas fa-fingerprint"></i> Auditoria</button>
            </div>
            <div id="paCfgC"></div>
        </div>
    </div>
</div>
<div class="pa-toast" id="paToast"></div>

<script>
window.PA = {
    csrf: <?php echo json_encode($_SESSION['painel_csrf']); ?>,
    usuario: <?php echo json_encode($u['usuario']); ?>,
    hoje: <?php echo json_encode(date('Y-m-d')); ?>
};
</script>
<script src="../script/chart.js"></script>
<script src="assets/painel.js?v=<?php echo $v('assets/painel.js'); ?>"></script>
</body>
</html>
