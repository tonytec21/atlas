<?php
/**
 * pe_teste_boleto.php — Emissão de boleto de TESTE (somente sandbox).
 * Acesso restrito a administradores.
 *
 * Faz um login novo com a configuração salva, emite um boleto com os dados
 * informados e mostra exatamente o que foi enviado e a resposta crua da
 * Parcela Express. Nada é gravado na O.S.
 */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/pe_lib.php';

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$bonito = static function ($txt) {
    $j = json_decode((string) $txt, true);
    return $j !== null ? json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $txt;
};

$cfg = pe_config(true);
$sandbox = ($cfg['ambiente'] ?? 'sandbox') !== 'production';

$f = [
    'valor' => trim((string) ($_POST['valor'] ?? '13,37')),
    'nome' => trim((string) ($_POST['nome'] ?? '')),
    'cpf' => trim((string) ($_POST['cpf'] ?? '')),
];

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && pe_csrf_check($_POST['csrf'] ?? null) && $sandbox) {
    $cli = pe_cliente($cfg);
    $cli->esquecerToken();

    $login = $cli->testarLogin();
    $seller = is_array($login['data'] ?? null) ? ($login['data']['seller'] ?? []) : [];

    $resultado = [
        'conta' => $cli->getEmail(),
        'login_http' => $login['http_code'] ?? 0,
        'login_ok' => !empty($login['success']),
        'seller' => $seller,
        'seller_usado' => trim((string) ($cfg['seller_id'] ?? '')) !== '' ? trim((string) $cfg['seller_id']) . ' (configuração)' : ($seller['id'] ?? '') . ' (login)',
    ];

    if (!empty($login['success'])) {
        $valor = (float) str_replace(',', '.', str_replace('.', '', $f['valor']));
        $ref = 'OS_TESTE_' . time();
        $res = $cli->emitirBoleto([
            'referencia_id' => $ref,
            'valor' => $valor,
            'vencimento' => (new DateTimeImmutable('now', new DateTimeZone('America/Fortaleza')))->modify('+3 days')->format('Y-m-d\TH:i:s\Z'),
            'cliente_nome' => $f['nome'],
            'cliente_documento' => $f['cpf'],
            'cliente_email' => '',
            'endereco_rua' => '', 'endereco_numero' => 'S/N', 'endereco_bairro' => '',
            'endereco_cidade' => '', 'endereco_uf' => '', 'endereco_cep' => '',
            'instrucoes' => 'Cobrança OS TESTE',
        ]);
        $resultado['emissao_ok'] = !empty($res['success']);
        $resultado['req'] = $cli->ultimaRequisicao;
        $resultado['msg'] = !empty($res['success']) ? 'Boleto emitido.' : ParcelaExpressClient::mensagemErro($res, 'Erro na emissão');
    }

    pe_log('info', 'teste', 'Boleto de teste: ' . json_encode([
        'conta' => $resultado['conta'], 'seller' => $seller['id'] ?? null,
        'ok' => $resultado['emissao_ok'] ?? false, 'msg' => $resultado['msg'] ?? null,
    ], JSON_UNESCAPED_UNICODE));
}
?><!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Boleto de teste — Pagamento Online</title>
<link rel="stylesheet" href="../../style/css/bootstrap.min.css">
<link rel="stylesheet" href="../../style/css/font-awesome.min.css">
<link rel="stylesheet" href="../../style/css/style.css">
<link rel="stylesheet" href="../ui-config.css">
<style>
 .tb-card{background:var(--bg-elevated,#fff);border:1px solid var(--border-primary,#e4e9f0);border-radius:12px;padding:18px 20px;margin-bottom:16px}
 .tb-card h2{font-size:15px;font-weight:800;margin:0 0 12px}
 .tb-grid{display:grid;grid-template-columns:140px 1fr 200px auto;gap:10px;align-items:end}
 .tb-grid label{font-size:12px;font-weight:700;margin:0 0 4px;display:block}
 .tb-grid input{width:100%;height:38px;border:1px solid var(--border-primary,#cbd5e1);border-radius:8px;padding:0 10px;background:transparent;color:inherit}
 .tb-btn{height:38px;border:0;border-radius:8px;background:#4f46e5;color:#fff;font-weight:700;padding:0 18px}
 .tb-ok{color:#047857;font-weight:800} .tb-erro{color:#b91c1c;font-weight:800}
 pre{background:#0f172a;color:#e2e8f0;border-radius:8px;padding:12px;font-size:12px;max-height:420px;overflow:auto;white-space:pre-wrap;word-break:break-word}
 dl{display:grid;grid-template-columns:170px 1fr;gap:4px 12px;margin:0} dt{font-weight:700;font-size:13px} dd{margin:0;font-size:13px}
 @media(max-width:800px){.tb-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include(__DIR__ . '/../../menu.php'); ?>
<div id="main" class="main-content"><div class="container" style="max-width:1000px;padding-top:12px">

  <h1 style="font-size:22px;font-weight:800">Boleto de teste <small class="text-muted" style="font-size:13px">v<?= $h(PE_VERSAO) ?></small></h1>
  <p class="text-muted" style="font-size:13px">Faz um login novo com a configuração salva e emite um boleto no <b>sandbox</b>, mostrando o envio e a resposta da Parcela Express. Nada é lançado em O.S.
    <a href="pe_config.php">Voltar à configuração</a></p>

  <?php if (!$sandbox): ?>
    <div class="tb-card"><span class="tb-erro">Disponível só com o Ambiente em Sandbox.</span></div>
  <?php else: ?>
  <form method="post" class="tb-card">
    <input type="hidden" name="csrf" value="<?= $h(pe_csrf_token()) ?>">
    <div class="tb-grid">
      <div><label for="valor">Valor (R$)</label><input id="valor" name="valor" value="<?= $h($f['valor']) ?>"></div>
      <div><label for="nome">Pagador</label><input id="nome" name="nome" value="<?= $h($f['nome']) ?>" required></div>
      <div><label for="cpf">CPF/CNPJ</label><input id="cpf" name="cpf" value="<?= $h($f['cpf']) ?>" required></div>
      <div><button class="tb-btn" type="submit"><i class="fa fa-paper-plane"></i> Emitir teste</button></div>
    </div>
  </form>
  <?php endif; ?>

  <?php if ($resultado): ?>
    <div class="tb-card">
      <h2>Login</h2>
      <dl>
        <dt>Conta</dt><dd><?= $h($resultado['conta']) ?></dd>
        <dt>Resultado</dt><dd class="<?= $resultado['login_ok'] ? 'tb-ok' : 'tb-erro' ?>"><?= $resultado['login_ok'] ? 'OK' : 'Recusado' ?> (HTTP <?= (int) $resultado['login_http'] ?>)</dd>
        <dt>Estabelecimento usado</dt><dd><code><?= $h($resultado['seller_usado'] ?? '') ?></code></dd>
        <dt>Estabelecimento do login</dt><dd><pre style="margin:0"><?= $h(json_encode($resultado['seller'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></dd>
      </dl>
    </div>
    <?php if (isset($resultado['req'])): ?>
      <div class="tb-card">
        <h2>Emissão — <span class="<?= $resultado['emissao_ok'] ? 'tb-ok' : 'tb-erro' ?>"><?= $h($resultado['msg']) ?></span></h2>
        <dl>
          <dt>Requisição</dt><dd><?= $h(($resultado['req']['metodo'] ?? '') . ' ' . ($resultado['req']['url'] ?? '')) ?></dd>
          <dt>HTTP</dt><dd><?= (int) ($resultado['req']['http'] ?? 0) ?></dd>
        </dl>
        <h2 style="margin-top:14px">Enviado</h2>
        <pre><?= $h($bonito($resultado['req']['corpo'] ?? '')) ?></pre>
        <h2>Resposta</h2>
        <pre><?= $h($bonito($resultado['req']['resposta'] ?? '')) ?></pre>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div></div>
</body>
</html>
