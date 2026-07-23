<?php
/** ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213 — Diagnóstico interativo */
require_once __DIR__ . '/p213_lib.php';

$cfg    = p213_config();
$classe = (int)$cfg['classe'];
$score  = p213_score($classe);
$resp   = p213_respostas();
$etapas = p213_etapas();
$itens  = p213_catalogo_por_classe($classe);
$nEvid  = p213_evid_contagem();

$porEtapa = [];
foreach ($itens as $it) $porEtapa[$it['etapa']][] = $it;

$opts = ['conforme' => 'Conforme', 'parcial' => 'Parcial',
         'nao_conforme' => 'Não conforme', 'nao_aplicavel' => 'Não aplicável'];

function cor_pct($p) { return $p >= 90 ? 'ok' : ($p >= 60 ? 'warn' : 'bad'); }

p213_head('Diagnóstico — Provimento 213');
p213_hero('Diagnóstico de conformidade',
    'Classe ' . $classe . ' &middot; Subclasse ' . p213_esc($cfg['subclasse']) . ' &middot; ' .
    count($itens) . ' requisitos aplicáveis &middot; as respostas são salvas automaticamente');
p213_nav('diagnostico.php');
?>

<div class="p213-alert info">
  <i class="fa fa-gavel"></i>
  <div><strong>Regime de transição do art. 20-A (Prov. 243/2026).</strong> Havendo indisponibilidade de mercado
    ou custo manifestamente desproporcional, a Corregedoria pode autorizar o cumprimento em nível técnico
    diverso — mediante requerimento com laudo de profissional habilitado e três orçamentos. A ressalva
    <em>não é automática</em> e só produz efeitos após o deferimento. Os itens marcados
    <span class="p213-tag c3"><i class="fa fa-lock"></i> sem ressalva</span> constituem padrão mínimo
    indispensável e não a admitem (§4º).</div>
</div>

<div class="p213-progressbar" id="pbar">
  <div class="p213-progressbar__row">
    <div class="p213-progressbar__meter">
      <div class="p213-progressbar__top">
        <span class="p213-hint">Aderência global</span>
        <strong id="pctGeral" style="font-size:.86rem"><?= number_format($score['geral'], 1, ',', '.') ?>%</strong>
      </div>
      <div class="p213-bar"><i id="barGeral" class="<?= cor_pct($score['geral']) ?>"
        style="width:<?= $score['geral'] ?>%"></i></div>
    </div>
    <div class="p213-toolbar">
      <span class="p213-flag" id="flagSave"><i class="fa fa-check"></i> Salvo</span>
      <div class="p213-segbtn">
        <button class="active" data-filtro="todos">Todos</button>
        <button data-filtro="pendentes">Pendentes</button>
        <button data-filtro="criticos">Críticos</button>
      </div>
      <div class="p213-search">
        <i class="fa fa-search"></i>
        <input id="busca" class="p213-in" placeholder="Buscar requisito…" autocomplete="off">
      </div>
    </div>
  </div>
</div>

<div class="p213-acc" id="acc">
<?php foreach ($etapas as $n => $nome):
      $d = $score['etapas'][$n];
      $lista = isset($porEtapa[$n]) ? $porEtapa[$n] : []; ?>
  <section class="p213-acc__item<?= $n === 1 ? ' open' : '' ?>" data-etapa="<?= $n ?>">
    <button type="button" class="p213-acc__head" aria-expanded="<?= $n === 1 ? 'true' : 'false' ?>">
      <i class="fa fa-chevron-right p213-acc__caret" aria-hidden="true"></i>
      <span class="p213-acc__label"><b>Etapa <?= $n ?></b> <span>— <?= p213_esc($nome) ?></span></span>
      <span class="p213-tag" id="tagEt<?= $n ?>"
            style="background:var(--<?= cor_pct($d['pct']) ?>-bg);color:var(--<?= cor_pct($d['pct']) ?>)">
        <?= number_format($d['pct'], 0) ?>%</span>
      <span class="p213-hint" style="flex:0 0 auto" id="cntEt<?= $n ?>">
        <?= $d['conforme'] ?>/<?= $d['total'] ?></span>
    </button>

    <div class="p213-acc__panel">
    <?php foreach ($lista as $it):
          $r  = isset($resp[$it['cod']]) ? $resp[$it['cod']] : [];
          $st = isset($r['status']) ? $r['status'] : 'nao_avaliado'; ?>
      <article class="p213-q st-<?= $st ?>" data-cod="<?= p213_esc($it['cod']) ?>"
               data-peso="<?= $it['peso'] ?>" data-status="<?= $st ?>"
               data-texto="<?= p213_esc(mb_strtolower($it['cod'] . ' ' . $it['pergunta'] . ' ' . $it['base'])) ?>">

        <div class="p213-q__tags">
          <span class="p213-code"><?= p213_esc($it['cod']) ?></span>
          <span class="p213-tag c<?= $it['peso'] ?>"><?= p213_criticidade($it['peso']) ?></span>
          <?php if (count($it['classes']) < 3): ?>
            <span class="p213-tag info">Classe <?= implode('/', $it['classes']) ?></span>
          <?php endif; ?>
          <?php if (p213_nao_ressalvavel($it['cod'])): ?>
            <span class="p213-tag c3" title="Art. 20-A, §4º — padrão mínimo indispensável: não admite ressalva técnica">
              <i class="fa fa-lock"></i> sem ressalva</span>
          <?php endif; ?>
          <?php $ne = isset($nEvid[$it['cod']]) ? $nEvid[$it['cod']] : 0; ?>
          <a class="p213-tag <?= $ne ? 'c1' : 'c3' ?>" style="margin-left:auto;text-decoration:none"
             href="evidencias.php" title="Gerenciar evidências deste requisito">
            <i class="fa fa-paperclip"></i> <?= $ne ?></a>
        </div>

        <p class="p213-q__text"><?= p213_esc($it['pergunta']) ?></p>
        <div class="p213-q__base"><i class="fa fa-book"></i> <?= p213_esc($it['base']) ?></div>

        <div class="p213-seg">
          <?php foreach ($opts as $k => $lbl): ?>
            <label class="<?= $st === $k ? 'sel-' . $k : '' ?>" data-val="<?= $k ?>">
              <input type="radio" name="st_<?= p213_esc($it['cod']) ?>" value="<?= $k ?>"
                     <?= $st === $k ? 'checked' : '' ?>><?= $lbl ?>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="p213-fields" style="margin-top:14px">
          <div class="p213-field span2">
            <label class="p213-label">Evidência / solução adotada</label>
            <textarea class="p213-in ev" rows="2"
              placeholder="Ex.: PSI v1.2 aprovada em 12/03/2026, ata nº 4"><?= p213_esc(isset($r['evidencia']) ? $r['evidencia'] : '') ?></textarea>
          </div>
          <div class="p213-field span2">
            <label class="p213-label">Observação / justificativa</label>
            <textarea class="p213-in ob" rows="2"
              placeholder="Obrigatória quando marcado como não aplicável"><?= p213_esc(isset($r['observacao']) ? $r['observacao'] : '') ?></textarea>
          </div>
          <div class="p213-field">
            <label class="p213-label">Responsável</label>
            <input class="p213-in rp" value="<?= p213_esc(isset($r['responsavel']) ? $r['responsavel'] : '') ?>">
          </div>
          <div class="p213-field">
            <label class="p213-label">Data de conclusão</label>
            <input type="date" class="p213-in dt" value="<?= p213_esc(isset($r['data_conclusao']) ? $r['data_conclusao'] : '') ?>">
          </div>
        </div>

        <div class="p213-tip">
          <i class="fa fa-lightbulb-o"></i>
          <div><b>Como cumprir:</b> <?= p213_esc($it['sugestao']) ?></div>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
</div>

<?php
$js = <<<'JS'
(function(){
  var flag = document.getElementById('flagSave'), timers = {};

  /* marca a barra como "grudada" para ela tapar a faixa sob a navbar fixa */
  var pbar = document.getElementById('pbar');
  function topPx(){
    var v = getComputedStyle(document.querySelector('.p213-scope')).getPropertyValue('--top');
    return parseInt(v, 10) || 76;
  }
  function checarStuck(){
    pbar.classList.toggle('stuck', pbar.getBoundingClientRect().top <= topPx() + 1);
  }
  window.addEventListener('scroll', checarStuck, {passive:true});
  window.addEventListener('resize', checarStuck);
  checarStuck();

  /* accordion próprio — o do Bootstrap conflita com o style.css do Atlas */
  document.querySelectorAll('.p213-acc__head').forEach(function(h){
    h.addEventListener('click', function(){
      var item = h.closest('.p213-acc__item');
      var open = item.classList.toggle('open');
      h.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  function corPct(p){ return p >= 90 ? 'ok' : (p >= 60 ? 'warn' : 'bad'); }

  function pintar(card, st){
    card.className = 'p213-q st-' + st;
    card.dataset.status = st;
    card.querySelectorAll('.p213-seg label').forEach(function(l){
      l.className = (l.dataset.val === st) ? 'sel-' + st : '';
    });
  }

  function aplicarScore(s){
    document.getElementById('pctGeral').textContent = s.geral.toFixed(1).replace('.', ',') + '%';
    var b = document.getElementById('barGeral');
    b.style.width = s.geral + '%';
    b.className = corPct(s.geral);
    Object.keys(s.etapas).forEach(function(e){
      var t = document.getElementById('tagEt' + e), c = document.getElementById('cntEt' + e);
      if(!t) return;
      var d = s.etapas[e], k = corPct(d.pct);
      t.textContent = Math.round(d.pct) + '%';
      t.style.background = 'var(--' + k + '-bg)';
      t.style.color = 'var(--' + k + ')';
      if(c) c.textContent = d.conforme + '/' + d.total;
    });
  }

  function salvar(card){
    var sel = card.querySelector('input[type=radio]:checked');
    if(!sel) return;
    var fd = new FormData();
    fd.append('acao','salvar_resposta');
    fd.append('codigo', card.dataset.cod);
    fd.append('status', sel.value);
    fd.append('evidencia', card.querySelector('.ev').value);
    fd.append('observacao', card.querySelector('.ob').value);
    fd.append('responsavel', card.querySelector('.rp').value);
    fd.append('data_conclusao', card.querySelector('.dt').value);

    fetch('api.php',{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(j){
        if(j.success){
          pintar(card, sel.value);
          aplicarScore(j.data);
          flag.classList.add('on');
          setTimeout(function(){ flag.classList.remove('on'); }, 1400);
        } else {
          Swal.fire({icon:'warning', title:'Não salvo', text:j.message, confirmButtonColor:'#4f46e5'});
        }
      })
      .catch(function(){ Swal.fire({icon:'error', title:'Falha de rede', confirmButtonColor:'#4f46e5'}); });
  }

  document.querySelectorAll('.p213-q').forEach(function(card){
    card.querySelectorAll('input[type=radio]').forEach(function(r){
      r.addEventListener('change', function(){ salvar(card); });
    });
    ['.ev','.ob','.rp','.dt'].forEach(function(sel){
      var el = card.querySelector(sel);
      if(!el) return;
      el.addEventListener('input', function(){
        clearTimeout(timers[card.dataset.cod]);
        timers[card.dataset.cod] = setTimeout(function(){ salvar(card); }, 700);
      });
    });
  });

  /* filtros + busca: abre automaticamente as etapas com resultado */
  var filtro = 'todos';
  function aplicarFiltro(){
    var q = document.getElementById('busca').value.trim().toLowerCase();
    document.querySelectorAll('.p213-acc__item').forEach(function(sec){
      var visiveis = 0;
      sec.querySelectorAll('.p213-q').forEach(function(c){
        var st = c.dataset.status, peso = parseInt(c.dataset.peso, 10), ok = true;
        if(filtro === 'pendentes') ok = (st !== 'conforme' && st !== 'nao_aplicavel');
        if(filtro === 'criticos')  ok = (peso === 3);
        if(ok && q) ok = c.dataset.texto.indexOf(q) !== -1;
        c.style.display = ok ? '' : 'none';
        if(ok) visiveis++;
      });
      sec.style.display = visiveis ? '' : 'none';
      if((filtro !== 'todos' || q) && visiveis) sec.classList.add('open');
    });
  }
  document.querySelectorAll('[data-filtro]').forEach(function(b){
    b.addEventListener('click', function(){
      document.querySelectorAll('[data-filtro]').forEach(function(x){ x.classList.remove('active'); });
      b.classList.add('active');
      filtro = b.dataset.filtro;
      aplicarFiltro();
    });
  });
  document.getElementById('busca').addEventListener('input', aplicarFiltro);
})();
JS;
p213_foot($js);
