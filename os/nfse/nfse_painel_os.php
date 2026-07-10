<?php
/**
 * ATLAS-NFSE-BUILD: 2026-07-09-integracao-emissor-nacional
 *
 * Painel da NFS-e para embutir em visualizar_os.php.
 * Espera a variável $os_id já definida. Nunca quebra a página hospedeira.
 *
 * Uso:
 *   <?php $__nfse_os_id = $os_id; include(__DIR__ . '/nfse/nfse_painel_os.php'); ?>
 */

$__nfse_os_id = (int) ($__nfse_os_id ?? ($os_id ?? 0));
if ($__nfse_os_id <= 0) {
    return;
}

$__nfse_erro  = null;
$__nfse_cfg   = [];
$__nfse_notas = [];
$__nfse_apur  = null;

try {
    require_once __DIR__ . '/nfse_lib.php';
    $__nfse_cfg = nfse_config();

    if (empty($__nfse_cfg['ativo'])) {
        return; // integração desligada: não polui a tela
    }

    $__nfse_notas = nfse_notas_da_os($__nfse_os_id);
    $__nfse_apur  = nfse_apurar_os($__nfse_os_id, $__nfse_cfg);
} catch (Throwable $e) {
    error_log('[nfse_painel_os] ' . $e->getMessage());
    $__nfse_erro = $e->getMessage();
}

$__nfse_ativa = null;
foreach ($__nfse_notas as $__n) {
    if (in_array($__n['status'], ['autorizada', 'processando'], true)) {
        $__nfse_ativa = $__n;
        break;
    }
}

$__brl = static fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$__esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<style>
  .nfse-box{border:1px solid #e2e8f0;border-radius:12px;background:#fff;margin:24px 0;overflow:hidden}
  .nfse-box .hd{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;
                padding:12px 18px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
  .nfse-box .hd h5{margin:0;font-size:.95rem;font-weight:700;color:#0f172a}
  .nfse-box .hd h5 i{color:#0f766e;margin-right:8px}
  .nfse-box .bd{padding:16px 18px}
  .nfse-tag{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:700}
  .nfse-tag.ok{background:#dcfce7;color:#166534}
  .nfse-tag.no{background:#fee2e2;color:#991b1b}
  .nfse-tag.wa{background:#fef3c7;color:#92400e}
  .nfse-tag.gr{background:#e2e8f0;color:#334155}
  .nfse-chave{font-family:monospace;font-size:.74rem;word-break:break-all;color:#334155}
  .nfse-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}
  .nfse-grid div span{display:block;font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.03em}
  .nfse-grid div b{font-size:.95rem;color:#0f172a}
</style>

<div class="nfse-box">
  <div class="hd">
    <h5><i class="fa fa-file-text-o"></i> NFS-e Nacional
      <span class="nfse-tag <?= ($__nfse_cfg['ambiente'] ?? '2') === '1' ? 'no' : 'wa' ?>">
        <?= ($__nfse_cfg['ambiente'] ?? '2') === '1' ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO' ?>
      </span>
    </h5>
    <div>
      <?php if ($__nfse_ativa && $__nfse_ativa['status'] === 'autorizada'): ?>
        <a class="btn btn-outline-secondary btn-sm" href="nfse/nfse_xml.php?nota_id=<?= (int) $__nfse_ativa['id'] ?>">
          <i class="fa fa-download"></i> XML
        </a>
        <button class="btn btn-outline-info btn-sm" onclick="nfseSincronizar(<?= (int) $__nfse_ativa['id'] ?>)">
          <i class="fa fa-refresh"></i> Sincronizar
        </button>
      <?php elseif (!$__nfse_erro && $__nfse_apur && $__nfse_apur['totalmente_liquidada'] && $__nfse_apur['valor_servico'] > 0): ?>
        <button class="btn btn-success btn-sm" onclick="nfseEmitir(<?= $__nfse_os_id ?>)">
          <i class="fa fa-paper-plane"></i> Emitir NFS-e
        </button>
      <?php endif; ?>
      <a class="btn btn-outline-secondary btn-sm" href="nfse/nfse_notas.php"><i class="fa fa-list"></i></a>
    </div>
  </div>

  <div class="bd">
    <?php if ($__nfse_erro): ?>
      <div class="alert alert-danger mb-0" style="font-size:.85rem">
        <b>Integração indisponível:</b> <?= $__esc($__nfse_erro) ?>
      </div>

    <?php elseif ($__nfse_ativa): ?>
      <div class="d-flex align-items-center flex-wrap mb-3" style="gap:10px">
        <span class="nfse-tag <?= $__nfse_ativa['status'] === 'autorizada' ? 'ok' : 'wa' ?>">
          <?= strtoupper($__nfse_ativa['status']) ?>
        </span>
        <span class="nfse-chave"><?= $__esc($__nfse_ativa['chave_acesso'] ?: 'Aguardando chave de acesso') ?></span>
      </div>
      <div class="nfse-grid">
        <div><span>Valor do serviço</span><b><?= $__brl($__nfse_ativa['valor_servico']) ?></b></div>
        <div><span>Base de cálculo</span><b><?= $__brl($__nfse_ativa['base_calculo']) ?></b></div>
        <div><span>Alíquota</span><b><?= number_format((float) $__nfse_ativa['aliquota'], 2, ',', '.') ?>%</b></div>
        <div><span>ISSQN</span><b><?= $__brl($__nfse_ativa['valor_iss']) ?></b></div>
        <div><span>DPS</span><b><?= $__esc($__nfse_ativa['serie']) ?>/<?= (int) $__nfse_ativa['numero_dps'] ?></b></div>
      </div>

    <?php elseif ($__nfse_apur && !$__nfse_apur['totalmente_liquidada']): ?>
      <p class="mb-0 text-muted" style="font-size:.87rem">
        <i class="fa fa-info-circle"></i>
        A NFS-e é emitida na <b>liquidação</b> dos atos — o depósito prévio é adiantamento e não constitui fato gerador.
        Restam <b><?= (int) $__nfse_apur['itens_pendentes'] ?></b> item(ns) a liquidar.
      </p>

    <?php elseif ($__nfse_apur && $__nfse_apur['valor_servico'] <= 0): ?>
      <p class="mb-0 text-muted" style="font-size:.87rem">
        <i class="fa fa-info-circle"></i> O.S. sem valor tributável (ato gratuito ou isento). Nada a declarar.
      </p>

    <?php elseif ($__nfse_apur): ?>
      <p class="text-muted mb-3" style="font-size:.85rem">
        Atos liquidados. Prévia do que será declarado
        (<?= ($__nfse_cfg['modo_emissao'] === 'individualizado' || nfse_exige_individualizacao())
              ? 'uma nota por ato' : 'nota consolidada' ?>):
      </p>
      <div class="nfse-grid">
        <div><span>Emolumentos</span><b><?= $__brl($__nfse_apur['emolumentos']) ?></b></div>
        <div><span>Taxas e fundos</span><b><?= $__brl($__nfse_apur['taxas']) ?></b></div>
        <div><span>Valor do serviço</span><b><?= $__brl($__nfse_apur['valor_servico']) ?></b></div>
        <div><span>Redução (<?= number_format((float) $__nfse_apur['p_reducao'], 2, ',', '.') ?>%)</span><b>−<?= $__brl($__nfse_apur['valor_reducao']) ?></b></div>
        <div><span>Base de cálculo</span><b><?= $__brl($__nfse_apur['base_calculo']) ?></b></div>
        <div><span>ISSQN (<?= number_format((float) $__nfse_apur['aliquota'], 2, ',', '.') ?>%)</span><b><?= $__brl($__nfse_apur['valor_iss']) ?></b></div>
      </div>
    <?php endif; ?>

    <?php
    $__rejeitadas = array_filter($__nfse_notas, static fn($n) => $n['status'] === 'rejeitada');
    if ($__rejeitadas):
        $__ultima = reset($__rejeitadas);
    ?>
      <div class="alert alert-danger mt-3 mb-0" style="font-size:.8rem">
        <b>Última tentativa rejeitada (DPS <?= (int) $__ultima['numero_dps'] ?>):</b><br>
        <?= $__esc(mb_substr((string) $__ultima['mensagem'], 0, 600)) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function nfseEmitir(osId, forcar) {
    Swal.fire({
        icon: 'question',
        title: 'Emitir NFS-e?',
        text: 'A declaração será enviada ao Ambiente Nacional em nome da serventia.',
        showCancelButton: true,
        confirmButtonText: 'Sim, emitir',
        cancelButtonText: 'Cancelar'
    }).then(r => {
        if (!r.isConfirmed) return;

        Swal.fire({ title: 'Transmitindo ao Ambiente Nacional...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        fetch('nfse/nfse_emitir.php', {
            method: 'POST',
            body: new URLSearchParams({ os_id: osId, forcar: forcar ? '1' : '0' })
        })
            .then(r => r.json())
            .then(res => {
                Swal.fire({
                    icon: res.ok ? 'success' : 'error',
                    title: res.ok ? 'NFS-e emitida' : 'Não foi possível emitir',
                    text: res.mensagem
                }).then(() => { if (res.ok) location.reload(); });
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha de comunicação com o servidor.' }));
    });
}

function nfseSincronizar(notaId) {
    Swal.fire({ title: 'Consultando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('nfse/nfse_consultar.php?nota_id=' + notaId)
        .then(r => r.json())
        .then(res => Swal.fire({ icon: res.ok ? 'success' : 'error', title: res.ok ? 'Sincronizada' : 'Falha', text: res.mensagem })
            .then(() => { if (res.ok) location.reload(); }));
}
</script>
