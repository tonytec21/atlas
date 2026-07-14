<?php
/** ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213 — Inventário de ativos */
require_once __DIR__ . '/p213_lib.php';

$conn = p213_db();
$cats = [
    'hardware'    => 'Hardware',
    'software'    => 'Software',
    'sgbd'        => 'Banco de dados',
    'integracao'  => 'Integração / API',
    'certificado' => 'Certificado digital',
    'contrato'    => 'Contrato',
    'rede'        => 'Ativo de rede',
    'nuvem'       => 'Serviço em nuvem',
];
$crit = ['alta' => 'Alta', 'media' => 'Média', 'baixa' => 'Baixa'];
$pesoCrit = ['alta' => 3, 'media' => 2, 'baixa' => 1];

$ativos = [];
$res = $conn->query("SELECT * FROM p213_ativos ORDER BY FIELD(criticidade,'alta','media','baixa'), categoria, nome");
while ($r = $res->fetch_assoc()) $ativos[] = $r;

$hoje = new DateTime('today');
$eol = []; $venc = [];
foreach ($ativos as $a) {
    if (!$a['suporte_ativo'] || ($a['eol'] && new DateTime($a['eol']) <= $hoje)) $eol[] = $a;
    if ($a['validade']) {
        $d = (int)$hoje->diff(new DateTime($a['validade']))->format('%r%a');
        if ($d <= 60) $venc[] = $a + ['dias' => $d];
    }
}

p213_head('Inventário de ativos — Provimento 213');
p213_hero('Inventário de ativos tecnológicos',
    'Anexo IV, item 1.7 &middot; ativos, integrações, bancos de dados, certificados, softwares, histórico e contratos');
p213_nav('inventario.php');
?>

<?php if ($eol): ?>
  <div class="p213-alert bad">
    <i class="fa fa-ban"></i>
    <div><strong>Componentes sem suporte oficial (EOL).</strong>
      O art. 4º, §3º não admite esses componentes para fins de conformidade.
      <ul>
      <?php foreach ($eol as $a): ?>
        <li><?= p213_esc($a['nome']) ?><?= $a['versao'] ? ' (' . p213_esc($a['versao']) . ')' : '' ?><?php
            if ($a['eol']) echo ' — EOL em ' . date('d/m/Y', strtotime($a['eol'])); ?></li>
      <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<?php if ($venc): ?>
  <div class="p213-alert warn">
    <i class="fa fa-clock-o"></i>
    <div><strong>Vencendo em até 60 dias.</strong>
      <ul>
      <?php foreach ($venc as $a): ?>
        <li><?= p213_esc($a['nome']) ?> — <?= $a['dias'] >= 0 ? $a['dias'] . ' dia(s)' : 'VENCIDO' ?>
          (<?= date('d/m/Y', strtotime($a['validade'])) ?>)</li>
      <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<div class="p213-card">
  <div class="p213-card__head">
    <h2 class="p213-card__title"><i class="fa fa-server"></i> Ativos cadastrados
      <span class="p213-tag info"><?= count($ativos) ?></span></h2>
    <button class="p213-btn p213-btn--pri p213-btn--sm" onclick="editar(null)">
      <i class="fa fa-plus"></i> Novo ativo</button>
  </div>
  <div class="p213-card__body flush">
    <?php if (!$ativos): ?>
      <div class="p213-empty">
        <i class="fa fa-inbox"></i>
        <p>Nenhum ativo cadastrado. O inventário completo é requisito da Etapa 1 (item 1.7).</p>
      </div>
    <?php else: ?>
    <div class="p213-tablewrap">
      <table class="p213-table" style="min-width:880px">
        <thead><tr>
          <th style="width:130px">Categoria</th><th>Ativo</th><th>Fabricante / versão</th>
          <th style="width:90px">Criticidade</th><th>Fornecedor / contrato</th>
          <th style="width:100px">Validade</th><th style="width:60px">LGPD</th><th style="width:96px"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($ativos as $a): ?>
          <tr>
            <td><span class="p213-code"><?= p213_esc(isset($cats[$a['categoria']]) ? $cats[$a['categoria']] : $a['categoria']) ?></span></td>
            <td>
              <strong><?= p213_esc($a['nome']) ?></strong>
              <?php if (!$a['suporte_ativo']): ?><span class="p213-tag c3" style="margin-left:6px">EOL</span><?php endif; ?>
              <?php if ($a['identificacao']): ?>
                <div class="p213-hint"><?= p213_esc($a['identificacao']) ?></div><?php endif; ?>
            </td>
            <td><?= p213_esc(trim($a['fabricante'] . ' ' . $a['versao'])) ?: '—' ?></td>
            <td><span class="p213-tag c<?= $pesoCrit[$a['criticidade']] ?>"><?= p213_esc($crit[$a['criticidade']]) ?></span></td>
            <td><?= p213_esc($a['fornecedor']) ?: '—'
                ?><?php if ($a['contrato']): ?><div class="p213-hint"><?= p213_esc($a['contrato']) ?></div><?php endif; ?></td>
            <td><?= $a['validade'] ? date('d/m/Y', strtotime($a['validade'])) : '—' ?></td>
            <td><?= $a['dados_pessoais']
                ? '<i class="fa fa-user-secret" style="color:var(--warn)" title="Trata dados pessoais"></i>' : '—' ?></td>
            <td>
              <div class="p213-actions" style="flex-wrap:nowrap">
                <button class="p213-btn p213-btn--ghost p213-btn--icon" aria-label="Editar"
                  onclick='editar(<?= json_encode($a, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                  <i class="fa fa-pencil"></i></button>
                <button class="p213-btn p213-btn--bad p213-btn--icon" aria-label="Excluir"
                  onclick="excluir(<?= (int)$a['id'] ?>)"><i class="fa fa-trash"></i></button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- modal próprio (independente do Bootstrap) -->
<div class="p213-overlay" id="ov">
  <div class="p213-modal" role="dialog" aria-modal="true" aria-labelledby="mdTitle">
    <div class="p213-modal__head">
      <h3 id="mdTitle">Ativo tecnológico</h3>
      <button class="p213-x" onclick="fechar()" aria-label="Fechar"><i class="fa fa-times"></i></button>
    </div>
    <div class="p213-modal__body">
      <input type="hidden" id="f_id">
      <div class="p213-fields">
        <div class="p213-field"><label class="p213-label">Categoria</label>
          <select id="f_categoria" class="p213-in">
            <?php foreach ($cats as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select></div>
        <div class="p213-field span2"><label class="p213-label">Nome do ativo *</label>
          <input id="f_nome" class="p213-in"></div>
        <div class="p213-field span2"><label class="p213-label">Identificação (patrimônio, hostname, serial)</label>
          <input id="f_identificacao" class="p213-in"></div>
        <div class="p213-field"><label class="p213-label">Fabricante</label>
          <input id="f_fabricante" class="p213-in"></div>
        <div class="p213-field"><label class="p213-label">Versão</label>
          <input id="f_versao" class="p213-in"></div>
        <div class="p213-field"><label class="p213-label">Criticidade</label>
          <select id="f_criticidade" class="p213-in">
            <?php foreach ($crit as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select></div>
        <div class="p213-field"><label class="p213-label">Suporte oficial ativo?</label>
          <select id="f_suporte_ativo" class="p213-in">
            <option value="1">Sim</option><option value="0">Não (EOL)</option></select></div>
        <div class="p213-field"><label class="p213-label">Data de EOL</label>
          <input type="date" id="f_eol" class="p213-in"></div>
        <div class="p213-field"><label class="p213-label">Validade (certificado/contrato)</label>
          <input type="date" id="f_validade" class="p213-in"></div>
        <div class="p213-field"><label class="p213-label">Fornecedor</label>
          <input id="f_fornecedor" class="p213-in"></div>
        <div class="p213-field"><label class="p213-label">Contrato (nº / objeto)</label>
          <input id="f_contrato" class="p213-in"></div>
        <div class="p213-field"><label class="p213-label">Responsável</label>
          <input id="f_responsavel" class="p213-in"></div>
        <div class="p213-field"><label class="p213-label">Localização (física ou lógica)</label>
          <input id="f_localizacao" class="p213-in"></div>
        <div class="p213-field span2"><label class="p213-label">Observação</label>
          <textarea id="f_observacao" class="p213-in" rows="2"></textarea></div>
      </div>
      <label class="p213-check" style="margin-top:14px">
        <input type="checkbox" id="f_dados_pessoais">
        Trata, armazena ou processa dados pessoais (relevante para o ROPA e para o item 1.8.c)
      </label>
    </div>
    <div class="p213-modal__foot">
      <button class="p213-btn p213-btn--ghost" onclick="fechar()">Cancelar</button>
      <button class="p213-btn p213-btn--pri" onclick="salvarAtivo()"><i class="fa fa-save"></i> Salvar</button>
    </div>
  </div>
</div>

<?php
$js = <<<'JS'
var CAMPOS = ['id','categoria','nome','identificacao','fabricante','versao','criticidade','suporte_ativo',
              'eol','validade','fornecedor','contrato','responsavel','localizacao','observacao'];
var ov = document.getElementById('ov');

function abrir(){ ov.classList.add('open'); document.body.style.overflow = 'hidden'; }
function fechar(){ ov.classList.remove('open'); document.body.style.overflow = ''; }
ov.addEventListener('click', function(e){ if(e.target === ov) fechar(); });
document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && ov.classList.contains('open')) fechar(); });

function editar(a){
  CAMPOS.forEach(function(k){
    var el = document.getElementById('f_' + k);
    if(el) el.value = (a && a[k] !== null && a[k] !== undefined) ? a[k] : '';
  });
  if(!a){
    document.getElementById('f_criticidade').value = 'media';
    document.getElementById('f_suporte_ativo').value = '1';
    document.getElementById('f_categoria').value = 'hardware';
  }
  document.getElementById('f_dados_pessoais').checked = !!(a && parseInt(a.dados_pessoais, 10));
  document.getElementById('mdTitle').textContent = a ? 'Editar ativo' : 'Novo ativo';
  abrir();
  setTimeout(function(){ document.getElementById('f_nome').focus(); }, 60);
}

function salvarAtivo(){
  var fd = new FormData();
  fd.append('acao','ativo_salvar');
  CAMPOS.forEach(function(k){ fd.append(k, document.getElementById('f_' + k).value); });
  fd.append('dados_pessoais', document.getElementById('f_dados_pessoais').checked ? 1 : 0);
  fetch('api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
    if(j.success){ fechar(); location.reload(); }
    else Swal.fire({icon:'error', title:'Erro', text:j.message, confirmButtonColor:'#4f46e5'});
  });
}

function excluir(id){
  Swal.fire({icon:'warning', title:'Excluir ativo?', text:'Esta ação não pode ser desfeita.',
    showCancelButton:true, confirmButtonText:'Excluir', cancelButtonText:'Cancelar',
    confirmButtonColor:'#d64545'})
    .then(function(r){
      if(!r.isConfirmed) return;
      var fd = new FormData(); fd.append('acao','ativo_excluir'); fd.append('id', id);
      fetch('api.php',{method:'POST',body:fd}).then(function(x){return x.json();}).then(function(j){
        if(j.success) location.reload();
        else Swal.fire({icon:'error', title:'Erro', text:j.message, confirmButtonColor:'#4f46e5'});
      });
    });
}
JS;
p213_foot($js);
