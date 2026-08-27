<?php
/**
 * pe_diagnostico.php — Verifica as premissas do módulo contra o banco real.
 *
 * Existe por causa de um erro concreto: o módulo assumiu a tabela
 * "ordem_de_servico" quando o Atlas usa "ordens_de_servico". A consulta
 * falhava dentro do include e o botão simplesmente sumia, sem mensagem.
 * Este arquivo torna esse tipo de divergência visível em um clique.
 *
 * Acesso restrito a administradores.
 */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/pe_lib.php';

$checagens = [];

function pe_check(string $titulo, callable $fn): array
{
    try {
        $r = $fn();

        return ['titulo' => $titulo, 'ok' => (bool) ($r['ok'] ?? true), 'detalhe' => $r['detalhe'] ?? ''];
    } catch (Throwable $e) {
        return ['titulo' => $titulo, 'ok' => false, 'detalhe' => $e->getMessage()];
    }
}

$checagens[] = pe_check('Conexão com o banco', function () {
    pe_pdo()->query('SELECT 1');

    return ['detalhe' => 'Conectado.'];
});

$checagens[] = pe_check('Tabelas do módulo', function () {
    pe_migrar();

    return ['detalhe' => 'pe_config, pe_pos_sales e pe_log presentes.'];
});

// Tabelas do Atlas que o módulo consulta.
foreach ([
    'ordens_de_servico' => ['id', 'cliente', 'total_os'],
    'pagamento_os' => ['ordem_de_servico_id', 'total_pagamento', 'forma_de_pagamento', 'funcionario', 'status'],
    'funcionarios' => ['usuario', 'nivel_de_acesso'],
] as $tabela => $colunas) {
    $checagens[] = pe_check("Tabela {$tabela}", function () use ($tabela, $colunas) {
        $pdo = pe_pdo();
        $existentes = [];
        $r = $pdo->query("SHOW COLUMNS FROM `{$tabela}`");

        while ($linha = $r->fetch(PDO::FETCH_ASSOC)) {
            $existentes[] = $linha['Field'];
        }

        $faltando = array_diff($colunas, $existentes);

        if ($faltando) {
            return ['ok' => false, 'detalhe' => 'Colunas ausentes: ' . implode(', ', $faltando)];
        }

        return ['detalhe' => count($existentes) . ' colunas; todas as exigidas presentes.'];
    });
}

// Opcionais: só entram no cálculo do saldo se existirem.
foreach (['devolucao_os' => 'total_devolucao', 'repasse_credor' => 'total_repasse'] as $tabela => $coluna) {
    $checagens[] = pe_check("Tabela {$tabela} (opcional)", function () use ($tabela, $coluna) {
        $pdo = pe_pdo();
        $r = $pdo->query("SHOW TABLES LIKE '{$tabela}'");

        if (!$r || $r->fetch() === false) {
            return ['detalhe' => 'Ausente — ignorada no cálculo do saldo.'];
        }

        $c = $pdo->query("SHOW COLUMNS FROM `{$tabela}` LIKE '{$coluna}'");

        if (!$c || $c->fetch() === false) {
            return ['ok' => false, 'detalhe' => "Existe, mas sem a coluna {$coluna}."];
        }

        return ['detalhe' => 'Presente e será considerada.'];
    });
}

$checagens[] = pe_check('Configuração', function () {
    $cfg = pe_config(true);
    $p = pe_pendencias($cfg);

    if (!$cfg['ativo']) {
        return ['detalhe' => 'Recurso desativado — a tela de O.S. opera no modo padrão.'];
    }

    if ($p) {
        return ['ok' => false, 'detalhe' => 'Ativo, mas faltando: ' . implode(', ', $p)];
    }

    return ['detalhe' => 'Ativo e completo (ambiente: ' . $cfg['ambiente'] . ').'];
});

$checagens[] = pe_check('Extensão cURL', function () {
    if (!extension_loaded('curl')) {
        return ['ok' => false, 'detalhe' => 'Não carregada. Habilite php_curl no php.ini.'];
    }

    return ['detalhe' => 'Disponível.'];
});

// Teste real do cálculo de saldo com uma O.S. qualquer.
$checagens[] = pe_check('Cálculo de saldo devido', function () {
    $id = pe_pdo()->query("SELECT id FROM ordens_de_servico ORDER BY id DESC LIMIT 1")->fetchColumn();

    if (!$id) {
        return ['detalhe' => 'Nenhuma O.S. cadastrada para testar.'];
    }

    $s = pe_saldo_devido((int) $id);

    return ['detalhe' => sprintf('O.S. %d → saldo devido R$ %s', $id, number_format($s, 2, ',', '.'))];
});

/* ---------------------------------------------------------------------
 * Inspeção da resposta de login.
 *
 * Não adianta adivinhar como a API nomeia o token: aqui a chamada é feita
 * de verdade e a ESTRUTURA da resposta é exibida — nomes de campos e tipos,
 * nunca os valores. Nenhuma senha ou token aparece em tela ou em log.
 * ------------------------------------------------------------------- */
$loginProbe = null;

if (isset($_GET['login']) && pe_habilitado()) {
    try {
        $cfgLogin = \TCloud\ParcelaExpress\Config::fromArray(pe_config(true));
        $httpLogin = new \TCloud\ParcelaExpress\HttpClient($cfgLogin);
        $loginProbe = $httpLogin->probeLogin();

        $loginProbe['url'] = rtrim($cfgLogin->baseUrl(), '/')
            . \TCloud\ParcelaExpress\Endpoints::AUTH_LOGIN;

        $loginProbe['campos'] = is_array($loginProbe['json'])
            ? \TCloud\ParcelaExpress\HttpClient::describeShape($loginProbe['json'])
            : [];
    } catch (Throwable $e) {
        $loginProbe = ['erro' => $e->getMessage(), 'status' => 0, 'json' => null, 'body' => '', 'campos' => []];
    }
}

$falhas = array_filter($checagens, static fn($c) => !$c['ok']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Online — Diagnóstico</title>
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../style/css/style.css">
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">
    <style>

    /* Cores derivadas dos tokens do Atlas — acompanham o modo escuro. */
    :root, .pe-tela {
        --surface: var(--bg-elevated,   #ffffff);
        --canvas:  var(--bg-secondary,  #f6f8fb);
        --ink:     var(--text-primary,  #0b1b2b);
        --muted:   var(--text-secondary,var(--muted));
        --faint:   var(--text-tertiary, var(--faint));
        --line:    var(--border-primary,#e4e9f0);
        --accent:  #0b6b53;
        --ok:      var(--ok);
        --bad:     #b91c1c;
    }
    body.dark-mode {
        --accent: #34d399;
        --ok:     #4ade80;
        --bad:    var(--bad);
    }
    body.dark-mode .chk, body.dark-mode .rt-card, body.dark-mode .sc-card,
    body.dark-mode .rt-lista, body.dark-mode input, body.dark-mode select {
        background: var(--surface);
        color: var(--ink);
    }
    body.dark-mode input, body.dark-mode select { background: rgba(0,0,0,.22); }
        .chk{border:1px solid var(--line);border-radius:10px;padding:14px 18px;margin-bottom:10px;background:var(--surface)}
        .chk.bad{border-color:var(--bad);background:rgba(185,28,28,.08)}
        .chk .t{font-weight:700;font-size:.92rem;color:var(--ink)}
        .chk .d{font-size:.84rem;color:var(--muted);margin-top:2px}
        .chk i{margin-right:8px}
        .ok i{color:var(--ok)}
        .bad i{color:var(--bad)}
    </style>
</head>
<body class="pe-tela">
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Pagamento Online — Diagnóstico</h3>
        <hr>

        <?php if ($falhas): ?>
            <div class="alert alert-danger">
                <strong><?= count($falhas) ?> verificação(ões) falharam.</strong>
                Enquanto houver falha aqui, o botão de cobrança pode não aparecer na O.S.
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                Todas as verificações passaram. O que restar é do lado da API da Parcela Express.
            </div>
        <?php endif; ?>

        <?php foreach ($checagens as $c): ?>
            <div class="chk <?= $c['ok'] ? 'ok' : 'bad' ?>">
                <div class="t">
                    <i class="fa <?= $c['ok'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                    <?= htmlspecialchars($c['titulo'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php if ($c['detalhe']): ?>
                    <div class="d"><?= htmlspecialchars($c['detalhe'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <hr class="mt-4">
        <h5>Resposta de login</h5>
        <p class="text-muted" style="font-size:.86rem">
            Faz a chamada de login de verdade e mostra a <strong>estrutura</strong> da resposta —
            nomes de campos e tipos. Valores não são exibidos nem gravados em log, então nenhuma
            senha ou token aparece aqui. Use para descobrir como a API nomeia o campo do token.
        </p>

        <?php if ($loginProbe === null): ?>
            <a href="?login=1" class="btn btn-outline-primary mb-4">
                <i class="fa fa-sign-in"></i> Inspecionar resposta de login
            </a>
        <?php else: ?>
            <div class="chk mb-4">
                <?php if (!empty($loginProbe['erro'])): ?>
                    <div class="t bad"><i class="fa fa-times-circle"></i> Falha de transporte</div>
                    <div class="d"><?= htmlspecialchars((string) $loginProbe['erro'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php else: ?>
                    <div class="t">
                        <i class="fa fa-info-circle"></i>
                        HTTP <?= (int) $loginProbe['status'] ?>
                        &nbsp;<small class="text-muted"><?= htmlspecialchars((string) ($loginProbe['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                    </div>

                    <?php if (!empty($loginProbe['campos'])): ?>
                        <div class="d mt-2"><strong>Campos recebidos:</strong></div>
                        <ul style="font-size:.84rem;font-family:monospace;margin:6px 0 0 0;padding-left:20px">
                            <?php foreach ($loginProbe['campos'] as $campo): ?>
                                <li><?= htmlspecialchars($campo, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="d mt-2">
                            Se algum destes for o token, me informe o nome do campo — ou, se ele já
                            se chamar <code>token</code>, <code>access_token</code>, <code>accessToken</code>,
                            <code>jwt</code> ou similar, o módulo já o reconhece automaticamente.
                        </div>
                    <?php elseif (is_array($loginProbe['json'])): ?>
                        <div class="d mt-2">A resposta veio em JSON, mas vazia.</div>
                    <?php else: ?>
                        <div class="d mt-2">
                            <strong>A resposta não é JSON.</strong> Primeiros caracteres:
                            <pre style="font-size:.78rem;background:var(--canvas);padding:8px;border-radius:6px;margin-top:6px;white-space:pre-wrap"><?=
                                htmlspecialchars(mb_substr((string) $loginProbe['body'], 0, 400), ENT_QUOTES, 'UTF-8')
                            ?></pre>
                            Isso costuma indicar que <code><?= htmlspecialchars(\TCloud\ParcelaExpress\Endpoints::AUTH_LOGIN, ENT_QUOTES, 'UTF-8') ?></code>
                            não é a rota de login correta.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="mt-4 mb-5">
            <a href="pe_config.php" class="btn btn-secondary">
                <i class="fa fa-cog"></i> Voltar à configuração
            </a>
        </div>
    </div>
</div>
</body>
</html>
