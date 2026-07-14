<?php
/** ATLAS-PROV213-BUILD: 2026-07-09-conformidade-cnj-213 — Cofre de evidências */
require_once __DIR__ . '/p213_lib.php';

$cfg    = p213_config();
$classe = (int)$cfg['classe'];
$etapas = p213_etapas();
$resp   = p213_respostas();

// ═══════════════════════════════════════════════ DOWNLOAD SEGURO
if (isset($_GET['baixar'])) {
    $id = (int)$_GET['baixar'];
    $st = p213_db()->prepare("SELECT * FROM p213_evidencias WHERE id=?");
    $st->bind_param('i', $id); $st->execute();
    $ev = $st->get_result()->fetch_assoc(); $st->close();
    if (!$ev || !$ev['arquivo_path']) { http_response_code(404); exit('Evidência não encontrada.'); }

    $abs = realpath(p213_evid_dir() . '/' . $ev['arquivo_path']);
    $raiz = realpath(p213_evid_dir());
    if (!$abs || strpos($abs, $raiz) !== 0 || !is_file($abs)) { http_response_code(404); exit('Arquivo ausente.'); }

    p213_log('evidencia', 'download #' . $id);
    header('Content-Type: ' . ($ev['mime'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($abs));
    header('Content-Disposition: attachment; filename="' . rawurlencode($ev['arquivo_nome']) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($abs);
    exit;
}

// ═══════════════════════════════════════════════ TERMO DE ENCERRAMENTO DO DOSSIÊ
if (isset($_GET['dossie'])) {
    $et = (int)$_GET['dossie'];
    if (!isset($etapas[$et])) { http_response_code(400); exit('Etapa inválida.'); }

    $st = p213_db()->prepare(
        "SELECT * FROM p213_evidencias WHERE etapa=? ORDER BY codigo, criado_em");
    $st->bind_param('i', $et); $st->execute();
    $rs = $st->get_result();
    $itens = [];
    while ($r = $rs->fetch_assoc()) $itens[] = $r;
    $st->close();

    $hashDossie = p213_dossie_hash($et);
    $meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto',
              'setembro','outubro','novembro','dezembro'];
    $hoje = date('j') . ' de ' . $meses[(int)date('n') - 1] . ' de ' . date('Y');

    $h = '<p style="text-align:center"><strong>' . p213_esc($cfg['serventia'] ?: '—') . '</strong><br>'
       . 'CNS ' . p213_esc($cfg['cns'] ?: '—') . ' — Classe ' . $classe . '</p><hr>'
       . '<h2 style="text-align:center">TERMO DE ENCERRAMENTO DO DOSSIÊ TÉCNICO</h2>'
       . '<p style="text-align:center">Etapa ' . $et . ' — ' . p213_esc($etapas[$et]) . '</p>'
       . '<p>Nos termos do Anexo IV, Disposições gerais, III, IV e VIII, do Provimento CN-CNJ n. 213/2026, '
       . 'declara-se encerrado o dossiê técnico da etapa acima, composto de <strong>' . count($itens)
       . '</strong> evidência(s), cuja integridade é aferível pela lista de resumos criptográficos (SHA-256) '
       . 'abaixo transcrita.</p>';

    if ($hashDossie) {
        $h .= '<p><strong>Hash consolidado do dossiê (SHA-256 da concatenação ordenada dos resumos):</strong><br>'
            . '<span style="font-family:courier;font-size:8pt">' . $hashDossie . '</span></p>';
    }

    $h .= '<table border="1" cellpadding="4"><thead><tr>'
        . '<th width="10%">Item</th><th width="30%">Evidência</th><th width="14%">Arquivo</th>'
        . '<th width="10%">Data</th><th width="36%">SHA-256</th></tr></thead><tbody>';
    if (!$itens) {
        $h .= '<tr><td colspan="5">Nenhuma evidência registrada para esta etapa.</td></tr>';
    }
    foreach ($itens as $r) {
        $h .= '<tr><td>' . p213_esc($r['codigo']) . '</td>'
            . '<td>' . p213_esc($r['titulo']) . '</td>'
            . '<td style="font-size:7pt">' . p213_esc($r['arquivo_nome'] ?: '— sem arquivo —') . '</td>'
            . '<td>' . ($r['data_evidencia'] ? date('d/m/Y', strtotime($r['data_evidencia'])) : '—') . '</td>'
            . '<td style="font-family:courier;font-size:6.5pt">' . p213_esc($r['sha256'] ?: '—') . '</td></tr>';
    }
    $h .= '</tbody></table>';

    $h .= '<p style="font-size:8pt">As evidências permanecem arquivadas em repositório com controle de acesso e '
        . 'registro auditável de alterações, pelo prazo mínimo de 5 (cinco) anos. Para as Classes 2 e 3, esta lista '
        . 'de hashes deve ser assinada digitalmente pelo responsável (Disposições gerais, IV). Para a Classe 1, '
        . 'admite-se o mecanismo simplificado de comprovação (Disposições gerais, V, VI e VII).</p>'
        . '<br><p style="text-align:center">' . p213_esc($cfg['municipio_uf'] ?: '__________') . ', ' . $hoje . '.</p>'
        . '<br><br><p style="text-align:center">_________________________________________<br><strong>'
        . p213_esc($cfg['titular'] ?: '__________________________') . '</strong><br>'
        . p213_esc($cfg['titular_qualif']) . '</p>';

    if ((isset($_GET['modo']) ? $_GET['modo'] : 'pdf') === 'pdf' && p213_tcpdf()) {
        p213_log('dossie', 'etapa ' . $et);
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Atlas — Módulo Provimento 213');
        $pdf->SetTitle('Dossiê técnico — Etapa ' . $et);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->setPrintHeader(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 9);
        $pdf->writeHTML('<style>h2{font-size:12pt}p{line-height:1.45}th{background-color:#eef1f4}</style>' . $h,
            true, false, true, false, '');
        $pdf->Output('dossie_etapa' . $et . '.pdf', 'I');
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><title>Dossiê — Etapa ' . $et . '</title>'
       . '<style>body{font-family:system-ui,Arial;max-width:900px;margin:24px auto;padding:0 20px}'
       . 'table{border-collapse:collapse;width:100%;font-size:.72rem}td,th{border:1px solid #bbb;padding:5px}'
       . '@media print{.noprint{display:none}}</style></head><body>'
       . '<div class="noprint" style="text-align:right"><button onclick="window.print()">Imprimir</button></div>'
       . $h . '</body></html>';
    exit;
}

// ═══════════════════════════════════════════════ PÁGINA
$evid   = p213_evidencias_por_codigo();
$itens  = p213_catalogo_por_classe($classe);
$porEt  = [];
foreach ($itens as $it) $porEt[$it['etapa']][] = $it;

$totEv = 0;
foreach ($evid as $l) $totEv += count($l);
$semEv = 0;
foreach ($itens as $it) if (empty($evid[$it['cod']])) $semEv++;
$comArq = 0;
foreach ($evid as $l) foreach ($l as $e) if ($e['arquivo_path']) $comArq++;

$tipos = ['documento' => 'Documento', 'relatorio' => 'Relatório', 'log' => 'Log',
          'captura' => 'Captura de tela', 'contrato' => 'Contrato', 'ata' => 'Ata', 'foto' => 'Fotografia'];

$iaOn = p213_gemini_ativo();

p213_head('Evidências — Provimento 213');
p213_hero('Cofre de evidências',
    'Dossiê técnico por etapa &middot; SHA-256 por arquivo &middot; Anexo IV, Disposições gerais, III, IV e VIII');
p213_nav('evidencias.php');
?>

<?php if (!is_writable(p213_evid_dir())): ?>
  <div class="p213-alert bad">
    <i class="fa fa-exclamation-triangle"></i>
    <div>A pasta <code>evidencias/</code> não tem permissão de escrita. Os uploads vão falhar.</div>
  </div>
<?php endif; ?>

<div class="p213-grid g3" style="margin-bottom:18px">
  <div class="p213-card"><div class="p213-card__body">
    <div class="p213-hint">Evidências registradas</div>
    <div class="p213-metric"><?= $totEv ?></div>
    <div class="p213-hint"><?= $comArq ?> com arquivo anexado</div>
  </div></div>
  <div class="p213-card"><div class="p213-card__body">
    <div class="p213-hint">Requisitos sem evidência</div>
    <div class="p213-metric" style="color:<?= $semEv ? 'var(--bad)' : 'var(--ok)' ?>"><?= $semEv ?></div>
    <div class="p213-hint">de <?= count($itens) ?> aplicáveis à Classe <?= $classe ?></div>
  </div></div>
  <div class="p213-card"><div class="p213-card__body">
    <div class="p213-hint">Integridade do repositório</div>
    <div id="integridade" class="p213-metric" style="font-size:1.1rem;color:var(--ink-3)">não verificada</div>
    <button class="p213-btn p213-btn--ghost p213-btn--sm" style="margin-top:8px" onclick="verificar()">
      <i class="fa fa-shield"></i> Verificar hashes</button>
  </div></div>
</div>

<div class="p213-card" style="margin-bottom:18px">
  <div class="p213-card__body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
    <div class="p213-segbtn">
      <button class="active" data-f="todos">Todos</button>
      <button data-f="sem">Sem evidência</button>
      <button data-f="criticos">Críticos</button>
    </div>
    <div class="p213-search" style="max-width:280px">
      <i class="fa fa-search"></i>
      <input id="busca" class="p213-in" placeholder="Buscar requisito…" autocomplete="off">
    </div>
    <?php if (!$iaOn): ?>
      <span class="p213-hint" style="margin-left:auto">
        <i class="fa fa-magic"></i> Assistente de IA desativado —
        <a href="configuracao.php" style="color:var(--pri)">informe a chave do Gemini</a></span>
    <?php endif; ?>
  </div>
</div>

<div class="p213-acc">
<?php foreach ($etapas as $n => $nome):
      $lista = isset($porEt[$n]) ? $porEt[$n] : [];
      $nEv = 0; $nSem = 0;
      foreach ($lista as $it) { $c = isset($evid[$it['cod']]) ? count($evid[$it['cod']]) : 0; $nEv += $c; if (!$c) $nSem++; }
      $hashDoc = p213_dossie_hash($n);
?>
  <section class="p213-acc__item<?= $n === 1 ? ' open' : '' ?>" data-etapa="<?= $n ?>">
    <button type="button" class="p213-acc__head" aria-expanded="<?= $n === 1 ? 'true' : 'false' ?>">
      <i class="fa fa-chevron-right p213-acc__caret"></i>
      <span class="p213-acc__label"><b>Etapa <?= $n ?></b> <span>— <?= p213_esc($nome) ?></span></span>
      <span class="p213-tag <?= $nSem ? 'c2' : 'c1' ?>"><?= $nEv ?> evidência(s)</span>
      <?php if ($nSem): ?><span class="p213-tag c3"><?= $nSem ?> sem</span><?php endif; ?>
    </button>

    <div class="p213-acc__panel">
      <div class="p213-dossie">
        <div>
          <strong style="font-size:.82rem">Dossiê técnico da Etapa <?= $n ?></strong>
          <div class="p213-hint" style="margin-top:3px">
            <?php if ($hashDoc): ?>
              Hash consolidado <code class="p213-hash"><?= substr($hashDoc, 0, 16) ?>…</code>
            <?php else: ?>
              Sem evidências com arquivo — o hash consolidado será gerado após o primeiro upload.
            <?php endif; ?>
          </div>
        </div>
        <div class="p213-actions">
          <?php if ($iaOn): ?>
            <button class="p213-btn p213-btn--ghost p213-btn--sm" onclick="iaLacunas(<?= $n ?>)">
              <i class="fa fa-magic"></i> Plano da etapa</button>
          <?php endif; ?>
          <a class="p213-btn p213-btn--ghost p213-btn--sm" target="_blank"
             href="evidencias.php?dossie=<?= $n ?>&modo=html"><i class="fa fa-eye"></i> Ver</a>
          <a class="p213-btn p213-btn--pri p213-btn--sm" target="_blank"
             href="evidencias.php?dossie=<?= $n ?>&modo=pdf"><i class="fa fa-file-pdf-o"></i> Termo de encerramento</a>
        </div>
      </div>

      <?php foreach ($lista as $it):
            $lst  = isset($evid[$it['cod']]) ? $evid[$it['cod']] : [];
            $st   = isset($resp[$it['cod']]) ? $resp[$it['cod']]['status'] : 'nao_avaliado';
            $esp  = p213_evid_do_requisito($it['cod']);
      ?>
        <article class="p213-q st-<?= $st ?>" data-cod="<?= p213_esc($it['cod']) ?>"
                 data-peso="<?= $it['peso'] ?>" data-tem="<?= count($lst) ? 1 : 0 ?>"
                 data-texto="<?= p213_esc(mb_strtolower($it['cod'] . ' ' . $it['pergunta'])) ?>">

          <div class="p213-q__tags">
            <span class="p213-code"><?= p213_esc($it['cod']) ?></span>
            <span class="p213-tag c<?= $it['peso'] ?>"><?= p213_criticidade($it['peso']) ?></span>
            <span class="p213-pill <?= $st ?>"><?= p213_status_label($st) ?></span>
            <span class="p213-tag <?= count($lst) ? 'c1' : 'c3' ?>" style="margin-left:auto">
              <?= count($lst) ?> evidência(s)</span>
          </div>

          <p class="p213-q__text"><?= p213_esc($it['pergunta']) ?></p>
          <div class="p213-q__base"><i class="fa fa-book"></i> <?= p213_esc($it['base']) ?></div>

          <?php if ($esp): ?>
            <div class="p213-checklist">
              <div class="p213-checklist__t">Evidências esperadas</div>
              <ul>
                <?php foreach ($esp as $x): ?><li><i class="fa fa-file-o"></i> <?= p213_esc($x) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if ($lst): ?>
            <div class="p213-files">
              <?php foreach ($lst as $e): ?>
                <div class="p213-file">
                  <i class="fa <?= $e['arquivo_path'] ? 'fa-file-text-o' : 'fa-sticky-note-o' ?> p213-file__ico"></i>
                  <div class="p213-file__main">
                    <div class="p213-file__name"><?= p213_esc($e['titulo']) ?>
                      <span class="p213-tag c1"><?= p213_esc(isset($tipos[$e['tipo']]) ? $tipos[$e['tipo']] : $e['tipo']) ?></span>
                    </div>
                    <?php if ($e['descricao']): ?>
                      <div class="p213-hint" style="margin-top:3px"><?= p213_esc($e['descricao']) ?></div>
                    <?php endif; ?>
                    <div class="p213-file__meta">
                      <?php if ($e['arquivo_nome']): ?>
                        <span><?= p213_esc($e['arquivo_nome']) ?></span>
                        <span><?= p213_tamanho((int)$e['tamanho']) ?></span>
                        <span title="<?= p213_esc($e['sha256']) ?>">SHA-256
                          <code class="p213-hash"><?= substr($e['sha256'], 0, 12) ?>…</code></span>
                      <?php else: ?>
                        <span>Registro sem arquivo</span>
                      <?php endif; ?>
                      <?php if ($e['data_evidencia']): ?>
                        <span><?= date('d/m/Y', strtotime($e['data_evidencia'])) ?></span><?php endif; ?>
                      <?php if ($e['responsavel']): ?><span><?= p213_esc($e['responsavel']) ?></span><?php endif; ?>
                    </div>
                  </div>
                  <div class="p213-actions" style="flex-wrap:nowrap">
                    <?php if ($e['arquivo_path']): ?>
                      <a class="p213-btn p213-btn--ghost p213-btn--icon" title="Baixar"
                         href="evidencias.php?baixar=<?= (int)$e['id'] ?>"><i class="fa fa-download"></i></a>
                    <?php endif; ?>
                    <button class="p213-btn p213-btn--bad p213-btn--icon" title="Excluir"
                            onclick="excluirEv(<?= (int)$e['id'] ?>)"><i class="fa fa-trash"></i></button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="p213-actions" style="margin-top:12px">
            <button class="p213-btn p213-btn--pri p213-btn--sm"
                    onclick="novaEvidencia('<?= p213_esc($it['cod']) ?>')">
              <i class="fa fa-upload"></i> Anexar evidência</button>
            <?php if ($iaOn): ?>
              <button class="p213-btn p213-btn--ghost p213-btn--sm"
                      onclick="iaSugerir('<?= p213_esc($it['cod']) ?>')">
                <i class="fa fa-magic"></i> Sugerir evidências</button>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
</div>

<!-- ═══════════════ MODAL DE UPLOAD -->
<div class="p213-overlay" id="ov">
  <div class="p213-modal" role="dialog" aria-modal="true">
    <div class="p213-modal__head">
      <h3>Nova evidência <span class="p213-code" id="mCod"></span></h3>
      <button class="p213-x" onclick="fechar()" aria-label="Fechar"><i class="fa fa-times"></i></button>
    </div>
    <div class="p213-modal__body">
      <div class="p213-drop" id="drop">
        <i class="fa fa-cloud-upload"></i>
        <p>Arraste o arquivo aqui ou <strong>clique para escolher</strong></p>
        <span class="p213-hint">PDF, imagens, logs, planilhas, ZIP, p7s &middot; até 30 MB</span>
        <input type="file" id="f_arquivo" hidden>
      </div>
      <div id="dropInfo" class="p213-hint" style="margin-top:8px"></div>

      <div class="p213-fields" style="margin-top:16px">
        <div class="p213-field span2"><label class="p213-label">Título da evidência *</label>
          <input id="f_titulo" class="p213-in" placeholder="Ex.: Laudo de aterramento com ART nº 123/2026"></div>
        <div class="p213-field"><label class="p213-label">Tipo</label>
          <select id="f_tipo" class="p213-in">
            <?php foreach ($tipos as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select></div>
        <div class="p213-field"><label class="p213-label">Data da evidência</label>
          <input type="date" id="f_data" class="p213-in"></div>
        <div class="p213-field"><label class="p213-label">Responsável</label>
          <input id="f_resp" class="p213-in"></div>
        <div class="p213-field span2">
          <label class="p213-label" style="display:flex;justify-content:space-between;align-items:center">
            <span>Descrição para o dossiê</span>
            <?php if ($iaOn): ?>
              <button class="p213-btn p213-btn--ghost p213-btn--sm" type="button" onclick="iaDescricao()">
                <i class="fa fa-magic"></i> Redigir com IA</button>
            <?php endif; ?>
          </label>
          <textarea id="f_desc" class="p213-in" rows="4"
            placeholder="O que este artefato demonstra e por que satisfaz o requisito"></textarea>
        </div>
      </div>
    </div>
    <div class="p213-modal__foot">
      <button class="p213-btn p213-btn--ghost" onclick="fechar()">Cancelar</button>
      <button class="p213-btn p213-btn--pri" id="btnSalvar" onclick="salvarEv()">
        <i class="fa fa-save"></i> Registrar evidência</button>
    </div>
  </div>
</div>

<?php
$js = <<<'JS'
var ov = document.getElementById('ov'), drop = document.getElementById('drop'),
    inp = document.getElementById('f_arquivo'), codAtual = '';

document.querySelectorAll('.p213-acc__head').forEach(function(h){
  h.addEventListener('click', function(){
    var it = h.closest('.p213-acc__item');
    h.setAttribute('aria-expanded', it.classList.toggle('open') ? 'true' : 'false');
  });
});

function fechar(){ ov.classList.remove('open'); document.body.style.overflow=''; }
ov.addEventListener('click', function(e){ if(e.target===ov) fechar(); });
document.addEventListener('keydown', function(e){ if(e.key==='Escape' && ov.classList.contains('open')) fechar(); });

function novaEvidencia(cod){
  codAtual = cod;
  document.getElementById('mCod').textContent = cod;
  ['f_titulo','f_data','f_resp','f_desc'].forEach(function(i){ document.getElementById(i).value=''; });
  inp.value = ''; document.getElementById('dropInfo').textContent = '';
  drop.classList.remove('has');
  ov.classList.add('open'); document.body.style.overflow='hidden';
  setTimeout(function(){ document.getElementById('f_titulo').focus(); }, 60);
}

drop.addEventListener('click', function(){ inp.click(); });
['dragenter','dragover'].forEach(function(ev){
  drop.addEventListener(ev, function(e){ e.preventDefault(); drop.classList.add('over'); });
});
['dragleave','drop'].forEach(function(ev){
  drop.addEventListener(ev, function(e){ e.preventDefault(); drop.classList.remove('over'); });
});
drop.addEventListener('drop', function(e){
  if(e.dataTransfer.files.length){ inp.files = e.dataTransfer.files; mostrarArquivo(); }
});
inp.addEventListener('change', mostrarArquivo);

function mostrarArquivo(){
  if(!inp.files.length) return;
  var f = inp.files[0];
  drop.classList.add('has');
  document.getElementById('dropInfo').innerHTML =
    '<i class="fa fa-check" style="color:var(--ok)"></i> ' + f.name +
    ' · ' + (f.size/1024/1024).toFixed(2) + ' MB';
  var t = document.getElementById('f_titulo');
  if(!t.value) t.value = f.name.replace(/\.[^.]+$/, '');
}

function salvarEv(){
  var btn = document.getElementById('btnSalvar');
  var titulo = document.getElementById('f_titulo').value.trim();
  if(!titulo){ Swal.fire({icon:'warning',title:'Informe o título',confirmButtonColor:'#4f46e5'}); return; }

  var fd = new FormData();
  fd.append('acao','evid_upload');
  fd.append('codigo', codAtual);
  fd.append('titulo', titulo);
  fd.append('tipo', document.getElementById('f_tipo').value);
  fd.append('descricao', document.getElementById('f_desc').value);
  fd.append('data_evidencia', document.getElementById('f_data').value);
  fd.append('responsavel', document.getElementById('f_resp').value);
  if(inp.files.length) fd.append('arquivo', inp.files[0]);

  btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enviando…';
  fetch('api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
    btn.disabled = false; btn.innerHTML = '<i class="fa fa-save"></i> Registrar evidência';
    if(j.success){ fechar(); location.reload(); }
    else Swal.fire({icon:'error',title:'Erro',text:j.message,confirmButtonColor:'#4f46e5'});
  }).catch(function(){
    btn.disabled = false; btn.innerHTML = '<i class="fa fa-save"></i> Registrar evidência';
    Swal.fire({icon:'error',title:'Falha de rede',confirmButtonColor:'#4f46e5'});
  });
}

function excluirEv(id){
  Swal.fire({icon:'warning',title:'Excluir evidência?',text:'O arquivo será removido do repositório.',
    showCancelButton:true,confirmButtonText:'Excluir',cancelButtonText:'Cancelar',confirmButtonColor:'#d64545'})
  .then(function(r){
    if(!r.isConfirmed) return;
    var fd = new FormData(); fd.append('acao','evid_excluir'); fd.append('id',id);
    fetch('api.php',{method:'POST',body:fd}).then(function(x){return x.json();}).then(function(j){
      if(j.success) location.reload();
      else Swal.fire({icon:'error',title:'Erro',text:j.message,confirmButtonColor:'#4f46e5'});
    });
  });
}

function verificar(){
  var el = document.getElementById('integridade');
  el.textContent = 'verificando…';
  var fd = new FormData(); fd.append('acao','evid_verificar');
  fetch('api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
    if(!j.success){ el.textContent = 'erro'; return; }
    var p = j.data.problemas;
    if(!p.length){
      el.style.color = 'var(--ok)';
      el.textContent = j.data.integros + ' íntegro(s)';
      Swal.fire({icon:'success',title:'Repositório íntegro',text:j.message,confirmButtonColor:'#4f46e5'});
    } else {
      el.style.color = 'var(--bad)';
      el.textContent = p.length + ' problema(s)';
      var html = '<ul style="text-align:left;font-size:.85rem">';
      p.forEach(function(x){ html += '<li><b>' + x.codigo + '</b> — ' + x.titulo + ': ' + x.erro + '</li>'; });
      Swal.fire({icon:'error',title:'Integridade comprometida',html:html+'</ul>',confirmButtonColor:'#4f46e5'});
    }
  });
}

/* ─────────── Gemini */
function iaSugerir(cod){
  Swal.fire({title:'Consultando o Gemini…', didOpen:function(){Swal.showLoading();},
    allowOutsideClick:false, showConfirmButton:false});
  var fd = new FormData(); fd.append('acao','ia_evidencias'); fd.append('codigo',cod);
  fetch('api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
    if(!j.success){ Swal.fire({icon:'error',title:'IA indisponível',text:j.message,confirmButtonColor:'#4f46e5'}); return; }
    var html = '<div style="text-align:left">';
    j.data.forEach(function(e){
      html += '<div style="border:1px solid #e5e8ee;border-radius:10px;padding:10px 12px;margin-bottom:8px">'
            + '<div style="font-weight:600;font-size:.86rem">' + e.titulo + '</div>'
            + '<div style="font-size:.75rem;color:#8f9aab;margin:2px 0 5px">' + (e.tipo||'') + '</div>'
            + '<div style="font-size:.8rem;color:#4b5565">' + (e.como_obter||'') + '</div></div>';
    });
    html += '<p style="font-size:.72rem;color:#8f9aab;margin-top:10px">Sugestões geradas por IA. '
          + 'Confira contra o texto do Provimento antes de usar.</p></div>';
    Swal.fire({title:'Evidências sugeridas — ' + cod, html:html, width:640,
      confirmButtonText:'Anexar uma agora', showCancelButton:true, cancelButtonText:'Fechar',
      confirmButtonColor:'#4f46e5'})
      .then(function(r){ if(r.isConfirmed) novaEvidencia(cod); });
  });
}

function iaDescricao(){
  var titulo = document.getElementById('f_titulo').value.trim();
  var notas  = document.getElementById('f_desc').value.trim();
  if(!titulo && !notas){
    Swal.fire({icon:'info',title:'Dê um ponto de partida',
      text:'Preencha o título ou escreva anotações para a IA desenvolver.',confirmButtonColor:'#4f46e5'});
    return;
  }
  Swal.fire({title:'Redigindo…', didOpen:function(){Swal.showLoading();},
    allowOutsideClick:false, showConfirmButton:false});
  var fd = new FormData();
  fd.append('acao','ia_descricao'); fd.append('codigo',codAtual);
  fd.append('titulo',titulo); fd.append('notas',notas);
  fetch('api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
    Swal.close();
    if(!j.success){ Swal.fire({icon:'error',title:'IA indisponível',text:j.message,confirmButtonColor:'#4f46e5'}); return; }
    document.getElementById('f_desc').value = j.data.descricao;
  });
}

function iaLacunas(etapa){
  Swal.fire({title:'Montando o plano…', didOpen:function(){Swal.showLoading();},
    allowOutsideClick:false, showConfirmButton:false});
  var fd = new FormData(); fd.append('acao','ia_lacunas'); fd.append('etapa',etapa);
  fetch('api.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
    if(!j.success){ Swal.fire({icon:'info',title:'Sem plano',text:j.message,confirmButtonColor:'#4f46e5'}); return; }
    var html = '<div style="text-align:left">';
    j.data.forEach(function(p){
      html += '<div style="display:flex;gap:10px;margin-bottom:10px">'
            + '<div style="flex:0 0 26px;height:26px;border-radius:50%;background:#4f46e5;color:#fff;'
            + 'display:grid;place-items:center;font-size:.75rem;font-weight:700">' + p.ordem + '</div>'
            + '<div><div style="font-size:.86rem;font-weight:500">' + p.acao + '</div>'
            + '<div style="font-size:.74rem;color:#8f9aab">' + (p.responsavel||'') + ' · '
            + (p.prazo_dias ? p.prazo_dias + ' dias' : '') + ' · '
            + (p.itens ? p.itens.join(', ') : '') + '</div></div></div>';
    });
    html += '<p style="font-size:.72rem;color:#8f9aab">Plano gerado por IA. Os prazos legais do Provimento '
          + 'prevalecem sobre qualquer estimativa acima.</p></div>';
    Swal.fire({title:'Plano da Etapa ' + etapa, html:html, width:680, confirmButtonColor:'#4f46e5'});
  });
}

/* ─────────── filtros */
var filtro = 'todos';
function aplicar(){
  var q = document.getElementById('busca').value.trim().toLowerCase();
  document.querySelectorAll('.p213-acc__item').forEach(function(sec){
    var vis = 0;
    sec.querySelectorAll('.p213-q').forEach(function(c){
      var ok = true;
      if(filtro === 'sem')      ok = c.dataset.tem === '0';
      if(filtro === 'criticos') ok = parseInt(c.dataset.peso,10) === 3;
      if(ok && q) ok = c.dataset.texto.indexOf(q) !== -1;
      c.style.display = ok ? '' : 'none';
      if(ok) vis++;
    });
    sec.style.display = vis ? '' : 'none';
    if((filtro !== 'todos' || q) && vis) sec.classList.add('open');
  });
}
document.querySelectorAll('[data-f]').forEach(function(b){
  b.addEventListener('click', function(){
    document.querySelectorAll('[data-f]').forEach(function(x){ x.classList.remove('active'); });
    b.classList.add('active'); filtro = b.dataset.f; aplicar();
  });
});
document.getElementById('busca').addEventListener('input', aplicar);
JS;
p213_foot($js);
