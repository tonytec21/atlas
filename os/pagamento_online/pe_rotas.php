<?php
/**
 * pe_rotas.php — Descobre as rotas da API e deixa o administrador confirmá-las.
 *
 * POR QUE ISTO EXISTE: as rotas em lib/Endpoints.php já batem com o spec
 * publicado, mas APIs mudam. Esta tela busca o spec atual da Parcela
 * Express, mostra o que existe hoje e permite sobrescrever qualquer caminho
 * sem tocar em código — útil numa mudança de versão, e útil também para
 * conferir se algo saiu do ar.
 *
 * Acesso restrito a administradores.
 */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/pe_lib.php';

use TCloud\ParcelaExpress\Config;

pe_migrar();

$cfg = pe_config(true);
$base = rtrim((new Config(
    environment: (string) ($cfg['ambiente'] ?? 'sandbox'),
    sellerId: (string) ($cfg['seller_id'] ?? ''),
    username: '',
    password: '',
    baseUrlOverride: ($cfg['base_url'] ?? '') !== '' ? (string) $cfg['base_url'] : null,
))->baseUrl(), '/');

/* Caminhos onde frameworks costumam servir o JSON do OpenAPI. O primeiro é
   a convenção do NestJS quando o Swagger UI está montado na raiz — que é o
   caso aqui, já que sandbox.parcelaexpress.com.br abre o Swagger. */
$candidatos = ['/-json', '/api-json', '/swagger-json', '/docs-json',
               '/swagger.json', '/openapi.json', '/v1/-json', '/api/docs-json'];

/** As operações que o módulo precisa mapear. */
$operacoes = [
    'AUTH_LOGIN'                 => ['Login / obtenção de token', 'post'],
    'POS_TERMINALS'              => ['Listar terminais (POS)', 'get'],
    'POS_INSTALLMENT_SIMULATION' => ['Simular parcelamento', 'get'],
    'POS_SALE_CREATE'            => ['Criar venda na maquininha', 'post'],
    'POS_SALE_STATUS'            => ['Status da venda', 'get'],
    'POS_SALE_INTERRUPT'         => ['Cancelar venda no terminal', 'post'],
    'POS_SALE_LIST'              => ['Listar vendas', 'get'],
    'BOLETO_CREATE'              => ['Emitir boleto', 'post'],
    'BOLETO_SHOW'                => ['Consultar boleto', 'get'],
    'BOLETO_CANCEL'              => ['Baixar boleto', 'post'],
    'BOLETO_URL'                 => ['URL do PDF do boleto', 'get'],
    'BOLETO_LIST'                => ['Listar boletos', 'get'],
];

$erroSpec = null;
$origemSpec = null;
$rotas = [];

function pe_buscar(string $url, int $timeout = 15): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $corpo = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => is_string($corpo) ? $corpo : ''];
}

// ---- Descoberta ----
if (isset($_GET['buscar'])) {
    foreach ($candidatos as $caminho) {
        $r = pe_buscar($base . $caminho);

        if ($r['status'] !== 200 || $r['body'] === '') {
            continue;
        }

        $spec = json_decode($r['body'], true);

        if (!is_array($spec) || empty($spec['paths'])) {
            continue;
        }

        $origemSpec = $base . $caminho;

        foreach ($spec['paths'] as $caminhoRota => $metodos) {
            if (!is_array($metodos)) {
                continue;
            }

            foreach ($metodos as $metodo => $detalhe) {
                if (!in_array(strtolower($metodo), ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                $rotas[] = [
                    'rota' => (string) $caminhoRota,
                    'metodo' => strtoupper($metodo),
                    'resumo' => is_array($detalhe) ? (string) ($detalhe['summary'] ?? $detalhe['operationId'] ?? '') : '',
                    'tags' => is_array($detalhe) && !empty($detalhe['tags']) ? implode(', ', $detalhe['tags']) : '',
                ];
            }
        }

        break;
    }

    if ($origemSpec === null) {
        $erroSpec = 'Nenhum dos caminhos conhecidos devolveu um spec OpenAPI válido. '
            . 'Abra o Swagger no navegador, veja em Rede (F12) qual URL retorna o JSON '
            . 'e informe abaixo.';
    }
}

// URL informada manualmente.
if (isset($_GET['spec_url']) && trim((string) $_GET['spec_url']) !== '') {
    $url = trim((string) $_GET['spec_url']);
    $r = pe_buscar($url);
    $spec = json_decode($r['body'], true);

    if (is_array($spec) && !empty($spec['paths'])) {
        $origemSpec = $url;
        $rotas = [];

        foreach ($spec['paths'] as $caminhoRota => $metodos) {
            foreach ((array) $metodos as $metodo => $detalhe) {
                if (!in_array(strtolower((string) $metodo), ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                $rotas[] = [
                    'rota' => (string) $caminhoRota,
                    'metodo' => strtoupper((string) $metodo),
                    'resumo' => is_array($detalhe) ? (string) ($detalhe['summary'] ?? $detalhe['operationId'] ?? '') : '',
                    'tags' => is_array($detalhe) && !empty($detalhe['tags']) ? implode(', ', $detalhe['tags']) : '',
                ];
            }
        }

        $erroSpec = null;
    } else {
        $erroSpec = 'A URL informada não devolveu um spec OpenAPI válido (HTTP ' . $r['status'] . ').';
    }
}

// ---- Gravação ----
$salvo = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && pe_csrf_check($_POST['csrf'] ?? null)) {
    $stmt = pe_pdo()->prepare(
        "INSERT INTO pe_rotas (chave, rota, metodo, confirmado) VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE rota = VALUES(rota), metodo = VALUES(metodo), confirmado = NOW()"
    );
    $limpar = pe_pdo()->prepare("DELETE FROM pe_rotas WHERE chave = ?");

    foreach ($operacoes as $chave => $info) {
        $rota = trim((string) ($_POST['rota'][$chave] ?? ''));

        if ($rota === '') {
            $limpar->execute([$chave]);
            continue;
        }

        $stmt->execute([$chave, $rota, strtoupper($info[1])]);
    }

    pe_log('info', 'rotas', 'Mapeamento de rotas atualizado.');
    $salvo = true;
}

$atuais = [];
$r = pe_pdo()->query("SELECT chave, rota FROM pe_rotas");
while ($linha = $r->fetch(PDO::FETCH_ASSOC)) {
    $atuais[$linha['chave']] = $linha['rota'];
}

/** Sugere a rota mais provável para uma operação, por palavras-chave. */
function pe_sugerir(string $chave, array $rotas): array
{
    $pistas = [
        'AUTH_LOGIN' => [['login', 'auth', 'token', 'sign-in', 'sessions'], 'POST'],
        'POS_TERMINALS' => [['/pos', 'terminal', 'device'], 'GET'],
        'POS_INSTALLMENT_SIMULATION' => [['installment', 'parcel', 'simul', 'fee', 'tax'], 'GET'],
        'POS_SALE_CREATE' => [['pos', 'terminal', 'sale', 'venda'], 'POST'],
        'POS_SALE_STATUS' => [['pos', 'sale', 'status', 'venda'], 'GET'],
        'POS_SALE_INTERRUPT' => [['interrupt', 'cancel', 'abort', 'pos'], 'POST'],
        'POS_SALE_LIST' => [['pos', 'sale', 'venda'], 'GET'],
        'BOLETO_CREATE' => [['billet', 'boleto', 'bank-slip', 'charge'], 'POST'],
        'BOLETO_SHOW' => [['billet', 'boleto', 'bank-slip', 'charge'], 'GET'],
        'BOLETO_CANCEL' => [['void', 'billet', 'boleto'], 'POST'],
        'BOLETO_URL' => [['billet', 'url'], 'GET'],
        'BOLETO_LIST' => [['sellers', 'billets'], 'GET'],
    ];

    if (!isset($pistas[$chave])) {
        return [];
    }

    [$palavras, $metodo] = $pistas[$chave];
    $pontuadas = [];

    foreach ($rotas as $r) {
        if ($r['metodo'] !== $metodo) {
            continue;
        }

        $alvo = strtolower($r['rota'] . ' ' . $r['resumo'] . ' ' . $r['tags']);
        $pontos = 0;

        foreach ($palavras as $p) {
            if (str_contains($alvo, $p)) {
                $pontos++;
            }
        }

        if ($pontos > 0) {
            $pontuadas[] = ['pontos' => $pontos, 'rota' => $r['rota']];
        }
    }

    usort($pontuadas, static fn($a, $b) => $b['pontos'] <=> $a['pontos']);

    return array_slice(array_column($pontuadas, 'rota'), 0, 3);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Online — Rotas da API</title>
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
        --aviso:   #b45309;
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
        .rt-card{border:1px solid var(--line);border-radius:10px;background:var(--surface);padding:16px 18px;margin-bottom:12px}
        .rt-op{font-weight:700;font-size:.9rem;color:var(--ink);margin-bottom:2px}
        .rt-hint{font-size:.78rem;color:var(--muted)}
        .rt-sug{font-size:.78rem;margin-top:6px}
        .rt-sug button{border:1px solid var(--line);background:var(--canvas);border-radius:6px;padding:3px 9px;
                       font-family:monospace;font-size:.76rem;margin:2px 4px 2px 0;cursor:pointer}
        .rt-sug button:hover{background:var(--line)}
        input.rt-rota{font-family:monospace;font-size:.84rem}
        .rt-metodo{display:inline-block;font-size:.68rem;font-weight:700;padding:2px 7px;
                   border-radius:4px;background:var(--accent);color:#fff;margin-right:6px}
        .rt-lista{max-height:340px;overflow:auto;border:1px solid var(--line);border-radius:10px;background:var(--surface)}
        .rt-lista div{padding:6px 12px;border-bottom:1px solid var(--canvas);font-family:monospace;font-size:.78rem}
    </style>
</head>
<body class="pe-tela">
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Rotas da API</h3>
        <hr>

        <?php if ($salvo): ?>
            <div class="alert alert-success">Mapeamento salvo. As chamadas passam a usar estas rotas imediatamente.</div>
        <?php endif; ?>

        <p class="text-muted" style="font-size:.88rem">
            As rotas que acompanham o módulo estão confirmadas contra o spec
            <em>Parcela Express API - Parceiros</em> v1.0. Deixe todos os campos vazios para
            usar esses padrões. Preencha apenas se a API mudar e algum caminho parar de
            responder — o valor gravado passa a valer imediatamente, sem editar código.
        </p>

        <div class="mb-4">
            <a href="?buscar=1" class="btn btn-primary">
                <i class="fa fa-search"></i> Buscar spec da API
            </a>
            <span class="text-muted ml-2" style="font-size:.82rem">Base: <code><?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?></code></span>
        </div>

        <?php if ($erroSpec): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($erroSpec, ENT_QUOTES, 'UTF-8') ?></div>
            <form method="get" class="form-inline mb-4">
                <input type="text" name="spec_url" class="form-control mr-2" style="width:420px;font-family:monospace"
                       placeholder="https://sandbox.parcelaexpress.com.br/-json">
                <button class="btn btn-outline-secondary">Buscar nesta URL</button>
            </form>
        <?php endif; ?>

        <?php if ($origemSpec): ?>
            <div class="alert alert-success">
                Spec encontrado em <code><?= htmlspecialchars($origemSpec, ENT_QUOTES, 'UTF-8') ?></code> —
                <?= count($rotas) ?> operações disponíveis.
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(pe_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

            <?php foreach ($operacoes as $chave => $info):
                $sugestoes = $rotas ? pe_sugerir($chave, $rotas) : [];
                $valor = $atuais[$chave] ?? '';
            ?>
                <div class="rt-card">
                    <div class="rt-op">
                        <span class="rt-metodo"><?= strtoupper($info[1]) ?></span>
                        <?= htmlspecialchars($info[0], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="rt-hint">
                        Em uso: <code><?= htmlspecialchars(TCloud\ParcelaExpress\Endpoints::path($chave), ENT_QUOTES, 'UTF-8') ?></code>
                        <?php if (!isset($atuais[$chave])): ?>
                            <span style="color:#16a34a">&nbsp;confirmado no spec</span>
                        <?php else: ?>
                            <span style="color:var(--aviso,#b45309)">&nbsp;sobrescrito</span>
                        <?php endif; ?>
                    </div>
                    <input type="text" class="form-control rt-rota mt-2" name="rota[<?= $chave ?>]"
                           value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Deixe vazio para usar o padrão"
                           id="in_<?= $chave ?>">

                    <?php if ($sugestoes): ?>
                        <div class="rt-sug">
                            Sugestões do spec:
                            <?php foreach ($sugestoes as $sug): ?>
                                <button type="button" onclick="document.getElementById('in_<?= $chave ?>').value=this.textContent"><?= htmlspecialchars($sug, ENT_QUOTES, 'UTF-8') ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="pe_config.php" class="btn btn-secondary">Voltar</a>
                <button class="btn btn-primary"><i class="fa fa-save"></i> Salvar mapeamento</button>
            </div>
        </form>

        <?php if ($rotas): ?>
            <h5>Todas as operações do spec</h5>
            <p class="text-muted" style="font-size:.82rem">Use como referência caso a sugestão automática não acerte.</p>
            <div class="rt-lista mb-5">
                <?php foreach ($rotas as $r): ?>
                    <div>
                        <span class="rt-metodo" style="background:var(--muted)"><?= $r['metodo'] ?></span>
                        <?= htmlspecialchars($r['rota'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($r['resumo']): ?>
                            <span style="color:#64748b;font-family:system-ui"> — <?= htmlspecialchars($r['resumo'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
