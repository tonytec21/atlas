<?php
/**
 * =====================================================================
 * api_sistemas.php — Cadastro e homologação de sistemas integradores
 * ---------------------------------------------------------------------
 * ATLAS-OS-API-BUILD: 2026-07-31-v1
 *
 * Tela administrativa da API do módulo O.S. Aqui a serventia:
 *   - cadastra o sistema parceiro (o que lavra os atos e gera os selos);
 *   - homologa esse cadastro, liberando o acesso;
 *   - emite e reemite o token;
 *   - alterna entre HOMOLOGAÇÃO e PRODUÇÃO;
 *   - suspende o acesso sem perder o histórico;
 *   - acompanha a trilha de auditoria de cada chamada.
 *
 * O token aparece UMA ÚNICA VEZ, na hora em que é gerado. No banco fica
 * apenas o SHA-256. Perdeu, gera outro.
 * =====================================================================
 */
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/../checar_acesso_de_administrador.php');

require_once __DIR__ . '/api/api_config.php';
require_once __DIR__ . '/api/api_auth.php';

api_migrar();
$pdo = api_pdo();

if (empty($_SESSION['api_csrf'])) {
    $_SESSION['api_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['api_csrf'];

$esc  = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$aviso = null;
$erro  = null;
$tokenNovo = null;   // exibido uma única vez
$sistemaNovo = null;

$usuario = $_SESSION['username'] ?? 'sistema';

$ESCOPOS = [
    'os:ler'          => 'Consultar O.S., atos, saldo e liquidações',
    'os:criar'        => 'Criar Ordens de Serviço',
    'pagamento:criar' => 'Lançar pagamentos',
    'ato:liquidar'    => 'Liquidar atos',
    'ato:liberar'     => 'Desfazer liquidação do dia (destrutivo)',
];

/* --------------------------------------------------------------------
 * Ações
 * ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Sessão expirada. Recarregue a página e tente de novo.');
        }

        $acao = $_POST['acao'] ?? '';
        $id   = (int) ($_POST['id'] ?? 0);

        if ($acao === 'cadastrar') {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            if ($nome === '') {
                throw new RuntimeException('Informe o nome do sistema.');
            }

            $ambiente = ($_POST['ambiente'] ?? 'homologacao') === 'producao' ? 'producao' : 'homologacao';
            $escopos  = array_values(array_intersect((array) ($_POST['escopos'] ?? []), array_keys($ESCOPOS)));
            if (!$escopos) {
                throw new RuntimeException('Selecione ao menos um escopo.');
            }

            $clientId = api_gerar_client_id();
            $token    = api_gerar_token($ambiente);

            $pdo->prepare(
                "INSERT INTO api_sistemas
                 (nome, responsavel, email, documento, client_id, token_hash, token_prefixo,
                  ambiente, status, escopos, ips_permitidos, observacoes, criado_em, criado_por)
                 VALUES (:nome, :resp, :mail, :doc, :cid, :hash, :pref,
                         :amb, 'pendente', :esc, :ips, :obs, NOW(), :por)"
            )->execute([
                ':nome' => mb_substr($nome, 0, 150),
                ':resp' => mb_substr(trim((string) ($_POST['responsavel'] ?? '')), 0, 150) ?: null,
                ':mail' => mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 150) ?: null,
                ':doc'  => preg_replace('/\D+/', '', (string) ($_POST['documento'] ?? '')) ?: null,
                ':cid'  => $clientId,
                ':hash' => api_hash_token($token),
                ':pref' => substr($token, 0, 14) . '…',
                ':amb'  => $ambiente,
                ':esc'  => implode(',', $escopos),
                ':ips'  => mb_substr(trim((string) ($_POST['ips'] ?? '')), 0, 500) ?: null,
                ':obs'  => trim((string) ($_POST['observacoes'] ?? '')) ?: null,
                ':por'  => $usuario,
            ]);

            $tokenNovo   = $token;
            $sistemaNovo = ['nome' => $nome, 'client_id' => $clientId, 'ambiente' => $ambiente];
            $aviso = 'Sistema cadastrado. Ele ainda está PENDENTE — homologue para liberar o acesso.';

        } elseif ($acao === 'homologacao_rapida') {
            /* Caminho direto: cadastra JÁ HOMOLOGADO em homologação.
               Não há risco — uma credencial de homologação só enxerga as
               O.S. que ela mesma criar, o acervo real fica fora de alcance. */
            $nome = trim((string) ($_POST['nome'] ?? ''));
            if ($nome === '') {
                throw new RuntimeException('Informe o nome do sistema.');
            }

            $clientId = api_gerar_client_id();
            $token    = api_gerar_token('homologacao');

            $pdo->prepare(
                "INSERT INTO api_sistemas
                 (nome, responsavel, email, client_id, token_hash, token_prefixo,
                  ambiente, status, escopos, observacoes, criado_em, criado_por,
                  homologado_em, homologado_por)
                 VALUES (:nome, :resp, :mail, :cid, :hash, :pref,
                         'homologacao', 'ativo', :esc, :obs, NOW(), :por, NOW(), :por2)"
            )->execute([
                ':nome' => mb_substr($nome, 0, 150),
                ':resp' => mb_substr(trim((string) ($_POST['responsavel'] ?? '')), 0, 150) ?: null,
                ':mail' => mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 150) ?: null,
                ':cid'  => $clientId,
                ':hash' => api_hash_token($token),
                ':pref' => substr($token, 0, 14) . '…',
                /* 'ato:liberar' fica DE FORA por padrão: desfazer liquidação
                   apaga registro. Ligue na tela, caso a caso. */
                ':esc'  => 'os:ler,os:criar,pagamento:criar,ato:liquidar',
                ':obs'  => 'Credencial de homologação gerada pelo painel.',
                ':por'  => $usuario,
                ':por2' => $usuario,
            ]);

            $tokenNovo   = $token;
            $sistemaNovo = ['nome' => $nome, 'client_id' => $clientId, 'ambiente' => 'homologacao'];
            $aviso = 'Credencial de homologação gerada e já liberada. Envie o kit abaixo ao desenvolvedor.';

        } elseif ($acao === 'homologar') {
            $pdo->prepare(
                "UPDATE api_sistemas
                    SET status = 'ativo', homologado_em = NOW(), homologado_por = :por
                  WHERE id = :id"
            )->execute([':por' => $usuario, ':id' => $id]);
            $aviso = 'Sistema homologado. O acesso está liberado.';

        } elseif ($acao === 'suspender') {
            $pdo->prepare("UPDATE api_sistemas SET status = 'suspenso' WHERE id = :id")->execute([':id' => $id]);
            $aviso = 'Acesso suspenso. O cadastro e o histórico foram preservados.';

        } elseif ($acao === 'reativar') {
            $pdo->prepare("UPDATE api_sistemas SET status = 'ativo' WHERE id = :id")->execute([':id' => $id]);
            $aviso = 'Acesso reativado.';

        } elseif ($acao === 'novo_token') {
            $st = $pdo->prepare("SELECT * FROM api_sistemas WHERE id = ?");
            $st->execute([$id]);
            $s = $st->fetch();
            if (!$s) {
                throw new RuntimeException('Sistema não encontrado.');
            }

            $token = api_gerar_token($s['ambiente']);
            $pdo->prepare("UPDATE api_sistemas SET token_hash = :h, token_prefixo = :p WHERE id = :id")
                ->execute([':h' => api_hash_token($token), ':p' => substr($token, 0, 14) . '…', ':id' => $id]);

            $tokenNovo   = $token;
            $sistemaNovo = ['nome' => $s['nome'], 'client_id' => $s['client_id'], 'ambiente' => $s['ambiente']];
            $aviso = 'Token reemitido. O anterior deixou de funcionar imediatamente.';

        } elseif ($acao === 'promover') {
            /* Homologação -> Produção. Exige token novo: o prefixo do
               token indica o ambiente, e ele precisa acompanhar. */
            $token = api_gerar_token('producao');
            $pdo->prepare(
                "UPDATE api_sistemas
                    SET ambiente = 'producao', token_hash = :h, token_prefixo = :p
                  WHERE id = :id"
            )->execute([':h' => api_hash_token($token), ':p' => substr($token, 0, 14) . '…', ':id' => $id]);

            $st = $pdo->prepare("SELECT nome, client_id FROM api_sistemas WHERE id = ?");
            $st->execute([$id]);
            $s = $st->fetch();

            $tokenNovo   = $token;
            $sistemaNovo = ['nome' => $s['nome'] ?? '', 'client_id' => $s['client_id'] ?? '', 'ambiente' => 'producao'];
            $aviso = 'Sistema promovido a PRODUÇÃO. Um token novo foi emitido — o de homologação não vale mais.';

        } elseif ($acao === 'rebaixar') {
            $token = api_gerar_token('homologacao');
            $pdo->prepare(
                "UPDATE api_sistemas
                    SET ambiente = 'homologacao', token_hash = :h, token_prefixo = :p
                  WHERE id = :id"
            )->execute([':h' => api_hash_token($token), ':p' => substr($token, 0, 14) . '…', ':id' => $id]);

            $st = $pdo->prepare("SELECT nome, client_id FROM api_sistemas WHERE id = ?");
            $st->execute([$id]);
            $s = $st->fetch();

            $tokenNovo   = $token;
            $sistemaNovo = ['nome' => $s['nome'] ?? '', 'client_id' => $s['client_id'] ?? '', 'ambiente' => 'homologacao'];
            $aviso = 'Sistema devolvido a HOMOLOGAÇÃO, com token novo.';

        } elseif ($acao === 'salvar_escopos') {
            $escopos = array_values(array_intersect((array) ($_POST['escopos'] ?? []), array_keys($ESCOPOS)));
            if (!$escopos) {
                throw new RuntimeException('Selecione ao menos um escopo.');
            }
            $pdo->prepare("UPDATE api_sistemas SET escopos = :e, ips_permitidos = :i WHERE id = :id")
                ->execute([
                    ':e'  => implode(',', $escopos),
                    ':i'  => mb_substr(trim((string) ($_POST['ips'] ?? '')), 0, 500) ?: null,
                    ':id' => $id,
                ]);
            $aviso = 'Permissões atualizadas.';
        }

    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }
}

/* --------------------------------------------------------------------
 * Dados da tela
 * ------------------------------------------------------------------ */
$sistemas = $pdo->query("SELECT * FROM api_sistemas ORDER BY status = 'pendente' DESC, id DESC")->fetchAll();

$logs = $pdo->query(
    "SELECT l.*, s.nome AS sistema_nome
       FROM api_log l LEFT JOIN api_sistemas s ON s.id = l.sistema_id
      ORDER BY l.id DESC LIMIT 60"
)->fetchAll();

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
         . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
         . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/os/'), '/') . '/api/v1';

$rotulos = [
    'pendente' => ['Pendente de homologação', 'warning'],
    'ativo'    => ['Homologado', 'success'],
    'suspenso' => ['Suspenso', 'danger'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API O.S. — Sistemas integradores</title>
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <style>
        .card-sis{border:1px solid #e2e8f0;border-radius:12px;background:#fff;margin-bottom:16px;overflow:hidden}
        .card-sis .hd{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;
                      padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
        .card-sis .hd h5{margin:0;font-size:.95rem;font-weight:700;color:#0f172a}
        .card-sis .bd{padding:14px 16px}
        .rot{font-size:.7rem;color:#64748b;text-transform:uppercase;letter-spacing:.03em}
        .val{font-size:.9rem;color:#0f172a;word-break:break-all}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
        .mono{font-family:monospace;font-size:.8rem}
        .tag{display:inline-block;padding:2px 10px;border-radius:999px;font-size:.7rem;font-weight:700}
        .tag.prd{background:#fee2e2;color:#991b1b}
        .tag.hml{background:#fef3c7;color:#92400e}
        .token-box{background:#0f172a;color:#4ade80;padding:14px;border-radius:10px;font-family:monospace;
                   font-size:.85rem;word-break:break-all;margin:10px 0}
        pre.exemplo{background:#0f172a;color:#e2e8f0;padding:14px;border-radius:10px;font-size:.76rem;overflow:auto}
        .log-tab td{font-size:.78rem;vertical-align:middle}
    </style>
</head>
<body>
<?php @include(__DIR__ . '/../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
      <h3 class="m-0">API do módulo O.S. — sistemas integradores</h3>
      <div class="text-nowrap">
        <a href="api/doc.php" target="_blank" class="btn btn-outline-secondary btn-sm">
          <i class="fa fa-book"></i> Documentação
        </a>
        <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#mHml">
          <i class="fa fa-flask"></i> Gerar token de homologação
        </button>
        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#mNovo">
          <i class="fa fa-plus"></i> Cadastro completo
        </button>
      </div>
    </div>
    <p class="text-muted mt-2" style="font-size:.88rem">
      Endereço base da API: <code><?= $esc($baseUrl) ?></code>
    </p>
    <hr>

    <?php if ($erro): ?>
      <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= $esc($erro) ?></div>
    <?php endif; ?>
    <?php if ($aviso): ?>
      <div class="alert alert-success"><i class="fa fa-check"></i> <?= $esc($aviso) ?></div>
    <?php endif; ?>

    <?php if ($tokenNovo):
        $docUrl = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/os/')), '/') . '/api/doc.php';
        $docAbs = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $docUrl;
        $kit = "KIT DE INTEGRAÇÃO — API do módulo O.S. (Atlas)\n"
             . str_repeat('=', 52) . "\n\n"
             . "Sistema......: {$sistemaNovo['nome']}\n"
             . "Ambiente.....: " . strtoupper($sistemaNovo['ambiente']) . "\n"
             . "client_id....: {$sistemaNovo['client_id']}\n\n"
             . "Endereço base:\n  {$baseUrl}\n\n"
             . "Token (enviar no cabeçalho de toda chamada):\n"
             . "  Authorization: Bearer {$tokenNovo}\n\n"
             . "Documentação completa:\n  {$docAbs}\n\n"
             . "Teste rápido:\n"
             . "  curl -H \"Authorization: Bearer {$tokenNovo}\" {$baseUrl}/ping\n\n"
             . ($sistemaNovo['ambiente'] === 'homologacao'
                 ? "OBSERVAÇÃO: esta credencial é de HOMOLOGAÇÃO. Ela só enxerga as O.S.\n"
                 . "criadas por ela mesma pela API. O acervo real do cartório fica fora\n"
                 . "de alcance. Concluídos os testes, solicite a promoção a produção.\n"
                 : "ATENÇÃO: credencial de PRODUÇÃO — opera sobre o acervo real.\n");
    ?>
      <div class="alert alert-warning">
        <h5 class="mb-1"><i class="fa fa-key"></i> Credencial de <?= $esc($sistemaNovo['nome']) ?></h5>
        <p class="mb-2" style="font-size:.85rem">
          <b>Copie agora.</b> O token não será exibido novamente — no banco fica apenas o hash.
          Se perder, será preciso reemitir, e o anterior deixa de funcionar.
        </p>

        <div class="rot">Token</div>
        <div class="token-box" id="tokenNovo"><?= $esc($tokenNovo) ?></div>

        <div class="d-flex flex-wrap mb-3" style="gap:8px">
          <button class="btn btn-dark btn-sm" onclick="copiar('tokenNovo')">
            <i class="fa fa-copy"></i> Copiar só o token
          </button>
          <button class="btn btn-outline-dark btn-sm" onclick="copiar('kitNovo')">
            <i class="fa fa-clipboard"></i> Copiar kit completo (para enviar ao dev)
          </button>
          <a class="btn btn-outline-primary btn-sm" href="api/doc.php" target="_blank">
            <i class="fa fa-book"></i> Abrir documentação
          </a>
        </div>

        <div class="rot mb-1">Kit de integração — texto pronto para enviar ao desenvolvedor</div>
        <pre class="exemplo" id="kitNovo"><?= $esc($kit) ?></pre>
      </div>
    <?php endif; ?>

    <?php if (!$sistemas): ?>
      <div class="alert alert-info">
        Nenhum sistema cadastrado ainda. Cadastre o sistema que lavra os atos e gera os selos para
        liberar a integração.
      </div>
    <?php endif; ?>

    <?php foreach ($sistemas as $s):
        [$rotulo, $cor] = $rotulos[$s['status']] ?? [$s['status'], 'secondary'];
        $escopos = array_filter(array_map('trim', explode(',', (string) $s['escopos'])));
    ?>
      <div class="card-sis">
        <div class="hd">
          <h5>
            <?= $esc($s['nome']) ?>
            <span class="tag <?= $s['ambiente'] === 'producao' ? 'prd' : 'hml' ?>">
              <?= $s['ambiente'] === 'producao' ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO' ?>
            </span>
            <span class="badge badge-<?= $cor ?>"><?= $esc($rotulo) ?></span>
          </h5>
          <div class="text-nowrap">
            <?php if ($s['status'] === 'pendente'): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Homologar e liberar o acesso deste sistema?')">
                <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                <input type="hidden" name="acao" value="homologar">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-success btn-sm"><i class="fa fa-check"></i> Homologar</button>
              </form>
            <?php elseif ($s['status'] === 'ativo'): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Suspender o acesso deste sistema?')">
                <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                <input type="hidden" name="acao" value="suspender">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-outline-danger btn-sm"><i class="fa fa-pause"></i> Suspender</button>
              </form>
            <?php else: ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                <input type="hidden" name="acao" value="reativar">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-outline-success btn-sm"><i class="fa fa-play"></i> Reativar</button>
              </form>
            <?php endif; ?>

            <?php if ($s['ambiente'] === 'homologacao'): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Promover a PRODUÇÃO? Um token novo será emitido e o atual deixará de funcionar.')">
                <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                <input type="hidden" name="acao" value="promover">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-outline-primary btn-sm"><i class="fa fa-arrow-up"></i> Promover a produção</button>
              </form>
            <?php else: ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Devolver a HOMOLOGAÇÃO? Um token novo será emitido.')">
                <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
                <input type="hidden" name="acao" value="rebaixar">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-down"></i> Voltar p/ homologação</button>
              </form>
            <?php endif; ?>

            <form method="post" class="d-inline" onsubmit="return confirm('Reemitir o token? O atual para de funcionar na hora.')">
              <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
              <input type="hidden" name="acao" value="novo_token">
              <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button class="btn btn-outline-warning btn-sm"><i class="fa fa-key"></i> Reemitir token</button>
            </form>
          </div>
        </div>

        <div class="bd">
          <div class="grid mb-3">
            <div><div class="rot">client_id</div><div class="val mono"><?= $esc($s['client_id']) ?></div></div>
            <div><div class="rot">Token</div><div class="val mono"><?= $esc($s['token_prefixo']) ?></div></div>
            <div><div class="rot">Responsável</div><div class="val"><?= $esc($s['responsavel'] ?: '—') ?></div></div>
            <div><div class="rot">E-mail</div><div class="val"><?= $esc($s['email'] ?: '—') ?></div></div>
            <div><div class="rot">Cadastrado em</div><div class="val"><?= $esc(atlas_data_br($s['criado_em'])) ?></div></div>
            <div><div class="rot">Homologado em</div><div class="val"><?= $esc(atlas_data_br($s['homologado_em'])) ?></div></div>
            <div><div class="rot">Último acesso</div><div class="val"><?= $esc(atlas_data_br($s['ultimo_acesso_em'])) ?></div></div>
            <div><div class="rot">Requisições</div><div class="val"><?= number_format((int) $s['total_requisicoes'], 0, ',', '.') ?></div></div>
          </div>

          <form method="post" class="form-row align-items-end">
            <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
            <input type="hidden" name="acao" value="salvar_escopos">
            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">

            <div class="col-md-7 mb-2">
              <div class="rot mb-1">Escopos</div>
              <?php foreach ($ESCOPOS as $k => $rotuloEsc): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="checkbox" name="escopos[]" value="<?= $k ?>"
                         id="e<?= (int) $s['id'] ?><?= md5($k) ?>" <?= in_array($k, $escopos, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" style="font-size:.8rem" for="e<?= (int) $s['id'] ?><?= md5($k) ?>">
                    <?= $esc($k) ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="col-md-3 mb-2">
              <div class="rot mb-1">IPs permitidos (vazio = qualquer)</div>
              <input type="text" name="ips" class="form-control form-control-sm"
                     placeholder="192.168.0.10, 200.1.2.3" value="<?= $esc($s['ips_permitidos']) ?>">
            </div>
            <div class="col-md-2 mb-2">
              <button class="btn btn-outline-primary btn-sm btn-block"><i class="fa fa-save"></i> Salvar</button>
            </div>
          </form>

          <?php if ($s['observacoes']): ?>
            <div class="rot mt-2">Observações</div>
            <div class="val" style="font-size:.83rem"><?= nl2br($esc($s['observacoes'])) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <h5 class="mt-4">Últimas chamadas</h5>
    <div class="table-responsive">
      <table class="table table-sm table-bordered table-striped log-tab">
        <thead><tr>
          <th>Quando</th><th>Sistema</th><th>Rota</th><th>O.S.</th>
          <th>HTTP</th><th>Erro</th><th>IP</th><th class="text-right">ms</th>
        </tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td class="text-nowrap"><?= $esc(atlas_data_br($l['criado_em'], 'd/m H:i:s')) ?></td>
            <td><?= $esc($l['sistema_nome'] ?: '—') ?></td>
            <td class="mono"><?= $esc($l['metodo']) ?> <?= $esc($l['rota']) ?></td>
            <td><?= $l['os_id'] ? '<a href="visualizar_os.php?id=' . (int) $l['os_id'] . '">' . (int) $l['os_id'] . '</a>' : '—' ?></td>
            <td>
              <span class="badge badge-<?= ((int) $l['status_http'] < 400) ? 'success' : (((int) $l['status_http'] < 500) ? 'warning' : 'danger') ?>">
                <?= (int) $l['status_http'] ?>
              </span>
            </td>
            <td><?= $esc($l['codigo_erro'] ?: '—') ?></td>
            <td class="mono"><?= $esc($l['ip']) ?></td>
            <td class="text-right"><?= $l['duracao_ms'] !== null ? (int) $l['duracao_ms'] : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?>
          <tr><td colspan="8" class="text-center text-muted py-3">Nenhuma chamada registrada.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: token de homologação (caminho rápido) -->
<div class="modal fade" id="mHml" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
      <input type="hidden" name="acao" value="homologacao_rapida">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-flask"></i> Gerar token de homologação</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p style="font-size:.88rem">
          Emite uma credencial de <b>homologação já liberada</b>, para o sistema parceiro
          começar a integrar hoje mesmo.
        </p>
        <div class="mb-3">
          <label>Nome do sistema *</label>
          <input type="text" name="nome" class="form-control" required
                 placeholder="Ex.: Sistema de Lavratura e Selagem">
        </div>
        <div class="form-row">
          <div class="col-md-6 mb-3">
            <label>Responsável</label>
            <input type="text" name="responsavel" class="form-control">
          </div>
          <div class="col-md-6 mb-3">
            <label>E-mail</label>
            <input type="email" name="email" class="form-control">
          </div>
        </div>
        <div class="alert alert-success mb-0" style="font-size:.83rem">
          <b>Por que é seguro liberar de imediato:</b> uma credencial de homologação só enxerga
          as O.S. que ela mesma criar pela API. O acervo real do cartório fica fora de alcance —
          o parceiro testa criar O.S., lançar pagamento e liquidar ato sem tocar em nada de
          verdade. Todos os escopos vêm ligados; ajuste depois se quiser.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-warning"><i class="fa fa-key"></i> Gerar credencial</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: cadastro -->
<div class="modal fade" id="mNovo" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
      <input type="hidden" name="acao" value="cadastrar">
      <div class="modal-header">
        <h5 class="modal-title">Cadastrar sistema integrador</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="col-md-7 mb-3">
            <label>Nome do sistema *</label>
            <input type="text" name="nome" class="form-control" required placeholder="Ex.: Sistema de Lavratura e Selagem">
          </div>
          <div class="col-md-5 mb-3">
            <label>Ambiente inicial</label>
            <select name="ambiente" class="form-control">
              <option value="homologacao">Homologação (recomendado)</option>
              <option value="producao">Produção</option>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label>Responsável</label>
            <input type="text" name="responsavel" class="form-control">
          </div>
          <div class="col-md-6 mb-3">
            <label>E-mail</label>
            <input type="email" name="email" class="form-control">
          </div>
          <div class="col-md-6 mb-3">
            <label>CNPJ / CPF</label>
            <input type="text" name="documento" class="form-control">
          </div>
          <div class="col-md-6 mb-3">
            <label>IPs permitidos (vazio = qualquer)</label>
            <input type="text" name="ips" class="form-control" placeholder="192.168.0.10, 200.1.2.3">
          </div>
          <div class="col-12 mb-3">
            <label>Escopos</label>
            <?php foreach ($ESCOPOS as $k => $rotuloEsc): ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="escopos[]" value="<?= $k ?>"
                       id="n<?= md5($k) ?>" <?= $k === 'ato:liberar' ? '' : 'checked' ?>>
                <label class="form-check-label" for="n<?= md5($k) ?>">
                  <code><?= $esc($k) ?></code> — <?= $esc($rotuloEsc) ?>
                  <?php if ($k === 'ato:liberar'): ?>
                    <span class="badge badge-danger" style="font-size:.65rem">destrutivo</span>
                  <?php endif; ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="col-12">
            <label>Observações</label>
            <textarea name="observacoes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="alert alert-info mb-0" style="font-size:.83rem">
          O sistema nasce <b>pendente</b>: o token é emitido, mas nenhuma chamada é aceita até a
          homologação. Em <b>homologação</b>, ele só enxerga as O.S. que ele mesmo criar pela API —
          o acervo real da serventia fica fora de alcance.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary"><i class="fa fa-plus"></i> Cadastrar e emitir token</button>
      </div>
    </form>
  </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script>
function copiar(id) {
    var el = document.getElementById(id);
    if (!el) return;
    var r = document.createRange();
    r.selectNodeContents(el);
    var s = window.getSelection();
    s.removeAllRanges(); s.addRange(r);
    try { document.execCommand('copy'); alert('Token copiado.'); }
    catch (e) { alert('Selecione e copie manualmente.'); }
    s.removeAllRanges();
}
</script>
</body>
</html>
