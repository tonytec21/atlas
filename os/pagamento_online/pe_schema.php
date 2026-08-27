<?php
/**
 * pe_schema.php — Mostra o formato exato que a API espera em cada operação.
 *
 * POR QUE ISTO EXISTE: acertar a rota não basta. Um POST com o caminho certo
 * e nomes de campo errados devolve "Bad Request Exception", sem dizer qual
 * campo. Esta tela lê o spec OpenAPI da própria Parcela Express e mostra o
 * corpo esperado — nomes, tipos e obrigatoriedade.
 *
 * Acesso restrito a administradores.
 */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/pe_lib.php';

use TCloud\ParcelaExpress\Config;
use TCloud\ParcelaExpress\Endpoints;

pe_migrar();
pe_rotas();

$cfg = pe_config(true);
$base = rtrim((new Config(
    environment: (string) ($cfg['ambiente'] ?? 'sandbox'),
    sellerId: (string) ($cfg['seller_id'] ?? ''),
    username: '',
    password: '',
    baseUrlOverride: ($cfg['base_url'] ?? '') !== '' ? (string) $cfg['base_url'] : null,
))->baseUrl(), '/');

/** Operações do módulo que enviam corpo, com rota e método. */
$operacoes = [
    'BOLETO_CREATE'   => ['POST', 'Emitir boleto'],
    'POS_SALE_CREATE' => ['POST', 'Criar venda na maquininha'],
    'POS_SALE_INTERRUPT' => ['POST', 'Cancelar venda no terminal'],
    'AUTH_LOGIN'      => ['POST', 'Login'],
];

$candidatos = ['/-json', '/api-json', '/swagger-json', '/docs-json',
               '/swagger.json', '/openapi.json', '/v1/-json'];

function pe_fetch_json(string $url): ?array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $corpo = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || !is_string($corpo) || $corpo === '') {
        return null;
    }

    $j = json_decode($corpo, true);

    return is_array($j) ? $j : null;
}

$spec = null;
$origem = null;
$erro = null;

$urlManual = trim((string) ($_GET['spec_url'] ?? ''));

if ($urlManual !== '') {
    $spec = pe_fetch_json($urlManual);
    $origem = $spec ? $urlManual : null;
    $erro = $spec ? null : 'A URL informada não devolveu um spec válido.';
} else {
    foreach ($candidatos as $c) {
        $tentativa = pe_fetch_json($base . $c);

        if ($tentativa && !empty($tentativa['paths'])) {
            $spec = $tentativa;
            $origem = $base . $c;
            break;
        }
    }

    if (!$spec) {
        $erro = 'Não foi possível localizar o spec automaticamente. Informe a URL do JSON abaixo.';
    }
}

/** Resolve $ref e devolve as propriedades planas de um schema. */
function pe_resolver(array $spec, array $schema, int $nivel = 0): array
{
    if ($nivel > 4) {
        return [];
    }

    if (isset($schema['$ref'])) {
        $nome = substr((string) $schema['$ref'], strrpos((string) $schema['$ref'], '/') + 1);
        $schema = $spec['components']['schemas'][$nome] ?? [];
    }

    if (isset($schema['allOf'])) {
        $mesclado = [];

        foreach ($schema['allOf'] as $parte) {
            $mesclado = array_merge($mesclado, pe_resolver($spec, $parte, $nivel + 1));
        }

        return $mesclado;
    }

    $obrigatorios = $schema['required'] ?? [];
    $saida = [];

    foreach (($schema['properties'] ?? []) as $nome => $prop) {
        $tipo = $prop['type'] ?? '';

        if (isset($prop['$ref'])) {
            $refNome = substr((string) $prop['$ref'], strrpos((string) $prop['$ref'], '/') + 1);
            $tipo = 'objeto ' . $refNome;
        } elseif ($tipo === 'array') {
            $item = $prop['items'] ?? [];
            $tipo = 'lista de ' . (isset($item['$ref'])
                ? substr((string) $item['$ref'], strrpos((string) $item['$ref'], '/') + 1)
                : ($item['type'] ?? '?'));
        }

        if (!empty($prop['format'])) {
            $tipo .= ' (' . $prop['format'] . ')';
        }

        if (!empty($prop['enum'])) {
            $tipo .= ' — valores: ' . implode(', ', array_map('strval', $prop['enum']));
        }

        $saida[] = [
            'nome' => (string) $nome,
            'tipo' => $tipo,
            'obrigatorio' => in_array($nome, $obrigatorios, true),
            'descricao' => (string) ($prop['description'] ?? ''),
            'exemplo' => isset($prop['example']) && !is_array($prop['example'])
                ? (string) $prop['example'] : '',
            'ref' => isset($prop['$ref'])
                ? substr((string) $prop['$ref'], strrpos((string) $prop['$ref'], '/') + 1)
                : (isset($prop['items']['$ref'])
                    ? substr((string) $prop['items']['$ref'], strrpos((string) $prop['items']['$ref'], '/') + 1)
                    : null),
        ];
    }

    return $saida;
}

/** Acha a definição da operação no spec, pela rota e método. */
function pe_operacao(array $spec, string $rota, string $metodo): ?array
{
    $metodo = strtolower($metodo);

    if (isset($spec['paths'][$rota][$metodo])) {
        return $spec['paths'][$rota][$metodo];
    }

    // O spec pode nomear os placeholders diferente do módulo
    // ({sellerId} vs {seller_id}); comparamos ignorando os nomes.
    $normalizar = static fn (string $r): string => preg_replace('/\{[^}]+\}/', '{}', $r) ?? $r;
    $alvo = $normalizar($rota);

    foreach (($spec['paths'] ?? []) as $caminho => $metodos) {
        if ($normalizar((string) $caminho) === $alvo && isset($metodos[$metodo])) {
            return $metodos[$metodo];
        }
    }

    return null;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Online — Formato dos envios</title>
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
        .sc-card{border:1px solid var(--line);border-radius:10px;background:var(--surface);margin-bottom:18px;overflow:hidden}
        .sc-card>h5{margin:0;padding:12px 16px;background:var(--canvas);border-bottom:1px solid var(--line);font-size:.9rem;font-weight:700}
        .sc-card>h5 code{font-size:.82rem;color:var(--accent)}
        .sc-body{padding:0}
        table.sc{width:100%;font-size:.83rem;margin:0}
        table.sc th{background:var(--canvas);padding:7px 14px;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
        table.sc td{padding:7px 14px;border-top:1px solid var(--canvas);vertical-align:top}
        .campo{font-family:monospace;font-weight:600;color:var(--ink)}
        .obg{color:#b91c1c;font-weight:700;font-size:.7rem}
        .opc{color:var(--faint);font-size:.7rem}
        .tipo{color:var(--muted);font-family:monospace;font-size:.78rem}
        .desc{color:var(--muted)}
    </style>
</head>
<body class="pe-tela">
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Formato dos envios</h3>
        <hr>

        <p class="text-muted" style="font-size:.88rem">
            Acertar a rota não basta: um POST no caminho certo com nomes de campo errados
            devolve <code>Bad Request</code> sem dizer qual campo. Esta tela lê o spec da
            Parcela Express e mostra o corpo que cada operação espera.
        </p>

        <?php if ($erro): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
            <form method="get" class="form-inline mb-4">
                <input type="text" name="spec_url" class="form-control mr-2" style="width:440px;font-family:monospace"
                       placeholder="https://sandbox.parcelaexpress.com.br/-json">
                <button class="btn btn-outline-secondary">Buscar nesta URL</button>
            </form>
        <?php endif; ?>

        <?php if ($spec): ?>
            <div class="alert alert-success" style="font-size:.85rem">
                Spec lido de <code><?= htmlspecialchars((string) $origem, ENT_QUOTES, 'UTF-8') ?></code>.
            </div>

            <?php foreach ($operacoes as $chave => [$metodo, $rotulo]):
                $rota = Endpoints::path($chave);
                $op = pe_operacao($spec, $rota, $metodo);
                $schema = $op['requestBody']['content']['application/json']['schema'] ?? null;
                $campos = $schema ? pe_resolver($spec, $schema) : [];
            ?>
                <div class="sc-card">
                    <h5>
                        <?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?>
                        &nbsp;<code><?= $metodo ?> <?= htmlspecialchars($rota, ENT_QUOTES, 'UTF-8') ?></code>
                    </h5>
                    <div class="sc-body">
                        <?php if (!$op): ?>
                            <div class="p-3 text-danger" style="font-size:.85rem">
                                Operação não encontrada no spec. A rota pode ter mudado — confira em <b>Rotas da API</b>.
                            </div>
                        <?php elseif (!$campos): ?>
                            <div class="p-3 text-muted" style="font-size:.85rem">
                                Sem corpo declarado no spec para esta operação.
                            </div>
                        <?php else: ?>
                            <table class="sc">
                                <thead><tr><th>Campo</th><th>Tipo</th><th>Descrição</th></tr></thead>
                                <tbody>
                                <?php foreach ($campos as $c): ?>
                                    <tr>
                                        <td>
                                            <span class="campo"><?= htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8') ?></span><br>
                                            <?= $c['obrigatorio']
                                                ? '<span class="obg">obrigatório</span>'
                                                : '<span class="opc">opcional</span>' ?>
                                        </td>
                                        <td class="tipo"><?= htmlspecialchars($c['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="desc">
                                            <?= htmlspecialchars($c['descricao'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($c['exemplo'] !== ''): ?>
                                                <br><small>ex.: <code><?= htmlspecialchars($c['exemplo'], ENT_QUOTES, 'UTF-8') ?></code></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if ($c['ref']):
                                        $sub = pe_resolver($spec, ['$ref' => '#/components/schemas/' . $c['ref']], 1);
                                        foreach ($sub as $sc): ?>
                                        <tr style="background:var(--canvas)">
                                            <td style="padding-left:34px">
                                                <span class="campo" style="font-weight:400">↳ <?= htmlspecialchars($sc['nome'], ENT_QUOTES, 'UTF-8') ?></span><br>
                                                <?= $sc['obrigatorio']
                                                    ? '<span class="obg">obrigatório</span>'
                                                    : '<span class="opc">opcional</span>' ?>
                                            </td>
                                            <td class="tipo"><?= htmlspecialchars($sc['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="desc">
                                                <?= htmlspecialchars($sc['descricao'], ENT_QUOTES, 'UTF-8') ?>
                                                <?php if ($sc['exemplo'] !== ''): ?>
                                                    <br><small>ex.: <code><?= htmlspecialchars($sc['exemplo'], ENT_QUOTES, 'UTF-8') ?></code></small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="alert alert-info" style="font-size:.85rem">
                Mande esta tela (print ou texto) e eu ajusto os payloads do módulo para
                bater campo a campo.
            </div>
        <?php endif; ?>

        <div class="mb-5">
            <a href="pe_config.php" class="btn btn-secondary">Voltar à configuração</a>
        </div>
    </div>
</div>
</body>
</html>
