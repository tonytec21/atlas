<?php
/** ATLAS-NFSE-BUILD: 2026-07-09-integracao-emissor-nacional */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';
nfse_migrar();

$pdo = nfse_pdo();

$status = $_GET['status'] ?? '';
$busca  = trim((string) ($_GET['q'] ?? ''));
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$porPagina = 30;

$where = [];
$params = [];

if (in_array($status, ['processando', 'autorizada', 'rejeitada', 'cancelada'], true)) {
    $where[] = 'status = :st';
    $params[':st'] = $status;
}
if ($busca !== '') {
    $where[] = '(chave_acesso LIKE :q OR CAST(ordem_servico_id AS CHAR) = :qexato OR tomador_nome LIKE :q)';
    $params[':q'] = '%' . $busca . '%';
    $params[':qexato'] = $busca;
}

$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM nfse_notas $sqlWhere");
$total->execute($params);
$total = (int) $total->fetchColumn();

$offset = ($pagina - 1) * $porPagina;
$st = $pdo->prepare("SELECT * FROM nfse_notas $sqlWhere ORDER BY id DESC LIMIT $porPagina OFFSET $offset");
$st->execute($params);
$notas = $st->fetchAll(PDO::FETCH_ASSOC);

$resumo = $pdo->query("SELECT status, COUNT(*) c, COALESCE(SUM(valor_iss),0) iss FROM nfse_notas GROUP BY status")
              ->fetchAll(PDO::FETCH_ASSOC);

$badge = static function (string $s): string {
    return match ($s) {
        'autorizada'  => '<span class="badge badge-success">Autorizada</span>',
        'rejeitada'   => '<span class="badge badge-danger">Rejeitada</span>',
        'cancelada'   => '<span class="badge badge-secondary">Cancelada</span>',
        'processando' => '<span class="badge badge-warning">Processando</span>',
        default       => '<span class="badge badge-light">' . htmlspecialchars($s) . '</span>',
    };
};
$brl = static fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NFS-e emitidas</title>
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../style/css/style.css">
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">
    <style>
        .kpi{border:1px solid #e2e8f0;border-radius:10px;padding:14px;background:#fff;text-align:center}
        .kpi .n{font-size:1.5rem;font-weight:700;color:#0f172a}
        .kpi .l{font-size:.75rem;color:#64748b;text-transform:uppercase;letter-spacing:.03em}
        .chave{font-family:monospace;font-size:.72rem;word-break:break-all}
        td .msg{font-size:.75rem;color:#b91c1c;max-width:280px;display:block}
    </style>
</head>
<body>
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
      <h3 class="m-0">NFS-e emitidas</h3>
      <a href="nfse_config.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-cog"></i> Configuração</a>
    </div>
    <hr>

    <div class="row mb-4">
      <?php
      $mapa = ['autorizada' => 'Autorizadas', 'rejeitada' => 'Rejeitadas', 'cancelada' => 'Canceladas', 'processando' => 'Processando'];
      foreach ($mapa as $k => $rot):
          $linha = null;
          foreach ($resumo as $r) { if ($r['status'] === $k) { $linha = $r; break; } }
      ?>
        <div class="col-6 col-md-3 mb-2">
          <div class="kpi">
            <div class="n"><?= (int) ($linha['c'] ?? 0) ?></div>
            <div class="l"><?= $rot ?></div>
            <?php if ($k === 'autorizada'): ?>
              <div class="l mt-1">ISS <?= $brl($linha['iss'] ?? 0) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="get" class="form-row mb-3">
      <div class="col-md-5 mb-2">
        <input type="text" name="q" class="form-control" placeholder="Chave de acesso, nº da O.S. ou tomador" value="<?= htmlspecialchars($busca) ?>">
      </div>
      <div class="col-md-3 mb-2">
        <select name="status" class="form-control">
          <option value="">Todos os status</option>
          <?php foreach ($mapa as $k => $rot): ?>
            <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $rot ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 mb-2">
        <button class="btn btn-primary btn-block"><i class="fa fa-search"></i> Filtrar</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-striped table-bordered table-sm">
        <thead>
          <tr>
            <th>#</th><th>O.S.</th><th>Amb.</th><th>Série/DPS</th><th>Chave / Nº NFS-e</th>
            <th>Tomador</th><th class="text-right">Serviço</th><th class="text-right">Base</th>
            <th class="text-right">ISS</th><th>Status</th><th>Emitida em</th><th>Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$notas): ?>
          <tr><td colspan="12" class="text-center text-muted py-4">Nenhuma NFS-e registrada.</td></tr>
        <?php endif; ?>
        <?php foreach ($notas as $n): ?>
          <tr>
            <td><?= (int) $n['id'] ?></td>
            <td><a href="../visualizar_os.php?id=<?= (int) $n['ordem_servico_id'] ?>"><?= (int) $n['ordem_servico_id'] ?></a></td>
            <td><?= $n['ambiente'] === '1' ? 'Prod.' : 'Homol.' ?></td>
            <td><?= htmlspecialchars($n['serie']) ?>/<?= (int) $n['numero_dps'] ?></td>
            <td>
              <?php if ($n['chave_acesso']): ?>
                <span class="chave"><?= htmlspecialchars($n['chave_acesso']) ?></span>
                <?php if ($n['numero_nfse']): ?><br><small>Nº <?= htmlspecialchars($n['numero_nfse']) ?></small><?php endif; ?>
              <?php else: ?>
                <small class="text-muted">—</small>
              <?php endif; ?>
              <?php if ($n['status'] === 'rejeitada' && $n['mensagem']): ?>
                <span class="msg"><?= htmlspecialchars(mb_substr($n['mensagem'], 0, 220)) ?></span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($n['tomador_nome'] ?: 'Não informado') ?></td>
            <td class="text-right"><?= $brl($n['valor_servico']) ?></td>
            <td class="text-right"><?= $brl($n['base_calculo']) ?></td>
            <td class="text-right"><?= $brl($n['valor_iss']) ?></td>
            <td><?= $badge($n['status']) ?></td>
            <td><small><?= $n['criado_em'] ? date('d/m/Y H:i', strtotime($n['criado_em'])) : '—' ?></small></td>
            <td class="text-nowrap">
              <?php if (in_array($n['status'], ['autorizada', 'cancelada'], true) && $n['chave_acesso']): ?>
                <a class="btn btn-outline-primary btn-sm" title="DANFSe (PDF)" target="_blank" href="nfse_danfse.php?nota_id=<?= (int) $n['id'] ?>"><i class="fa fa-file-pdf-o"></i></a>
                <a class="btn btn-outline-success btn-sm" title="Recibo (impressora térmica)" target="_blank" href="nfse_recibo.php?nota_id=<?= (int) $n['id'] ?>"><i class="fa fa-print"></i></a>
              <?php endif; ?>
              <?php if ($n['xml_nfse']): ?>
                <a class="btn btn-outline-secondary btn-sm" title="Baixar XML" href="nfse_xml.php?nota_id=<?= (int) $n['id'] ?>"><i class="fa fa-file-code-o"></i></a>
              <?php endif; ?>
              <button class="btn btn-outline-info btn-sm" title="Sincronizar" onclick="sincronizar(<?= (int) $n['id'] ?>)"><i class="fa fa-refresh"></i></button>
              <?php if ($n['status'] === 'autorizada'): ?>
                <button class="btn btn-outline-danger btn-sm" title="Cancelar" onclick="cancelar(<?= (int) $n['id'] ?>)"><i class="fa fa-ban"></i></button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php $paginas = (int) ceil($total / $porPagina); if ($paginas > 1): ?>
      <nav><ul class="pagination pagination-sm">
        <?php for ($i = 1; $i <= $paginas; $i++): ?>
          <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
            <a class="page-link" href="?p=<?= $i ?>&status=<?= urlencode($status) ?>&q=<?= urlencode($busca) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul></nav>
    <?php endif; ?>
  </div>
</div>

<script src="../../script/jquery-3.5.1.min.js"></script>
<script src="../../script/bootstrap.bundle.min.js"></script>
<script src="../../script/sweetalert2.js"></script>
<script>
function sincronizar(id) {
    Swal.fire({ title: 'Consultando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('nfse_consultar.php?nota_id=' + id)
        .then(r => r.json())
        .then(res => Swal.fire({ icon: res.ok ? 'success' : 'error', title: res.ok ? 'Sincronizada' : 'Falha', text: res.mensagem })
            .then(() => { if (res.ok) location.reload(); }));
}

function cancelar(id) {
    Swal.fire({
        title: 'Cancelar NFS-e',
        html: `
            <select id="cMotivo" class="swal2-select" style="width:90%">
                <option value="1">1 — Erro na emissão</option>
                <option value="2">2 — Serviço não prestado</option>
                <option value="9">9 — Outros</option>
            </select>
            <textarea id="xMotivo" class="swal2-textarea" placeholder="Justificativa (obrigatória para 'Outros')"></textarea>
            <div style="font-size:.78rem;color:#64748b;text-align:left;padding:0 12px">
                Fora do prazo de cancelamento direto do município, o Ambiente Nacional exige análise fiscal.
            </div>`,
        showCancelButton: true,
        confirmButtonText: 'Cancelar a nota',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#dc3545',
        preConfirm: () => {
            const c = document.getElementById('cMotivo').value;
            const x = document.getElementById('xMotivo').value.trim();
            if (c === '9' && !x) { Swal.showValidationMessage('Justificativa obrigatória para "Outros".'); return false; }
            return { c, x };
        }
    }).then(r => {
        if (!r.isConfirmed) return;
        fetch('nfse_cancelar.php', {
            method: 'POST',
            body: new URLSearchParams({ nota_id: id, c_motivo: r.value.c, x_motivo: r.value.x })
        })
            .then(r => r.json())
            .then(res => Swal.fire({ icon: res.ok ? 'success' : 'error', title: res.ok ? 'Cancelada' : 'Falha', text: res.mensagem })
                .then(() => { if (res.ok) location.reload(); }));
    });
}
</script>
</body>
</html>
