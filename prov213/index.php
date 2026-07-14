<?php
/** ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213 — Painel */
require_once __DIR__ . '/p213_lib.php';

$cfg    = p213_config();
$classe = (int)$cfg['classe'];
$score  = p213_score($classe);
$par    = p213_parametros($classe);
$prz    = p213_prazos($classe);
$plano  = p213_plano_acao($classe);
$etapas = p213_etapas();

function cls_prazo($d) { return $d < 0 ? 'bad' : ($d <= 30 ? 'warn' : 'ok'); }
function cls_etapa($d) {
    if (!$d['liberada'])      return 'locked';
    if ($d['apto_declarar'])  return 'ok';
    if ($d['pct'] >= 60)      return 'warn';
    return 'bad';
}

p213_head('Conformidade — Provimento 213');
p213_hero('Conformidade — Provimento CN-CNJ n. 213/2026',
    'Padrões mínimos de TIC dos serviços notariais e de registro &middot; vigência desde 23/02/2026');
p213_nav('index.php');
?>

<?php if (trim($cfg['serventia']) === ''): ?>
  <div class="p213-alert warn">
    <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
    <div>A serventia ainda não foi identificada. <a href="configuracao.php">Preencha a configuração</a>
      para que o enquadramento por classe e os prazos sejam calculados corretamente.</div>
  </div>
<?php endif; ?>

<div class="p213-split">

  <!-- ───────────────────────────── coluna esquerda -->
  <div>
    <div class="p213-card" style="margin-bottom:18px">
      <div class="p213-card__head"><h2 class="p213-card__title"><i class="fa fa-pie-chart"></i> Aderência global</h2></div>
      <div class="p213-card__body">
        <?= p213_ring($score['geral']) ?>
        <p class="p213-hint" style="text-align:center;margin:14px 0 0">
          <?= $score['itens'] ?> requisitos aplicáveis à Classe <?= $classe ?><br>
          Ponderação por criticidade &middot; itens não aplicáveis excluídos do denominador
        </p>
        <dl style="margin:18px 0 0">
          <div class="p213-kv"><dt>Serventia</dt><dd class="sm"><?= p213_esc($cfg['serventia'] ?: '—') ?></dd></div>
          <div class="p213-kv"><dt>CNS</dt><dd><?= p213_esc($cfg['cns'] ?: '—') ?></dd></div>
          <div class="p213-kv"><dt>Enquadramento</dt>
            <dd>Classe <?= $classe ?> &middot; <?= p213_esc($cfg['subclasse']) ?></dd></div>
        </dl>
      </div>
    </div>

    <div class="p213-card">
      <div class="p213-card__head">
        <h2 class="p213-card__title"><i class="fa fa-sliders"></i> Parâmetros da Classe <?= $classe ?></h2>
      </div>
      <div class="p213-card__body">
        <dl style="margin:0">
          <div class="p213-kv"><dt>RPO máximo</dt><dd><?= $par['rpo'] ?></dd></div>
          <div class="p213-kv"><dt>RTO máximo</dt><dd><?= $par['rto'] ?></dd></div>
          <div class="p213-kv"><dt>Backup completo</dt><dd><?= $par['backup_full'] ?></dd></div>
          <div class="p213-kv"><dt>Link de referência</dt><dd><?= $par['link'] ?></dd></div>
          <div class="p213-kv"><dt>Teste de restauração</dt><dd><?= $par['teste_restauracao'] ?></dd></div>
          <div class="p213-kv"><dt>Trilha de auditoria</dt><dd>nível <?= $par['trilha'] ?></dd></div>
          <div class="p213-kv"><dt>Retenção de trilhas</dt><dd>5 anos</dd></div>
          <div class="p213-kv"><dt>Pentest</dt><dd class="sm"><?= $par['pentest'] ?></dd></div>
          <div class="p213-kv"><dt>Extração integral</dt><dd class="sm"><?= $par['extracao'] ?></dd></div>
          <div class="p213-kv"><dt>Encarregado (DPO)</dt><dd class="sm"><?= $par['dpo'] ?></dd></div>
          <div class="p213-kv"><dt>Comprovação</dt><dd class="sm"><?= $par['comprovacao'] ?></dd></div>
        </dl>
      </div>
    </div>
  </div>

  <!-- ───────────────────────────── coluna direita -->
  <div>
    <div class="p213-grid g2" style="margin-bottom:18px">
      <div class="p213-deadline <?= cls_prazo($prz['inicial_dias']) ?>">
        <div class="lbl">Etapas 1 e 2 — implementação inicial obrigatória (art. 20)</div>
        <div class="num"><?= $prz['inicial_dias'] >= 0 ? $prz['inicial_dias'] . ' dias' : 'Vencido' ?></div>
        <div class="lbl">Vence em <?= $prz['inicial']->format('d/m/Y') ?>
          &middot; <?= $prz['dias_norma'] ?> dias da vigência</div>
      </div>
      <div class="p213-deadline <?= cls_prazo($prz['global_dias']) ?>">
        <div class="lbl">Etapas 1 a 5 — implementação integral (art. 23)</div>
        <div class="num"><?= $prz['global_dias'] >= 0 ? $prz['global_dias'] . ' dias' : 'Vencido' ?></div>
        <div class="lbl">Vence em <?= $prz['global']->format('d/m/Y') ?>
          &middot; <?= $prz['meses_norma'] ?> meses da vigência</div>
      </div>
    </div>

    <?php if ($prz['inicial_dias'] < 60): ?>
      <div class="p213-alert info">
        <i class="fa fa-info-circle"></i>
        <div><strong>Prorrogação excepcional (art. 21).</strong> A Corregedoria pode prorrogar
          <em>uma única vez</em> o prazo do art. 20 por até 90 dias (limite:
          <?= $prz['prorrogado']->format('d/m/Y') ?>), mediante plano formal de adequação com cronograma e
          responsáveis, e adoção imediata de medidas compensatórias. O requerimento deve ser apresentado
          <strong>antes</strong> do vencimento.</div>
      </div>
    <?php endif; ?>

    <div class="p213-card" style="margin-bottom:18px">
      <div class="p213-card__head">
        <h2 class="p213-card__title"><i class="fa fa-list-ol"></i> Progresso por etapa</h2>
        <a href="diagnostico.php" class="p213-btn p213-btn--pri p213-btn--sm">
          <i class="fa fa-pencil-square-o"></i> Responder diagnóstico</a>
      </div>
      <div class="p213-card__body">
        <div class="p213-steps">
        <?php foreach ($etapas as $n => $nome):
              $d  = $score['etapas'][$n];
              $cl = cls_etapa($d);
              $bar = $d['apto_declarar'] ? 'ok' : ($d['pct'] >= 60 ? 'warn' : 'bad');
        ?>
          <div class="p213-step <?= $cl ?>">
            <div class="p213-step__dot">
              <?php if ($cl === 'ok'): ?><i class="fa fa-check"></i>
              <?php elseif ($cl === 'locked'): ?><i class="fa fa-lock"></i>
              <?php else: ?><?= $n ?><?php endif; ?>
            </div>
            <div class="p213-step__box">
              <div class="p213-step__top">
                <div style="min-width:0">
                  <h3 class="p213-step__name">Etapa <?= $n ?> — <?= p213_esc($nome) ?></h3>
                  <div class="p213-step__meta">
                    <?= $d['conforme'] ?>/<?= $d['total'] ?> conformes<?php
                      if ($d['parcial'])      echo ' &middot; ' . $d['parcial'] . ' parciais';
                      if ($d['nao_conforme']) echo ' &middot; ' . $d['nao_conforme'] . ' não conformes';
                      if ($d['nao_avaliado']) echo ' &middot; ' . $d['nao_avaliado'] . ' não avaliados';
                      if ($d['nao_aplicavel']) echo ' &middot; ' . $d['nao_aplicavel'] . ' N/A';
                    ?>
                  </div>
                </div>
                <div class="p213-step__pct"><?= number_format($d['pct'], 0) ?>%</div>
              </div>
              <div class="p213-bar"><i class="<?= $bar ?>" style="width:<?= $d['pct'] ?>%"></i></div>
              <?php if ($cl === 'ok'): ?>
                <button class="p213-btn p213-btn--ok p213-btn--sm" style="margin-top:11px"
                        onclick="declarar(<?= $n ?>)">
                  <i class="fa fa-gavel"></i> Declarar conclusão no Justiça Aberta</button>
              <?php elseif ($cl === 'locked'): ?>
                <div class="p213-hint" style="margin-top:9px">
                  <i class="fa fa-lock"></i> Etapas são sucessivas e cumulativas — conclua a etapa anterior.</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="p213-card">
      <div class="p213-card__head">
        <h2 class="p213-card__title"><i class="fa fa-tasks"></i> Próximas ações
          <span class="p213-tag info"><?= count($plano) ?> pendências</span></h2>
        <a href="relatorio.php" class="p213-btn p213-btn--ghost p213-btn--sm">
          <i class="fa fa-file-pdf-o"></i> Plano completo</a>
      </div>
      <div class="p213-card__body flush">
        <?php if (!$plano): ?>
          <div class="p213-empty">
            <i class="fa fa-check-circle" style="color:var(--ok);opacity:.8"></i>
            <p>Nenhuma pendência. Mantenha as evidências pelo prazo mínimo de 5 anos.</p>
          </div>
        <?php else: ?>
          <div class="p213-tablewrap">
            <table class="p213-table">
              <thead><tr>
                <th style="width:84px">Item</th><th style="width:64px">Etapa</th>
                <th>Requisito</th><th style="width:104px">Criticidade</th><th style="width:150px">Situação</th>
              </tr></thead>
              <tbody>
              <?php foreach (array_slice($plano, 0, 10) as $p): ?>
                <tr>
                  <td><span class="p213-code"><?= p213_esc($p['cod']) ?></span></td>
                  <td class="num"><?= $p['etapa'] ?></td>
                  <td>
                    <?= p213_esc(mb_substr($p['pergunta'], 0, 110)) ?><?= mb_strlen($p['pergunta']) > 110 ? '…' : '' ?>
                    <div class="p213-q__base"><i class="fa fa-book"></i> <?= p213_esc($p['base']) ?></div>
                  </td>
                  <td><span class="p213-tag c<?= $p['peso'] ?>"><?= p213_criticidade($p['peso']) ?></span></td>
                  <td><span class="p213-pill <?= $p['status'] ?>"><?= p213_status_label($p['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (count($plano) > 10): ?>
            <div style="padding:12px;text-align:center">
              <a href="diagnostico.php" class="p213-btn p213-btn--ghost p213-btn--sm">
                Ver as outras <?= count($plano) - 10 ?> pendências <i class="fa fa-arrow-right"></i></a>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$js = <<<'JS'
function declarar(etapa){
  Swal.fire({
    title:'Declaração de conclusão — Etapa '+etapa,
    html:'<p style="font-size:.85rem;text-align:left;color:#4b5565">A declaração é firmada pelo titular da '+
         'delegação, pelo interino na serventia vaga ou pelo interventor, sob as penas da lei (art. 17, §2º).</p>'+
         '<input id="d1" class="swal2-input" placeholder="Nome do declarante">'+
         '<input id="d2" class="swal2-input" placeholder="Qualificação (titular/interino/interventor)">'+
         '<input id="d3" class="swal2-input" placeholder="Protocolo no Justiça Aberta">',
    showCancelButton:true, confirmButtonText:'Registrar', cancelButtonText:'Cancelar',
    confirmButtonColor:'#4f46e5',
    preConfirm:function(){
      return {declarante:document.getElementById('d1').value,
              qualificacao:document.getElementById('d2').value,
              protocolo_ja:document.getElementById('d3').value};
    }
  }).then(function(r){
    if(!r.isConfirmed) return;
    var fd = new FormData();
    fd.append('acao','declarar_etapa'); fd.append('etapa',etapa);
    fd.append('declarante',r.value.declarante);
    fd.append('qualificacao',r.value.qualificacao);
    fd.append('protocolo_ja',r.value.protocolo_ja);
    fetch('api.php',{method:'POST',body:fd}).then(function(x){return x.json();}).then(function(j){
      if(j.success){
        Swal.fire({icon:'success',title:'Registrado',text:j.message,confirmButtonColor:'#4f46e5'})
          .then(function(){location.reload();});
      } else {
        Swal.fire({icon:'error',title:'Não é possível declarar',text:j.message,confirmButtonColor:'#4f46e5'});
      }
    });
  });
}
JS;
p213_foot($js);
