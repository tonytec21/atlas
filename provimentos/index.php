<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/* Bases absolutas
   $appBase    → http(s)://{SERVIDOR}/atlas/provimentos/
   $viewerBase → http(s)://{SERVIDOR}/atlas/provimentos/pdfjs/web/viewer.html */
$scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$appBase    = $scheme . '://' . $host . $scriptDir . '/';
$viewerBase = $appBase . 'pdfjs/web/viewer.html';

/* Helper para preencher o formulário com os valores enviados */
$g = $_GET ?? [];
function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provimentos e Resoluções</title>

    <!-- CSS base -->
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">

    <!-- Favicon -->
    <link rel="icon" href="../style/img/favicon.png" type="image/png">

    <!-- Material Design Icons (CDN para corrigir erro de fontes 404) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="../style/css/dataTables.bootstrap4.min.css">

    <style>
        /* =========================================================
           Tema Light/Dark — variáveis apenas do MODAL de visualização
           O sistema já controla body.light-mode / body.dark-mode
           =======================================================*/
        body.light-mode {
            --modal-bg: #f8fafc;
            --modal-panel: #ffffff;
            --modal-bar: #f1f5f9;
            --modal-border: #e5e7eb;
            --modal-text: #0f172a;
            --modal-muted: #64748b;
            --modal-header1: #2563eb;
            --modal-header2: #1e40af;
            --modal-badge-bg: rgba(0,0,0,.06);
            --modal-badge-brd: rgba(0,0,0,.12);
            --btn-outline: #0f172a;
            --btn-outline-hover: rgba(2,6,23,.06);
            --loader-fg: #0f172a;
            --loader-bg1: rgba(59,130,246,.12);
            --loader-bg2: rgba(29,78,216,.18);
            --input-bg: #ffffff;
            --input-brd: #d1d5db;
            --input-text:#0f172a;
            --input-ph:#6b7280;
        }
        body.dark-mode {
            --modal-bg: #0b1220;
            --modal-panel: #0b1324;
            --modal-bar: #0e1627;
            --modal-border: rgba(255,255,255,.08);
            --modal-text: #e5e7eb;
            --modal-muted: #9ca3af;
            --modal-header1: #2563eb;
            --modal-header2: #1e40af;
            --modal-badge-bg: rgba(255,255,255,.15);
            --modal-badge-brd: rgba(255,255,255,.25);
            --btn-outline: #e5e7eb;
            --btn-outline-hover: rgba(255,255,255,.08);
            --loader-fg: #dbeafe;
            --loader-bg1: rgba(59,130,246,.12);
            --loader-bg2: rgba(29,78,216,.18);
            --input-bg: #0b1324;
            --input-brd: rgba(255,255,255,.15);
            --input-text:#e5e7eb;
            --input-ph:#9ca3af;
        }

        .btn-adicionar { height: 38px; line-height: 24px; margin-left: 10px; }

        .table th:nth-child(1), .table td:nth-child(1){ width:7%; }
        .table th:nth-child(2), .table td:nth-child(2){ width:7%; }
        .table th:nth-child(3), .table td:nth-child(3){ width:5%; }
        .table th:nth-child(4), .table td:nth-child(4){ width:8%; }
        .table th:nth-child(5), .table td:nth-child(5){ width:68%; }
        .table th:nth-child(6), .table td:nth-child(6){ width:5%; }

        .modal-modern.modal          { backdrop-filter: blur(4px); }
        .modal-modern .modal-dialog  { max-width:90vw!important; width:90vw!important; margin:2vh auto; }
        .modal-modern .modal-content {
            height:100vh; display:flex; flex-direction:column;
            border:1px solid var(--modal-border); border-radius:16px; overflow:hidden;
            box-shadow:0 20px 50px rgba(0,0,0,.35); background:var(--modal-panel); color:var(--modal-text);
        }
        .modal-modern .modal-header  { border:0; padding:14px 18px; background:linear-gradient(135deg,var(--modal-header1),var(--modal-header2)); color:#fff; }
        .modal-modern .modal-title   { display:flex; align-items:center; gap:.75rem; font-weight:600; }
        .modal-modern .modal-title .badge{ background:var(--modal-badge-bg); border:1px solid var(--modal-badge-brd); color:#fff; font-weight:500; }
        .modal-modern .close , .modal-modern .close:hover{ color:#fff; opacity:1; text-shadow:none; }

        .modal-modern .modal-body{
            background:var(--modal-bg); color:var(--modal-text);
            border-top:1px solid var(--modal-border); border-bottom:1px solid var(--modal-border);
            padding:0; display:flex; flex-direction:column;
        }
        .modal-modern .meta-bar{
            display:grid; grid-template-columns:repeat(4,minmax(0,1fr));
            gap:12px; padding:14px 16px; background:var(--modal-bar); border-bottom:1px solid var(--modal-border);
        }
        .meta-item{ background:var(--modal-panel); border:1px solid var(--modal-border); border-radius:12px; padding:10px 12px;}
        .meta-item label{ display:block; font-size:.75rem; color:var(--modal-muted); margin-bottom:2px;}
        .meta-item .value{ font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        .modal-modern .doc-toolbar{
            display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
            padding:10px 16px; background:var(--modal-bar); border-top:1px solid var(--modal-border); border-bottom:1px solid var(--modal-border);
        }

        .meta-desc{
            max-width:60%; cursor:pointer; position:relative; line-height:1.25rem;
            max-height:2.6rem; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
            transition:max-height .2s ease;
        }
        .meta-desc.expanded{
            background:var(--modal-panel); border:1px solid var(--modal-border); padding:8px 10px;
            border-radius:8px; max-height:18rem; overflow:auto; -webkit-line-clamp:unset;
        }
        .meta-desc .mdi{ vertical-align:middle; margin-right:6px; }

        .doc-actions{ display:flex; align-items:center; gap:6px; }
        .doc-actions .btn.theme-outline{
            border-radius:10px; border:1px solid var(--btn-outline); color:var(--btn-outline); background:transparent;
        }
        .doc-actions .btn.theme-outline i{ margin-right:6px; font-size:16px; }
        .doc-actions .btn.theme-outline:hover{ background:var(--btn-outline-hover); }

        .pdf-search .form-control{
            background:var(--input-bg); color:var(--input-text); border:1px solid var(--input-brd);
        }
        .pdf-search .form-control::placeholder{ color:var(--input-ph); }
        .pdf-search .input-group-append .btn{
            border:1px solid var(--btn-outline); color:var(--btn-outline); background:transparent; border-left:none;
        }
        .pdf-search .input-group-append .btn:hover{ background:var(--btn-outline-hover); }

        .viewer-wrapper{ position:relative; flex:1 1 auto; min-height:200px; background:var(--modal-panel);}
        .viewer-frame  { position:absolute; inset:0; width:100%; height:100%; border:0; background:var(--modal-panel);}
        .doc-loader{
            position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
            background:radial-gradient(1200px 600px at 30% -20%,var(--loader-bg1),transparent 60%),radial-gradient(800px 600px at 130% 120%,var(--loader-bg2),transparent 60%),var(--modal-panel);
            color:var(--loader-fg); font-weight:600; letter-spacing:.3px;
        }

        @media (max-width:992px){
            .modal-modern .meta-bar{ grid-template-columns:repeat(2,minmax(0,1fr)); }
            .meta-desc{ max-width:100%; }
        }
        @media (max-width:576px){
            .modal-modern .modal-dialog{ width:96vw!important; max-width:96vw!important; margin:2vh auto; }
            .modal-modern .modal-content{ height:92vh; }
            .modal-modern .meta-bar{ grid-template-columns:1fr; }
            .doc-actions .btn.theme-outline span{ display:none; }
        }
    </style>
</head>

<body class="light-mode">
<?php include(__DIR__ . '/../menu.php'); ?>

<!-- ================================ PÁGINA PRINCIPAL ================================ -->
<div id="main" class="main-content">
    <div class="container">
        <h3>Pesquisar Provimentos e Resoluções</h3>
        <hr>
        <!-- --------------------------- FORMULÁRIO DE FILTRO --------------------------- -->
        <form id="pesquisarForm" method="GET">
            <div class="form-row">
                <div class="form-group col-md-2">
                    <label for="tipo">Tipo:</label>
                    <select class="form-control" id="tipo" name="tipo">
                        <option value="" <?= (($g['tipo'] ?? '')==='' ? 'selected' : '') ?>>Todos</option>
                        <option value="Provimento" <?= (($g['tipo'] ?? '')==='Provimento' ? 'selected' : '') ?>>Provimento</option>
                        <option value="Resolução"  <?= (($g['tipo'] ?? '')==='Resolução'  ? 'selected' : '') ?>>Resolução</option>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label for="numero_provimento">Nº Prov./Resol.:</label>
                    <input type="text" class="form-control" id="numero_provimento" name="numero_provimento"
                           value="<?= e($g['numero_provimento'] ?? '') ?>">
                </div>
                <div class="form-group col-md-2">
                    <label for="ano">Ano:</label>
                    <input type="text" class="form-control" id="ano" name="ano" pattern="\d{4}" maxlength="4"
                           oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,4)" title="Digite um ano válido"
                           value="<?= e($g['ano'] ?? '') ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="origem">Origem:</label>
                    <select class="form-control" id="origem" name="origem">
                        <option value="" <?= (($g['origem'] ?? '')==='' ? 'selected' : '') ?>>Selecione</option>
                        <option value="CGJ/MA" <?= (($g['origem'] ?? '')==='CGJ/MA' ? 'selected' : '') ?>>CGJ/MA</option>
                        <option value="CNJ"    <?= (($g['origem'] ?? '')==='CNJ'    ? 'selected' : '') ?>>CNJ</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="data_provimento">Data:</label>
                    <input type="date" class="form-control" id="data_provimento" name="data_provimento"
                           value="<?= e($g['data_provimento'] ?? '') ?>">
                </div>
                <div class="form-group col-md-6">
                    <label for="descricao">Descrição:</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="3"><?= e($g['descricao'] ?? '') ?></textarea>
                </div>
                <div class="form-group col-md-6">
                    <label for="conteudo_anexo">Conteúdo:</label>
                    <textarea class="form-control" id="conteudo_anexo" name="conteudo_anexo" rows="3"><?= e($g['conteudo_anexo'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="row mb-12">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary" style="width:100%;color:#fff!important">
                        <i class="fa fa-filter"></i> Filtrar
                    </button>
                </div>
            </div>
        </form>
        <hr>
        <!-- --------------------------- TABELA DE RESULTADOS --------------------------- -->
        <div class="table-responsive">
            <h5>Resultados da Pesquisa</h5>
            <table id="tabelaResultados" class="table table-striped table-bordered" style="zoom:100%">
                <thead>
                    <tr><th>Tipo</th><th>Nº</th><th>Origem</th><th>Data</th><th>Descrição</th><th>Ações</th></tr>
                </thead>
                <tbody>
<?php
/* ---------- PHP para listar os provimentos (inalterado) ---------- */
$conn = getDatabaseConnection();
$conditions=[]; $params=[]; $filtered=false;
if(!empty($_GET['numero_provimento'])){
    if(strpos($_GET['numero_provimento'],'/')!==false){
        list($numero,$ano)=explode('/',$_GET['numero_provimento']);
        $conditions[]='numero_provimento=:numero AND YEAR(data_provimento)=:ano';
        $params[':numero']=$numero; $params[':ano']=$ano;
    }else{ $conditions[]='numero_provimento=:numero'; $params[':numero']=$_GET['numero_provimento']; }
    $filtered=true;
}
if(!empty($_GET['origem']))           { $conditions[]='origem=:origem';                  $params[':origem']=$_GET['origem'];             $filtered=true; }
if(!empty($_GET['tipo']))             { $conditions[]='tipo=:tipo';                      $params[':tipo']  =$_GET['tipo'];               $filtered=true; }
if(!empty($_GET['ano']))              { $conditions[]='YEAR(data_provimento)=:ano';      $params[':ano']   =$_GET['ano'];                $filtered=true; }
if(!empty($_GET['data_provimento']))  { $conditions[]='data_provimento=:data_provimento';$params[':data_provimento']=$_GET['data_provimento']; $filtered=true; }
if(!empty($_GET['descricao']))        { $conditions[]='descricao LIKE :descricao';       $params[':descricao']='%'.$_GET['descricao'].'%'; $filtered=true; }
if(!empty($_GET['conteudo_anexo']))   { $conditions[]='conteudo_anexo LIKE :conteudo_anexo';$params[':conteudo_anexo']='%'.$_GET['conteudo_anexo'].'%'; $filtered=true; }

$sql='SELECT * FROM provimentos';
if($conditions) $sql.=' WHERE '.implode(' AND ',$conditions);
if(!$filtered)  $sql.=' ORDER BY data_provimento DESC';

$stmt=$conn->prepare($sql);
foreach($params as $k=>$v){ $stmt->bindValue($k,$v); }
$stmt->execute();
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $p){
    $numAno=$p['numero_provimento'].'/'.date('Y',strtotime($p['data_provimento']));?>
    <tr>
        <td><?=htmlspecialchars($p['tipo']);?></td>
        <td><?=htmlspecialchars($numAno);?></td>
        <td><?=htmlspecialchars($p['origem']);?></td>
        <td data-order="<?=date('Y-m-d',strtotime($p['data_provimento']));?>"><?=date('d/m/Y',strtotime($p['data_provimento']));?></td>
        <td><?=htmlspecialchars($p['descricao']);?></td>
        <td>
            <button class="btn btn-info btn-sm" style="margin-bottom:5px;font-size:20px;width:40px;height:40px;border-radius:5px;border:none"
                    title="Visualizar Provimento" onclick="visualizarProvimento('<?=$p['id'];?>')">
                <i class="fa fa-eye"></i>
            </button>
        </td>
    </tr>
<?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================================ MODAL DE VISUALIZAÇÃO ================================ -->
<div class="modal fade modal-modern" id="visualizarModal" tabindex="-1" role="dialog" aria-labelledby="visualizarModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document" aria-modal="true">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title" id="visualizarModalLabel">
          <i class="mdi mdi-file-document-outline"></i> <span class="title-text">Documento</span>
          <span class="badge badge-pill ml-2" id="tagTipo">—</span>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <!-- META BAR -->
        <div class="meta-bar">
          <div class="meta-item"><label>Tipo</label><div class="value" id="metaTipo">—</div></div>
          <div class="meta-item"><label>Número</label><div class="value" id="metaNumero">—</div></div>
          <div class="meta-item"><label>Origem</label><div class="value" id="metaOrigem">—</div></div>
          <div class="meta-item"><label>Data</label><div class="value" id="metaData">—</div></div>
        </div>
        <!-- TOOLBAR -->
        <div class="doc-toolbar">
          <div id="metaDescricaoWrapper" class="meta-desc" tabindex="0" title="Clique para expandir/recolher a descrição">
            <i class="mdi mdi-text-long"></i> <span id="metaDescricao">—</span>
          </div>
          <div class="pdf-search">
            <div class="input-group input-group-sm">
              <input type="text" id="pdfSearchInput" class="form-control" placeholder="Buscar no PDF">
              <div class="input-group-append">
                <button class="btn theme-outline btn-sm" id="pdfSearchBtn" title="Buscar no PDF">
                  <i class="mdi mdi-magnify"></i><span>Buscar</span>
                </button>
              </div>
            </div>
          </div>
          <div class="doc-actions">
            <button class="btn theme-outline btn-sm" id="btnOpenNew" title="Abrir em nova aba"><i class="mdi mdi-open-in-new"></i><span>Abrir</span></button>
            <button class="btn theme-outline btn-sm" id="btnDownload" title="Baixar documento"><i class="mdi mdi-download"></i><span>Baixar</span></button>
            <button class="btn theme-outline btn-sm" id="btnCopyLink" title="Copiar link"><i class="mdi mdi-link-variant"></i><span>Copiar link</span></button>
          </div>
        </div>
        <!-- VIEWER -->
        <div class="viewer-wrapper">
          <div id="docLoader" class="doc-loader"><i class="mdi mdi-loading mdi-spin mr-2"></i> Carregando documento…</div>
          <iframe id="anexo_visualizacao" class="viewer-frame" frameborder="0"></iframe>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ================================ SCRIPTS ================================ -->
<script>
const APP_BASE_URL     = <?php echo json_encode($appBase,    JSON_UNESCAPED_SLASHES); ?>;
const PDFJS_VIEWER_URL = <?php echo json_encode($viewerBase, JSON_UNESCAPED_SLASHES); ?>;
function toAbsoluteUrl(u){ return !u ? '' : (/^(https?:)?\/\//i.test(u) ? u : APP_BASE_URL + u.replace(/^\/+/,'') ); }
</script>
<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.dataTables.min.js"></script>
<script src="../script/dataTables.bootstrap4.min.js"></script>
<script src="../script/sweetalert2.js"></script>

<script>
$(document).ready(function(){
  $('#tabelaResultados').DataTable({language:{url:"../style/Portuguese-Brasil.json"},order:[[3,'desc']]});
  $('#metaDescricaoWrapper').on('click',e=>$(e.currentTarget).toggleClass('expanded'))
                            .on('mouseleave blur',e=>$(e.currentTarget).removeClass('expanded'));
  $('#pdfSearchBtn').on('click',triggerPdfSearch);
  $('#pdfSearchInput').on('keydown',e=>{ if(e.key==='Enter'){ e.preventDefault(); triggerPdfSearch(); }});
});

/* ---------- utilidades de download (mesmas) ---------- */
function composeDownloadName(p){
  const numero=p.numero_provimento||'';
  const ano   =p.ano_provimento||(p.data_provimento?new Date(p.data_provimento+'T00:00:00').getFullYear():'');
  const tipo  =p.tipo||'Documento';
  const origem=p.origem||'';
  return (`${tipo} nº ${numero}_${ano} - ${origem}`)
          .replace(/:/g,'').replace(/\//g,'_')
          .replace(/[<>:"/\\|?*\x00-\x1F]/g,'')
          .replace(/\s+/g,' ').trim().replace(/\.+$/,'');
}
async function baixarArquivo(url,nomeBase){
  try{
    const r=await fetch(url,{credentials:'same-origin'});
    if(!r.ok) throw new Error('HTTP '+r.status);
    const b=await r.blob();
    let ext=''; const ct=(b.type||'').toLowerCase();
    const m=url.split('?')[0].match(/\.[a-z0-9]+$/i);
    if(m) ext=m[0];
    else if(ct.includes('pdf')) ext='.pdf'; else if(ct.includes('jpeg')) ext='.jpg';
    else if(ct.includes('png')) ext='.png'; else if(ct.includes('gif')) ext='.gif';
    else if(ct.includes('msword')) ext='.doc'; else if(ct.includes('wordprocessingml')) ext='.docx';
    else if(ct.includes('spreadsheetml')) ext='.xlsx'; else if(ct.includes('csv')) ext='.csv';
    else if(ct.includes('rtf')) ext='.rtf'; else if(ct.includes('text')) ext='.txt';
    const o=URL.createObjectURL(b);
    const a=document.createElement('a'); a.href=o; a.download=nomeBase+ext; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(o);
  }catch(e){ Swal.fire({icon:'error',title:'Falha ao baixar',text:e.message||'Erro desconhecido'}); }
}

/* ---------- visualização ---------- */
let __currentPdfUrl='';
function visualizarProvimento(id){
  $('#docLoader').show(); $('#anexo_visualizacao').attr('src','about:blank');
  $.get('obter_provimento.php',{id},res=>{
    try{
      const p=typeof res==='object'?res:JSON.parse(res);
      const numAno=(p.numero_provimento||'')+'/'+(p.ano_provimento||'');
      $('#metaTipo').text(p.tipo||'—'); $('#metaNumero').text(numAno||'—'); $('#metaOrigem').text(p.origem||'—');
      $('#metaData').text(p.data_provimento?new Date(p.data_provimento+'T00:00:00').toLocaleDateString('pt-BR'):'—');
      $('#metaDescricao').text(p.descricao||'—'); $('#metaDescricaoWrapper').removeClass('expanded');
      $('#tagTipo').text(p.tipo||'Documento'); $('.title-text').text(`${p.tipo||'Documento'} nº: ${numAno} - ${p.origem||'—'}`);
      __currentPdfUrl=toAbsoluteUrl(p.caminho_anexo||'');
      $('#anexo_visualizacao').off('load').on('load',()=>$('#docLoader').fadeOut(150)).attr('src',__currentPdfUrl);
      const nomePadrao=composeDownloadName(p);
      $('#btnOpenNew').off('click').on('click',()=>{ if(__currentPdfUrl) window.open(__currentPdfUrl,'_blank'); });
      $('#btnDownload').off('click').on('click',()=>{ if(__currentPdfUrl) baixarArquivo(__currentPdfUrl,nomePadrao); });
      $('#btnCopyLink').off('click').on('click',async()=>{
          try{ await navigator.clipboard.writeText(__currentPdfUrl); Swal.fire({icon:'success',title:'Link copiado!',timer:1200,showConfirmButton:false});}
          catch{ Swal.fire({icon:'error',title:'Falha ao copiar link'});}
      });
      $('#visualizarModal').modal('show');
    }catch(e){ console.error(e); alert('Erro ao processar resposta do servidor.'); }
  }).fail(()=>alert('Erro ao obter os dados do provimento.'));
}

/* ---------- busca no PDF: agora usa phrase=true para buscar a frase exata ---------- */
function buildPdfJsViewerUrl(fileUrl,search){
  let u=PDFJS_VIEWER_URL+'?file='+encodeURIComponent(fileUrl);
  if(search&&search.trim()) u+='#search='+encodeURIComponent(search.trim())+'&phrase=true';
  return u;
}
function triggerPdfSearch(){
  const term=($('#pdfSearchInput').val()||'').trim();
  if(!__currentPdfUrl)           { Swal.fire({icon:'warning',title:'Nenhum documento aberto'}); return; }
  if(!term)                      { Swal.fire({icon:'warning',title:'Digite um termo ou frase'}); return; }
  $('#docLoader').show();
  $('#anexo_visualizacao').off('load').on('load',()=>$('#docLoader').fadeOut(150))
                         .attr('src',buildPdfJsViewerUrl(__currentPdfUrl,term));
}

/* ---------- ajuste de altura e validação de data (inalterados) ---------- */
function ajustarAlturaViewer(){
  const $c=$('#visualizarModal .modal-content');
  const h=$('#visualizarModal .modal-header').outerHeight(true)||0;
  const f=$('#visualizarModal .modal-footer').outerHeight(true)||0;
  $('#visualizarModal .modal-body').height(($c.height()||0)-h-f);
}
$('#visualizarModal').on('shown.bs.modal',ajustarAlturaViewer);
$(window).on('resize',()=>{ if($('#visualizarModal').hasClass('show')) ajustarAlturaViewer(); });

$(document).ready(function(){
  const currentYear=new Date().getFullYear();
  $('#data_provimento').on('change',function(){
    const sel=new Date(this.value);
    if(sel.getFullYear()>currentYear){
      Swal.fire({icon:'warning',title:'Data inválida',text:'O ano não pode ser maior que o ano atual.'});
      this.value='';
    }
  });
});
</script>

<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
