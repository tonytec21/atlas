<?php
/**
 * ATLAS-NFSE-BUILD: 2026-07-31-reemissao-em-lote
 * (base: 2026-07-09-integracao-emissor-nacional)
 */
include(__DIR__ . '/../session_check.php');
checkSession();
include(__DIR__ . '/../../checar_acesso_de_administrador.php');

require_once __DIR__ . '/nfse_lib.php';
require_once __DIR__ . '/nfse_reemissao_lib.php';
nfse_migrar();
nfse_reemissao_migrar();

$pdo = nfse_pdo();

$cfgAtual  = nfse_config();
$ambAtual  = (string) ($cfgAtual['ambiente'] ?? '2');

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
if ($status === 'reemitir') {
    // Somente o que ainda aguarda nova tentativa.
    $where[] = "status = 'rejeitada' AND reemitida_em IS NULL AND ambiente = :ambf
                AND NOT EXISTS (SELECT 1 FROM nfse_notas v
                                 WHERE v.ordem_servico_id = nfse_notas.ordem_servico_id
                                   AND v.ambiente = nfse_notas.ambiente
                                   AND v.status IN ('autorizada','processando','cancelada'))";
    $params[':ambf'] = $ambAtual;
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

/* Fila de reemissão (O.S. distintas com rejeição ainda em aberto). */
$totalReemitir = nfse_reemissao_total($ambAtual);

/* Para cada O.S. exibida nesta página, saber se já existe nota válida —
   evita oferecer "tentar novamente" onde não há mais o que emitir. */
$osComNotaValida = [];
$osDaPagina = array_values(array_unique(array_map(static fn($n) => (int) $n['ordem_servico_id'], $notas)));
if ($osDaPagina) {
    $in = implode(',', $osDaPagina);
    $rs = $pdo->query(
        "SELECT DISTINCT ordem_servico_id FROM nfse_notas
          WHERE ordem_servico_id IN ($in)
            AND status IN ('autorizada','processando','cancelada')"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($rs as $osid) {
        $osComNotaValida[(int) $osid] = true;
    }
}

/** Esta linha rejeitada ainda comporta nova tentativa? */
$podeReemitir = static function (array $n) use ($osComNotaValida, $ambAtual): bool {
    return $n['status'] === 'rejeitada'
        && empty($n['reemitida_em'])
        && (string) $n['ambiente'] === $ambAtual
        && empty($osComNotaValida[(int) $n['ordem_servico_id']]);
};

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
$esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
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
        .kpi.fila{border-color:#fdba74;background:#fff7ed}
        .kpi.fila .n{color:#c2410c}
        .chave{font-family:monospace;font-size:.72rem;word-break:break-all}
        td .msg{font-size:.75rem;color:#b91c1c;max-width:280px;display:block}
        .btn-erro{border:0;background:none;padding:0;color:#b91c1c;font-size:.72rem;text-decoration:underline;cursor:pointer}
        .tag-reemitida{display:inline-block;background:#e0f2fe;color:#075985;border-radius:999px;
                       padding:1px 8px;font-size:.68rem;font-weight:700;margin-top:3px}
        #lotebox{text-align:left;font-size:.82rem;max-height:230px;overflow:auto;border:1px solid #e2e8f0;
                 border-radius:8px;padding:8px;margin-top:10px}
        #lotebox div{padding:2px 0;border-bottom:1px dashed #eef2f7}
        .progresso{height:10px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-top:12px}
        .progresso i{display:block;height:100%;background:#0f766e;width:0;transition:width .25s}
    </style>
</head>
<body>
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
      <h3 class="m-0">NFS-e emitidas</h3>
      <div>
        <?php if ($totalReemitir > 0): ?>
          <button class="btn btn-warning btn-sm" onclick="reemitirTodas()">
            <i class="fa fa-refresh"></i> Reemitir rejeitadas (<?= (int) $totalReemitir ?>)
          </button>
        <?php endif; ?>
        <a href="nfse_config.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-cog"></i> Configuração</a>
      </div>
    </div>
    <hr>

    <div class="row mb-4">
      <?php
      $mapa = ['autorizada' => 'Autorizadas', 'rejeitada' => 'Rejeitadas', 'cancelada' => 'Canceladas', 'processando' => 'Processando'];
      foreach ($mapa as $k => $rot):
          $linha = null;
          foreach ($resumo as $r) { if ($r['status'] === $k) { $linha = $r; break; } }
      ?>
        <div class="col-6 col-md-3 col-lg-2 mb-2">
          <div class="kpi">
            <div class="n"><?= (int) ($linha['c'] ?? 0) ?></div>
            <div class="l"><?= $rot ?></div>
            <?php if ($k === 'autorizada'): ?>
              <div class="l mt-1">ISS <?= $brl($linha['iss'] ?? 0) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="col-6 col-md-3 col-lg-2 mb-2">
        <a href="?status=reemitir" style="text-decoration:none">
          <div class="kpi fila">
            <div class="n"><?= (int) $totalReemitir ?></div>
            <div class="l">A reemitir</div>
            <div class="l mt-1">O.S. na fila</div>
          </div>
        </a>
      </div>
    </div>

    <form method="get" class="form-row mb-3">
      <div class="col-md-5 mb-2">
        <input type="text" name="q" class="form-control" placeholder="Chave de acesso, nº da O.S. ou tomador" value="<?= $esc($busca) ?>">
      </div>
      <div class="col-md-3 mb-2">
        <select name="status" class="form-control">
          <option value="">Todos os status</option>
          <?php foreach ($mapa as $k => $rot): ?>
            <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $rot ?></option>
          <?php endforeach; ?>
          <option value="reemitir" <?= $status === 'reemitir' ? 'selected' : '' ?>>Aguardando reemissão</option>
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
            <td><?= $esc($n['serie']) ?>/<?= (int) $n['numero_dps'] ?></td>
            <td>
              <?php if ($n['chave_acesso']): ?>
                <span class="chave"><?= $esc($n['chave_acesso']) ?></span>
                <?php if ($n['numero_nfse']): ?><br><small>Nº <?= $esc($n['numero_nfse']) ?></small><?php endif; ?>
              <?php else: ?>
                <small class="text-muted">—</small>
              <?php endif; ?>

              <?php if ($n['status'] === 'rejeitada' && $n['mensagem']): ?>
                <span class="msg"><?= $esc(mb_substr((string) $n['mensagem'], 0, 220)) ?></span>
                <button type="button" class="btn-erro" data-msg="<?= $esc($n['mensagem']) ?>"
                        onclick="verErro(this)">ver erro completo</button>
              <?php endif; ?>

              <?php if (!empty($n['reemitida_em'])): ?>
                <span class="tag-reemitida">
                  <i class="fa fa-check"></i> Reemitida
                  <?= !empty($n['reemitida_nota_id']) ? ' &rarr; nota #' . (int) $n['reemitida_nota_id'] : '' ?>
                </span>
              <?php endif; ?>
            </td>
            <td><?= $esc($n['tomador_nome'] ?: 'Não informado') ?></td>
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

              <?php if ($podeReemitir($n)): ?>
                <button class="btn btn-warning btn-sm" title="Tentar emitir novamente"
                        onclick="reemitirUma(<?= (int) $n['ordem_servico_id'] ?>)">
                  <i class="fa fa-paper-plane"></i>
                </button>
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
const BRL = v => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function verErro(btn) {
    Swal.fire({
        icon: 'error',
        title: 'Retorno do Ambiente Nacional',
        html: '<pre style="text-align:left;white-space:pre-wrap;word-break:break-word;font-size:.78rem;' +
              'max-height:340px;overflow:auto;background:#f8fafc;padding:10px;border-radius:8px">' +
              (btn.dataset.msg || '').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '</pre>',
        width: 760,
        confirmButtonText: 'Fechar'
    });
}

function sincronizar(id) {
    Swal.fire({ title: 'Consultando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('nfse_consultar.php?nota_id=' + id)
        .then(r => r.json())
        .then(res => Swal.fire({ icon: res.ok ? 'success' : 'error', title: res.ok ? 'Sincronizada' : 'Falha', text: res.mensagem })
            .then(() => { if (res.ok) location.reload(); }));
}

/* ------------------------------------------------------------------ *
 * Nova tentativa — uma O.S.
 * ------------------------------------------------------------------ */
function reemitirUma(osId) {
    Swal.fire({
        icon: 'question',
        title: 'Tentar emitir novamente?',
        html: 'Uma nova DPS será gerada e transmitida ao Ambiente Nacional para a ' +
              '<b>O.S. ' + osId + '</b>.<br><small class="text-muted">A DPS rejeitada anterior não gerou ' +
              'NFS-e, por isso a reemissão não duplica nada.</small>',
        showCancelButton: true,
        confirmButtonText: 'Sim, emitir',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#0f766e'
    }).then(r => {
        if (!r.isConfirmed) return;

        Swal.fire({ title: 'Transmitindo ao Ambiente Nacional...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        fetch('nfse_reemitir.php', {
            method: 'POST',
            body: new URLSearchParams({ acao: 'emitir', os_id: osId })
        })
            .then(r => r.json())
            .then(res => Swal.fire({
                icon: res.ok ? 'success' : 'error',
                title: res.ok ? 'NFS-e emitida' : 'Ainda não foi possível emitir',
                text: res.mensagem
            }).then(() => { if (res.ok) location.reload(); }))
            .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha de comunicação com o servidor.' }));
    });
}

/* ------------------------------------------------------------------ *
 * Nova tentativa — lote
 * O laço é feito aqui, uma O.S. por requisição, para não estourar o
 * tempo de execução do PHP e para o operador acompanhar o andamento.
 * ------------------------------------------------------------------ */
async function reemitirTodas() {
    Swal.fire({ title: 'Montando a fila...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    let fila;
    try {
        fila = await fetch('nfse_reemitir.php?acao=listar').then(r => r.json());
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha de comunicação com o servidor.' });
        return;
    }

    if (!fila.ok) {
        Swal.fire({ icon: 'error', title: 'Erro', text: fila.mensagem || 'Não foi possível montar a fila.' });
        return;
    }
    if (!fila.total) {
        Swal.fire({ icon: 'info', title: 'Nada a reemitir', text: 'Não há rejeições pendentes no ambiente atual.' });
        return;
    }

    const linhas = fila.itens.map(i =>
        '<div>O.S. <b>' + i.os_id + '</b> — ' + (i.tomador_nome || 'Tomador não informado') +
        ' — ' + BRL(i.valor_servico) + '</div>').join('');

    const conf = await Swal.fire({
        icon: 'question',
        title: 'Reemitir ' + fila.total + ' NFS-e?',
        html: 'Cada O.S. abaixo receberá uma <b>nova DPS</b>, transmitida em sequência ao ' +
              'Ambiente Nacional.<div id="lotebox">' + linhas + '</div>',
        width: 720,
        showCancelButton: true,
        confirmButtonText: 'Emitir todas',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#0f766e'
    });
    if (!conf.isConfirmed) return;

    let ok = 0, falha = 0;
    const erros = [];

    Swal.fire({
        title: 'Emitindo...',
        html: '<div id="lotestat">Preparando…</div><div class="progresso"><i id="lotebar"></i></div>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false
    });

    for (let k = 0; k < fila.itens.length; k++) {
        const item = fila.itens[k];

        const stat = document.getElementById('lotestat');
        if (stat) {
            stat.innerHTML = 'O.S. <b>' + item.os_id + '</b> (' + (k + 1) + ' de ' + fila.total + ')' +
                             '<br><small>' + ok + ' emitida(s) · ' + falha + ' com falha</small>';
        }
        const bar = document.getElementById('lotebar');
        if (bar) bar.style.width = Math.round((k / fila.total) * 100) + '%';

        try {
            const res = await fetch('nfse_reemitir.php', {
                method: 'POST',
                body: new URLSearchParams({ acao: 'emitir', os_id: item.os_id })
            }).then(r => r.json());

            if (res.ok) { ok++; } else { falha++; erros.push('O.S. ' + item.os_id + ': ' + (res.mensagem || 'falha')); }
        } catch (e) {
            falha++;
            erros.push('O.S. ' + item.os_id + ': falha de comunicação.');
        }
    }

    const bar = document.getElementById('lotebar');
    if (bar) bar.style.width = '100%';

    await Swal.fire({
        icon: falha === 0 ? 'success' : (ok === 0 ? 'error' : 'warning'),
        title: 'Concluído',
        html: '<b>' + ok + '</b> emitida(s) com sucesso.<br><b>' + falha + '</b> ainda com falha.' +
              (erros.length ? '<div id="lotebox">' + erros.map(e =>
                    '<div>' + e.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '</div>').join('') + '</div>' : ''),
        width: 720,
        confirmButtonText: 'Fechar'
    });

    location.reload();
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
