<?php
include(__DIR__ . '/session_check.php');
include(__DIR__ . '/db_connection.php');
checkSession();
date_default_timezone_set('America/Sao_Paulo');
header('Permissions-Policy: clipboard-write=(self)');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Atlas - Editar Arquivamento</title>
  <link rel="stylesheet" href="../style/css/bootstrap.min.css">
  <link rel="stylesheet" href="../style/css/font-awesome.min.css">
  <link rel="stylesheet" href="../style/css/style.css">
  <link rel="icon" href="../style/img/favicon.png" type="image/png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <?php include(__DIR__ . '/../style/style_edit_arquivamento.php');?>
  <style>
    textarea.form-control {
        height: 170px;
    }
  </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');

/* ==========================
   Carrega selo
========================== */
$usuarioLogado = $_SESSION['nome_completo'];
$arquivo_id = $_GET['id'];
$selos_arquivamentos = $conn->prepare("SELECT selos.* FROM selos_arquivamentos JOIN selos ON selos_arquivamentos.selo_id = selos.id WHERE selos_arquivamentos.arquivo_id = ? ORDER BY selos.id ASC");
$selos_arquivamentos->bind_param("i", $arquivo_id);
$selos_arquivamentos->execute();
$selos_arquivamentos_result = $selos_arquivamentos->get_result();
$selo_existe = $selos_arquivamentos_result->num_rows > 0;

$selos_html = '';
if ($selo_existe) {
    while ($selo = $selos_arquivamentos_result->fetch_assoc()) {
        $texto_sanitizado = nl2br(htmlspecialchars($selo['texto_selo'] ?? '', ENT_QUOTES, 'UTF-8'));
        $numero_selo = htmlspecialchars($selo['numero_selo'] ?? '', ENT_QUOTES, 'UTF-8');
        $qr = $selo['qr_code'] ?? '';

        $selos_html .= '<div class="seal-wrapper" style="margin-bottom:10px">';
        $selos_html .= '  <div class="seal-card">';
        $selos_html .= '    <div class="seal-head">';
        $selos_html .= '      <div class="seal-title">Poder Judiciário – TJMA</div>';
        $selos_html .= '      <span class="seal-pill"><i class="fa fa-check-circle"></i> Selo gerado</span>';
        $selos_html .= '    </div>';
        $selos_html .= '    <div class="seal-grid">';
        $selos_html .= '      <div class="seal-qr"><img src="data:image/png;base64,'. $qr .'" alt="QR Code do selo"></div>';
        $selos_html .= '      <div class="seal-meta">';
        $selos_html .= '        <div class="seal-number">Selo: <b>'. $numero_selo .'</b></div>';
        $selos_html .= '        <p class="seal-text">'. $texto_sanitizado .'</p>';
        $selos_html .= '        <div class="seal-actions">';
        $selos_html .= '          <button type="button" class="seal-copy-btn" data-copy="'. $numero_selo .'"><i class="fa fa-clone"></i> Copiar número</button>';
        $selos_html .= '        </div>';
        $selos_html .= '      </div>';
        $selos_html .= '    </div>';
        $selos_html .= '  </div>';
        $selos_html .= '</div>';
    }
}
$selos_arquivamentos->close();
?>

<div id="main" class="main-content">
  <div class="container">

    <!-- HERO -->
    <section class="page-hero">
      <div class="row-top">
        <div class="hero-left">
          <div class="title-icon"><i class="fa fa-edit"></i></div>
          <div>
            <h1>Edição de Arquivamento</h1>
            <div class="muted">Atualize dados, gerencie partes e anexos.</div>
          </div>
        </div>
        <div class="hero-actions">
          <button type="button" class="btn btn-primary" id="capaArquivamentoButton">
            <i class="fa fa-print" aria-hidden="true"></i> Capa de Arquivamento
          </button>
          <button type="button" class="btn btn-success" onclick="window.location.href='cadastro.php'">
            <i class="fa fa-plus" aria-hidden="true"></i> Criar Arquivamento
          </button>
          <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
            <i class="fa fa-search" aria-hidden="true"></i> Pesquisar
          </button>
        </div>
      </div>
    </section>

    <form id="ato-form">
      <!-- Corrigido: id do campo hidden agora é "id" para bater com o JS -->
      <input type="hidden" id="id" name="id">

      <!-- DADOS DO ATO -->
      <div class="form-card">
        <h4>Dados do Ato</h4>
        <div class="row g-grid">
          <div class="col-md-3">
            <label for="atribuicao">Atribuição</label>
            <select id="atribuicao" name="atribuicao" class="form-control" required>
              <option value="">Selecione</option>
              <option value="Registro Civil">Registro Civil</option>
              <option value="Registro de Imóveis">Registro de Imóveis</option>
              <option value="Registro de Títulos e Documentos">Registro de Títulos e Documentos</option>
              <option value="Registro Civil das Pessoas Jurídicas">Registro Civil das Pessoas Jurídicas</option>
              <option value="Notas">Notas</option>
              <option value="Protesto">Protesto</option>
              <option value="Contratos Marítimos">Contratos Marítimos</option>
              <option value="Administrativo">Administrativo</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="categoria">Categoria</label>
            <select id="categoria" name="categoria" class="form-control" required></select>
          </div>
          <div class="col-md-3">
            <label for="data_ato">Data do Ato</label>
            <input type="date" class="form-control" id="data_ato" name="data_ato" required>
          </div>
          <div class="col-md-3">
            <label for="livro">Livro</label>
            <input type="text" class="form-control" id="livro" name="livro">
          </div>
          <div class="col-md-3">
            <label for="folha">Folha</label>
            <input type="text" class="form-control" id="folha" name="folha">
          </div>
          <div class="col-md-3">
            <label for="termo">Termo/Ordem</label>
            <input type="text" class="form-control" id="termo" name="termo">
          </div>
          <div class="col-md-3">
            <label for="protocolo">Protocolo</label>
            <input type="text" class="form-control" id="protocolo" name="protocolo">
          </div>
          <div class="col-md-3">
            <label for="matricula">Matrícula</label>
            <input type="text" class="form-control" id="matricula" name="matricula">
          </div>
        </div>
      </div>

      <!-- PARTES ENVOLVIDAS -->
      <div class="form-card">
        <h4>Parte Envolvida</h4>
        <div class="row g-grid">
          <div class="col-md-4">
            <label for="cpf">CPF/CNPJ</label>
            <input type="text" class="form-control" id="cpf" placeholder="CPF/CNPJ">
          </div>
          <div class="col-md-8">
            <label for="nome">Nome</label>
            <input type="text" class="form-control" id="nome" placeholder="Nome completo">
          </div>
        </div>
        <div class="d-flex justify-content-end">
          <button type="button" class="btn btn-secondary" id="adicionar-parte">
            <i class="fa fa-user-plus"></i> Adicionar Parte
          </button>
        </div>

        <div class="table-responsive mt-3">
          <table class="table mb-0">
            <thead>
              <tr>
                <th style="min-width:140px">CPF/CNPJ</th>
                <th>Nome</th>
                <th style="width:80px">Ação</th>
              </tr>
            </thead>
            <tbody id="partes-envolvidas"></tbody>
          </table>
        </div>
      </div>

      <!-- DESCRIÇÃO -->
      <div class="form-card">
        <h4>Descrição e Detalhes</h4>
        <div class="form-group">
          <label for="descricao">Descrição e Detalhes</label>
          <textarea class="form-control" id="descricao" name="descricao" rows="3" placeholder="Descreva brevemente o ato"></textarea>
        </div>
      </div>

      <!-- ANEXAR NOVOS ARQUIVOS (DROPZONE) -->
      <div class="form-card">
        <h4>Anexos (adicionar novos)</h4>
        <div id="dropzone" class="dropzone" tabindex="0" role="button" aria-label="Área para arrastar e soltar arquivos">
          <i class="fa fa-cloud-upload dz-icon" aria-hidden="true"></i>
          <div class="dz-title">Arraste e solte os arquivos aqui</div>
          <div class="dz-help">ou</div>
          <button type="button" id="btnBrowse" class="dz-btn">
            <i class="fa fa-folder-open"></i> Selecionar arquivos
          </button>
          <input type="file" id="file-input" name="file-input[]" multiple class="sr-only" hidden>
        </div>
        <div id="filesList" class="files-list" aria-live="polite"></div>
      </div>

      <button type="submit" id="btnSubmitEdit" class="btn btn-primary w-100" style="margin-top:0;margin-bottom:30px">
        <i class="fa fa-save"></i> Salvar
      </button>
    </form>
<!-- Overlay de Upload (progresso) -->
    <div id="uploadOverlay" class="upload-overlay" aria-hidden="true" aria-live="polite">
      <div class="upload-card" role="dialog" aria-modal="true" aria-label="Progresso do envio">
        <div class="upload-title">
          <i class="fa fa-cloud-upload" aria-hidden="true"></i>
          <span id="uploadTitle">Enviando anexos…</span>
        </div>
        <div class="upload-subtitle" id="uploadSubtitle">Preparando envio…</div>
        <div class="progress">
          <div id="uploadBar" class="progress-bar bg-primary" style="width:0%"></div>
        </div>
        <div class="progress-row">
          <div class="progress-label" id="uploadLabel">Upload</div>
          <div class="progress-value" id="uploadValue">0%</div>
        </div>
        <div class="upload-footer">Por favor, não feche a página até finalizar.</div>
      </div>
    </div>

    <div class="soft-divider"></div>

    <!-- ANEXOS EXISTENTES -->
    <div class="form-card">
      <h4>Anexos</h4>
      <div id="file-list" class="attachments-grid"></div>
    </div>

    <!-- FORMULÁRIO DE SELO / SELO GERADO -->
    <?php if (!$selo_existe): ?>
  <div class="form-card seal-wrapper">
    <div class="d-flex align-items-center justify-content-between">
      <h4>Solicitar Selo</h4>
      <button type="button" id="btnAddSelo" class="btn btn-primary" style="display:none;">
        <i class="fa fa-plus"></i> Adicionar mais selo
      </button>
    </div>
    <p class="hint">Informe a quantidade e confirme para gerar o selo. Os demais campos são preenchidos automaticamente.</p>

    <form method="post" id="selo-form" class="selo-form row g-grid">
      <input type="hidden" name="numeroControle" value="<?php echo $arquivo_id; ?>">
      <input type="hidden" name="livro" id="livro_selo" value="">
      <input type="hidden" name="folha" id="folha_selo" value="">
      <input type="hidden" name="termo" id="termo_selo" value="">

      <div class="col-12 col-lg-6">
        <label for="ato">Ato</label>
        <select id="ato" name="ato" class="form-control" required>
          <option value="13.30">13.30 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,55</option>
          <option value="14.12">14.12 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,55</option>
          <option value="15.22">15.22 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,55</option>
          <option value="16.39">16.39 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,55</option>
          <option value="17.9">17.9 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,42</option>
          <option value="18.13">18.13 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,55</option>
        </select>
      </div>

      <div class="col-md-3" style="display:none;">
        <label for="escrevente">Escrevente</label>
        <input type="text" id="escrevente" name="escrevente" class="form-control" required readonly value="<?php echo utf8_encode($usuarioLogado); ?>">
      </div>
      <div class="col-md-6" style="display:none;">
        <label for="partes">Partes</label>
        <input id="partes" name="partes" class="form-control" required />
      </div>

      <div class="col-6 col-md-3 col-lg-2">
        <label for="quantidade">Quantidade</label>
        <input type="number" id="quantidade" name="quantidade" class="form-control" min="1" placeholder="0" required>
      </div>

      <div class="col-6 col-md-3 col-lg-2">
        <label class="d-block">Selo isento?</label>
        <label class="switch" aria-label="Marcar como isento">
          <input type="checkbox" id="isento" name="isento">
          <span class="slider"></span>
          <span class="switch-text">Isento</span>
        </label>
      </div>

      <div class="col-12" id="motivo-wrapper" style="display:none;">
        <label for="motivo_isencao">Motivo da isenção</label>
        <input type="text" id="motivo_isencao" name="motivo_isencao" class="form-control" placeholder="Descreva o motivo da isenção">
      </div>

      <div class="col-12 col-md-4 col-lg-2">
        <label class="d-none d-md-block">&nbsp;</label>
        <button type="submit" id="solicitar-selo-btn" class="btn btn-primary w-100">
          Solicitar Selo
        </button>
      </div>
    </form>

    <!-- container para receber os selos gerados -->
    <div id="selos-container" class="mt-2"></div>
  </div>
<?php else: ?>
<div class="form-card seal-wrapper">
    <div class="d-flex align-items-center justify-content-between">
    <h4>Selos deste arquivamento</h4>
    <button type="button" id="btnAddSelo" class="btn btn-primary">
        <i class="fa fa-plus"></i> Adicionar mais selo
    </button>
    </div>
    <div id="selos-container" class="mt-2">
    <?php echo $selos_html; ?>
    </div>

    <!-- Form de novo selo (inicialmente oculto) -->
    <form method="post" id="selo-form" class="selo-form row g-grid" style="display:none; margin-top:12px;">
    <input type="hidden" name="numeroControle" value="<?php echo $arquivo_id; ?>">
    <input type="hidden" name="livro" id="livro_selo" value="">
    <input type="hidden" name="folha" id="folha_selo" value="">
    <input type="hidden" name="termo" id="termo_selo" value="">

    <div class="col-12 col-lg-6">
        <label for="ato">Ato</label>
        <select id="ato" name="ato" class="form-control" required>
        <option value="13.30">13.30 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,55</option>
        <option value="14.12">14.12 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,55</option>
        <option value="15.22">15.22 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,55</option>
        <option value="16.39">16.39 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,55</option>
        <option value="17.9">17.9 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,42</option>
        <option value="18.13">18.13 - Arquivamento, por folha do documento, os emolumentos serão: R$ 6,55</option>
        </select>
    </div>

    <div class="col-md-3" style="display:none;">
        <label for="escrevente">Escrevente</label>
        <input type="text" id="escrevente" name="escrevente" class="form-control" required readonly value="<?php echo utf8_encode($usuarioLogado); ?>">
    </div>
    <div class="col-md-6" style="display:none;">
        <label for="partes">Partes</label>
        <input id="partes" name="partes" class="form-control" required />
    </div>

    <div class="col-6 col-md-3 col-lg-2">
        <label for="quantidade">Quantidade</label>
        <input type="number" id="quantidade" name="quantidade" class="form-control" min="1" placeholder="0" required>
    </div>

    <div class="col-6 col-md-3 col-lg-2">
        <label class="d-block">Selo isento?</label>
        <label class="switch" aria-label="Marcar como isento">
        <input type="checkbox" id="isento" name="isento">
        <span class="slider"></span>
        <span class="switch-text">Isento</span>
        </label>
    </div>

    <div class="col-12" id="motivo-wrapper" style="display:none;">
        <label for="motivo_isencao">Motivo da isenção</label>
        <input type="text" id="motivo_isencao" name="motivo_isencao" class="form-control" placeholder="Descreva o motivo da isenção">
    </div>

    <div class="col-12 col-md-4 col-lg-2">
        <label class="d-none d-md-block">&nbsp;</label>
        <button type="submit" id="solicitar-selo-btn" class="btn btn-primary w-100">
        Solicitar Selo
        </button>
    </div>
    </form>
</div>
<?php endif; ?>


  </div>
</div>

<!-- Modal de mensagem -->
<div class="modal fade" id="messageModal" tabindex="-1" role="dialog" aria-labelledby="messageModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="messageModalLabel"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="messageModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL – PREVIEW DE ANEXOS -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">
          <i class="fa fa-eye" aria-hidden="true"></i>
          <span>Visualização do Anexo</span>
        </div>
        <div class="preview-toolbar d-flex align-items-center gap-2">
          <a id="btnOpenNewTab" class="btn btn-sm" target="_blank" rel="noopener">
            <i class="fa fa-external-link"></i> Abrir em nova aba
          </a>
          <a id="btnDownload" class="btn btn-sm" download>
            <i class="fa fa-download"></i> Baixar
          </a>
          <button type="button" class="btn btn-sm btn-close-lg" data-dismiss="modal" aria-label="Fechar">
            <i class="fa fa-times"></i> Fechar
          </button>
        </div>
      </div>
      <div id="previewContainer">
        <iframe id="previewFrame"></iframe>
        <img id="previewImage" alt="Pré-visualização do anexo">
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="../script/jquery-3.6.0.min.js"></script>
<script src="../script/bootstrap.bundle.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<!-- SweetAlert2 JS via CDN (garante compatibilidade com o CSS) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
/* ======================== Utils ======================== */
function bytesToSize(bytes){
  if (bytes === 0) return '0 B';
  const k = 1024, sizes = ['B','KB','MB','GB','TB'];
  const i = Math.floor(Math.log(bytes)/Math.log(k));
  return parseFloat((bytes/Math.pow(k,i)).toFixed(2)) + ' ' + sizes[i];
}
function getExt(name){ return (name.split('.').pop()||'').toLowerCase(); }
function fileExt(path){
  const p = (path||'').split('?')[0].split('#')[0];
  return (p.split('.').pop()||'').toLowerCase();
}
function extIcon(ext){
  ext = (ext||'').toLowerCase();
  if(['jpg','jpeg','png','gif','webp','bmp','tiff'].includes(ext)) return 'fa-file-image-o';
  if(['pdf'].includes(ext)) return 'fa-file-pdf-o';
  if(['doc','docx'].includes(ext)) return 'fa-file-word-o';
  if(['xls','xlsx','csv'].includes(ext)) return 'fa-file-excel-o';
  if(['ppt','pptx'].includes(ext)) return 'fa-file-powerpoint-o';
  if(['zip','rar','7z'].includes(ext)) return 'fa-file-archive-o';
  if(['txt','md','rtf'].includes(ext)) return 'fa-file-text-o';
  return 'fa-file-o';
}
function isPdf(ext){ return ext==='pdf'; }
function isImage(ext){ return ['jpg','jpeg','png','gif','webp','bmp','tiff'].includes(ext); }

/* ===== Helpers para viewer PDF.js ===== */
// Converte caminho/URL (relativa ou absoluta) em URL absoluta
function toAbsoluteUrl(u){
  try { return new URL(u, window.location.href).href; }
  catch(e){ return u; }
}
function buildPdfViewerUrl(absFileUrl){
  const viewerBase = '../provimentos/pdfjs/web/viewer.html';
  return viewerBase + '?file=' + encodeURIComponent(absFileUrl);
}

/* ======================== Capa de Arquivamento ======================== */
$(document).on('click','#capaArquivamentoButton', function(){
  $.ajax({
    url: '../style/configuracao.json',
    dataType: 'json',
    cache: false,
    success: function(data) {
      const arquivoId = '<?php echo $arquivo_id; ?>';
      const url = (data.timbrado === 'S')
        ? 'capa_arquivamento.php?id=' + arquivoId
        : 'capa-arquivamento.php?id=' + arquivoId;
      window.open(url, '_blank');
    },
    error: function(){ alert('Erro ao carregar o arquivo de configuração.'); }
  });
});

/* ======================== Dropzone (novos anexos) ======================== */
const dzFiles = [];
function renderDzList(){
  const $list = $('#filesList').empty();
  if (!dzFiles.length) return;
  dzFiles.forEach((f,idx)=>{
    const ext = getExt(f.name);
    $list.append(
      `<div class="file-item" data-idx="${idx}">
        <div class="file-icon"><i class="fa ${extIcon(ext)}"></i></div>
        <div class="file-meta">
          <div class="file-name" title="${f.name}">${f.name}</div>
          <div class="file-size">${bytesToSize(f.size)}</div>
        </div>
        <button type="button" class="file-remove" title="Remover" aria-label="Remover arquivo">
          <i class="fa fa-times"></i>
        </button>
      </div>`
    );
  });
}
function addDzFiles(fileList){
  Array.from(fileList).forEach(file=>{
    const key = file.name + '|' + file.size;
    const exists = dzFiles.some(f => (f.name+'|'+f.size) === key);
    if (!exists){ dzFiles.push(file); }
  });
  renderDzList();
}

$(function(){
  const $drop = $('#dropzone');
  const $browse = $('#btnBrowse');
  const $input = $('#file-input');

  $browse.on('click', ()=> $input.trigger('click'));
  $input.on('change', function(){ addDzFiles(this.files); this.value=''; });

  ['dragenter','dragover'].forEach(ev=>{
    $drop.on(ev, function(e){ e.preventDefault(); e.stopPropagation(); $(this).addClass('dragover'); });
  });
  ['dragleave','drop'].forEach(ev=>{
    $drop.on(ev, function(e){ e.preventDefault(); e.stopPropagation(); if(ev==='dragleave') $(this).removeClass('dragover'); });
  });
  $drop.on('drop', function(e){
    $(this).removeClass('dragover');
    const dt = e.originalEvent.dataTransfer;
    if (dt && dt.files && dt.files.length) addDzFiles(dt.files);
  });
  $drop.on('keydown', function(e){
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $input.trigger('click'); }
  });
  $('#filesList').on('click','.file-remove', function(){
    const idx = +$(this).closest('.file-item').data('idx');
    dzFiles.splice(idx,1);
    renderDzList();
  });
});

/* ======================== Partes envolvidas ======================== */
function validarCPF_CNPJ(value) {
  const digitsOnly = (value||'').replace(/[^\d]/g,'');
  if (!digitsOnly) return false;
  if (digitsOnly.length === 11) {
    if (/^(\d)\1{10}$/.test(digitsOnly)) return false;
    let soma=0, resto;
    for (let i=1;i<=9;i++) soma += parseInt(digitsOnly.substring(i-1,i))*(11-i);
    resto = (soma*10)%11; if (resto===10||resto===11) resto=0;
    if (resto!==parseInt(digitsOnly.substring(9,10))) return false;
    soma=0;
    for (let i=1;i<=10;i++) soma+= parseInt(digitsOnly.substring(i-1,i))*(12-i);
    resto = (soma*10)%11; if (resto===10||resto===11) resto=0;
    if (resto!==parseInt(digitsOnly.substring(10,11))) return false;
    return true;
  }
  if (digitsOnly.length === 14) {
    if (/^(\d)\1{13}$/.test(digitsOnly)) return false;
    let tamanho = digitsOnly.length-2;
    let numeros = digitsOnly.substring(0,tamanho);
    let digitos = digitsOnly.substring(tamanho);
    let soma = 0, pos = tamanho-7;
    for (let i=tamanho; i>=1; i--){
      soma += numeros.charAt(tamanho-i)*pos--; if (pos<2) pos=9;
    }
    let resultado = soma%11<2?0:11-soma%11;
    if (resultado!==parseInt(digitos.charAt(0))) return false;

    tamanho = tamanho+1; numeros = digitsOnly.substring(0,tamanho); soma=0; pos=tamanho-7;
    for (let i=tamanho; i>=1; i--){
      soma += numeros.charAt(tamanho-i)*pos--; if (pos<2) pos=9;
    }
    resultado = soma%11<2?0:11-soma%11;
    if (resultado!==parseInt(digitos.charAt(1))) return false;
    return true;
  }
  return false;
}

$(document).on('blur','#cpf', function(){
  var value = $(this).val().replace(/\D/g,'');
  if (value.length === 11) $(this).mask('000.000.000-00', { reverse: true });
  else if (value.length === 14) $(this).mask('00.000.000/0000-00', { reverse: true });
  else $(this).unmask();
});

$(document).on('click','#adicionar-parte', function(){
  var cpf = $('#cpf').val();
  var nome = $('#nome').val().trim();

  if (!nome){
    Swal.fire({ icon:'warning', title:'Atenção!', text:'Preencha o nome.', confirmButtonText:'OK' });
    return;
  }
  if (cpf && !validarCPF_CNPJ(cpf)){
    Swal.fire({ icon:'error', title:'Erro!', text:'CPF/CNPJ inválido.', confirmButtonText:'OK' });
    return;
  }

  var row = '<tr>'+
      '<td>'+ (cpf || '') +'</td>'+
      '<td>'+ nome +'</td>'+
      '<td><button type="button" class="btn btn-delete btn-sm remover-parte" title="Remover"><i class="fa fa-trash" aria-hidden="true"></i></button></td>'+
    '</tr>';
  $('#partes-envolvidas').append(row);
  $('#cpf').val(''); $('#nome').val('').focus();
});

$(document).on('click','.remover-parte', function(e){
  e.preventDefault();
  var row = $(this).closest('tr');
  Swal.fire({
    title: 'Você tem certeza?',
    text: 'Deseja realmente remover esta parte envolvida?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Sim, remover',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      row.remove();
      Swal.fire({ icon:'success', title:'Removido!', text:'A parte envolvida foi removida com sucesso.', confirmButtonText:'OK' });
    }
  });
});

/* ======================== Carregar categorias e dados do ato ======================== */
$(function(){
  // Carrega categorias primeiro
  $.ajax({
    url: 'categorias/categorias.json',
    method: 'GET',
    dataType: 'json',
    success: function(response) {
      var categoriaSelect = $('#categoria');
      categoriaSelect.empty();
      response.forEach(function(categoria) {
        categoriaSelect.append($('<option>', { value: categoria, text: categoria }));
      });

      // Depois carrega o ato
      const atoId = new URLSearchParams(window.location.search).get('id');
      $.ajax({
        url: 'get_ato.php',
        method: 'GET',
        data: { id: atoId },
        success: function(response) {
          var ato;
          try{ ato = JSON.parse(response); }catch(e){ alert('Ato não encontrado.'); return; }
          if (!ato){ alert('Ato não encontrado.'); return; }

          $('#id').val(ato.id);
          $('#atribuicao').val(ato.atribuicao);
          $('#categoria').val(ato.categoria);
          $('#data_ato').val(ato.data_ato);
          $('#livro').val(ato.livro);
          $('#folha').val(ato.folha);
          $('#termo').val(ato.termo);
          $('#protocolo').val(ato.protocolo);
          $('#matricula').val(ato.matricula);
          $('#descricao').val(ato.descricao);

          var partesEnvolvidas = [];
          (ato.partes_envolvidas||[]).forEach(function(parte) {
            var row = '<tr>'+
              '<td>' + (parte.cpf||'') + '</td>'+
              '<td>' + (parte.nome||'') + '</td>'+
              '<td><button class="btn btn-delete btn-sm remover-parte" type="button"><i class="fa fa-trash" aria-hidden="true"></i></button></td>'+
            '</tr>';
            $('#partes-envolvidas').append(row);
            if (parte.nome) partesEnvolvidas.push(parte.nome);
          });

          // Preenche "Partes" no form de selo
          $('#partes').val(partesEnvolvidas.join(", "));

          // Renderiza anexos existentes (do próprio arquivamento) e anexos vindos das tarefas
          const $grid = $('#file-list').empty();
          let seq = 0;

          // 1) Anexos do próprio arquivamento
          (ato.anexos||[]).forEach(function(anexo) {
            seq++;
            const name = anexo.split('/').pop();
            const ext = fileExt(anexo);
            $grid.append(
              `<div class="attachment-card">
                <div class="att-icon"><i class="fa ${extIcon(ext)}"></i></div>
                <div>
                  <div class="attachment-name">${name}</div>
                  <small class="muted">#${seq}</small>
                </div>
                <div class="attachment-actions">
                  <button class="btn btn-info btn-sm visualizar-anexo" data-file="${anexo}" title="Visualizar"><i class="fa fa-eye"></i></button>
                  <button class="btn btn-delete btn-sm remover-anexo" data-file="${anexo}" title="Remover"><i class="fa fa-trash"></i></button>
                </div>
              </div>`
            );
          });

          // 2) Anexos que vieram das tarefas (abrir em ../tarefas/arquivos/...)
          (ato.anexos_tarefa||[]).forEach(function(anexo) {
            seq++;
            const rel = (anexo||'').replace(/^\/+/, '');   // remove / inicial se houver
            const url = '../tarefas/' + rel;               // monta ../tarefas/arquivos/...
            const name = rel.split('/').pop();
            const ext = fileExt(rel);
            $grid.append(
              `<div class="attachment-card">
                <div class="att-icon"><i class="fa ${extIcon(ext)}"></i></div>
                <div>
                  <div class="attachment-name">${name} <span class="badge badge-secondary" style="margin-left:6px;">Tarefa</span></div>
                  <small class="muted">#${seq}</small>
                </div>
                <div class="attachment-actions">
                  <button class="btn btn-info btn-sm visualizar-anexo" data-file="${url}" title="Visualizar"><i class="fa fa-eye"></i></button>
                </div>
              </div>`
            );
          });

          // Define o "Ato" conforme atribuição
          var atribuicao = ato.atribuicao;
          var atoField = $('#ato');
          switch (atribuicao) {
            case 'Registro Civil': atoField.val('14.12'); break;
            case 'Registro de Imóveis': atoField.val('16.39'); break;
            case 'Registro de Títulos e Documentos':
            case 'Registro Civil das Pessoas Jurídicas': atoField.val('15.22'); break;
            case 'Notas': atoField.val('13.30'); break;
            case 'Protesto': atoField.val('17.9'); break;
            case 'Contratos Marítimos': atoField.val('18.13'); break;
            default: atoField.val(''); break;
          }
        }
      });
    }
  });
});

/* ======================== Remover anexos existentes ======================== */
var filesToRemove = [];
$(document).on('click', '.remover-anexo', function() {
  var button = $(this);
  var anexo = button.data('file');
  Swal.fire({
    title: 'Você tem certeza?',
    text: 'Deseja realmente remover este anexo?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Sim, remover',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      button.closest('.attachment-card').remove();
      filesToRemove.push(anexo);
      Swal.fire({ icon:'success', title:'Removido!', text:'O anexo foi removido com sucesso.', confirmButtonText:'OK' });
    }
  });
});

/* ======================== Preview de anexos em modal ======================== */
function openPreview(url){
  const ext = fileExt(url);
  const $frame = $('#previewFrame');
  const $img   = $('#previewImage');

  // reset
  $frame.hide().attr('src','');
  $img.hide().attr('src','');

  if (isPdf(ext)) {
    const abs = toAbsoluteUrl(url);
    const viewerUrl = buildPdfViewerUrl(abs);

    $('#btnOpenNewTab').attr('href', viewerUrl);
    $('#btnDownload').attr('href', abs);

    $frame.attr('src', viewerUrl).show();
    $('#previewModal').modal('show');
    return;
  }

  if (isImage(ext)) {
    $('#btnOpenNewTab').attr('href', url);
    $('#btnDownload').attr('href', url);
    $img.attr('src', url).show();
    $('#previewModal').modal('show');
    return;
  }

  window.open(toAbsoluteUrl(url), '_blank');
}

$(document).on('click', '.visualizar-anexo', function(e) {
  e.preventDefault();
  var url = $(this).data('file');
  var ext = fileExt(url);
  if (isPdf(ext) || isImage(ext)) {
    openPreview(url);
  } else {
    window.open(url, '_blank');
  }
});

// Limpa recursos ao fechar
$('#previewModal').on('hidden.bs.modal', function () {
  $('#previewFrame').attr('src','').hide();
  $('#previewImage').attr('src','').hide();
});

/* ===== Overlay helpers (progresso de upload) ===== */
const $overlay   = $('#uploadOverlay');
const $bar       = $('#uploadBar');
const $value     = $('#uploadValue');
const $title     = $('#uploadTitle');
const $subtitle  = $('#uploadSubtitle');

function showOverlay(title, subtitle) {
  if (title)    $title.text(title);
  if (subtitle) $subtitle.text(subtitle);
  $bar.css('width','0%');
  $value.text('0%');
  $overlay.fadeIn(150).attr('aria-hidden','false');
}
function updateOverlay(percent) {
  const p = Math.max(0, Math.min(100, Math.round(percent)));
  $bar.css('width', p + '%');
  $value.text(p + '%');
  if (p >= 100) $subtitle.text('Processando…');
}
function hideOverlay() {
  $overlay.fadeOut(150).attr('aria-hidden','true');
}
function beforeUnloadGuard(e){
  e.preventDefault();
  e.returnValue = '';
}
let isSubmittingEdit = false;

/* ======================== Envio do formulário (com progresso) ======================== */
$(document).on('submit','#ato-form', function(e){
  e.preventDefault();
  if (isSubmittingEdit) return;
  isSubmittingEdit = true;

  if ($('#partes-envolvidas tr').length === 0) {
    isSubmittingEdit = false;
    Swal.fire({ icon:'error', title:'Erro!', text:'Adicione pelo menos uma parte envolvida.', confirmButtonText:'OK' });
    return;
  }

  const formData = new FormData();
  ['id','atribuicao','categoria','data_ato','livro','folha','termo','protocolo','matricula','descricao'].forEach(id=>{
    formData.append(id, $('#'+id).val());
  });

  const partes = [];
  $('#partes-envolvidas tr').each(function(){
    const cpf  = $(this).find('td').eq(0).text();
    const nome = $(this).find('td').eq(1).text();
    partes.push({ cpf, nome });
  });
  formData.append('partes_envolvidas', JSON.stringify(partes));

  // Novos anexos
  dzFiles.forEach(f=> formData.append('file-input[]', f));
  // Anexos marcados para remoção
  formData.append('files_to_remove', JSON.stringify(filesToRemove));

  // Mostrar overlay (sempre). Exibe “Enviando” quando houver anexos, “Salvando…” quando não houver.
  const hasFiles = dzFiles.length > 0;
  const totalSize = hasFiles ? dzFiles.reduce((acc, f) => acc + (f.size||0), 0) : 0;
  showOverlay(hasFiles ? 'Enviando anexos…' : 'Salvando…',
              hasFiles ? (totalSize ? 'Tamanho total: ' + bytesToSize(totalSize) : 'Preparando envio…')
                       : 'Aguarde um instante.');

  // Desabilita botão e evita navegação
  $('#btnSubmitEdit').prop('disabled', true).addClass('disabled');
  window.addEventListener('beforeunload', beforeUnloadGuard);

  $.ajax({
    url: 'update_ato.php',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    // Progresso real do envio
    xhr: function(){
      const xhr = new window.XMLHttpRequest();
      xhr.upload.addEventListener('progress', function(evt){
        if (!hasFiles) return;
        if (evt.lengthComputable) {
          const percent = (evt.loaded / evt.total) * 100;
          updateOverlay(percent);
        } else {
          // quando não for computável, faça um avanço suave até 95%
          const current = parseInt($('#uploadValue').text(), 10) || 0;
          updateOverlay(Math.min(current + 1, 95));
        }
      });
      return xhr;
    },
    success: function(response) {
      hideOverlay();
      window.removeEventListener('beforeunload', beforeUnloadGuard);
      Swal.fire({ icon:'success', title:'Sucesso!', text:'Dados salvos com sucesso.', confirmButtonText:'OK' })
        .then(()=> { location.reload(); });
    },
    error: function() {
      hideOverlay();
      window.removeEventListener('beforeunload', beforeUnloadGuard);
      Swal.fire({ icon:'error', title:'Erro!', text:'Erro ao salvar os dados.', confirmButtonText:'OK' });
    },
    complete: function(){
      isSubmittingEdit = false;
      $('#btnSubmitEdit').prop('disabled', false).removeClass('disabled');
    }
  });
});


/* ======================== Form de Selo ======================== */
// Exibe/oculta campo de motivo conforme checkbox "isento"
$(document).on('change', '#isento', function(){
  const checked = this.checked;
  $('#motivo-wrapper').toggle(checked);
  if (checked) {
    $('#motivo_isencao').attr('required', true);
  } else {
    $('#motivo_isencao').removeAttr('required').val('');
  }
});

$(document).on('click','#solicitar-selo-btn', function(){
  $('#livro_selo').val($('#livro').val());
  $('#folha_selo').val($('#folha').val());
  $('#termo_selo').val($('#termo').val());
});

// Fallback para normalizar HTML do selo recebido do servidor
function appendSealCard(serverHtml){
  // Se já veio no layout novo, só inserir
  if (serverHtml && serverHtml.indexOf('seal-wrapper') !== -1) {
    $('#selos-container').append(serverHtml);
    return;
  }
  // Fallback: tenta extrair dados do HTML antigo e montar o card novo
  const html = serverHtml || '';
  const qrMatch = html.match(/src="data:image\/png;base64,([^"]+)"/i);
  const numMatch = html.match(/Selo:\s*([A-Z0-9]+)/i);
  const textMatch = html.match(/<\/strong><\/p>\s*<p[^>]*>([\s\S]*?)<\/p>/i);

  const qr = (qrMatch && qrMatch[1]) || '';
  const numero = (numMatch && numMatch[1]) || '';
  const texto = (textMatch && textMatch[1]) || '';

  const card = `
    <div class="seal-wrapper" style="margin-bottom:10px">
      <div class="seal-card">
        <div class="seal-head">
          <div class="seal-title">Poder Judiciário – TJMA</div>
          <span class="seal-pill"><i class="fa fa-check-circle"></i> Selo gerado</span>
        </div>
        <div class="seal-grid">
          <div class="seal-qr"><img src="data:image/png;base64,${qr}" alt="QR Code do selo"></div>
          <div class="seal-meta">
            <div class="seal-number">Selo: <b>${numero}</b></div>
            <p class="seal-text">${texto}</p>
            <div class="seal-actions">
              <button type="button" class="seal-copy-btn" data-copy="${numero}">
                <i class="fa fa-clone"></i> Copiar número
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>`;
  $('#selos-container').append(card);
}

$(document).on('submit','#selo-form', function(e){
  e.preventDefault();
  $('#solicitar-selo-btn').prop('disabled', true);

  $.ajax({
    url: 'selos_arquivamentos.php',
    type: 'POST',
    data: $(this).serialize(),
    success: function(response) {
      var data = {};
      try{ data = JSON.parse(response); }catch(e){ data = {}; }

      if (data.success) {
        // garante que os campos ocultos estão atualizados
        $('#livro_selo').val($('#livro').val());
        $('#folha_selo').val($('#folha').val());
        $('#termo_selo').val($('#termo').val());

        // adiciona o selo novo na lista (normalizando, se preciso)
        if ($('#selos-container').length === 0){
          $('<div id="selos-container" class="mt-2"></div>').insertAfter('#selo-form');
        }
        appendSealCard(data.html);

        // reseta e esconde o form
        $('#selo-form')[0].reset();
        $('#motivo-wrapper').hide();
        $('#selo-form').slideUp(120);

        // mostra o botão "Adicionar mais selo"
        $('#btnAddSelo').show();

        // feedback
        Swal.fire({ icon:'success', title:'Sucesso', text:data.success, timer:1500, showConfirmButton:false });

        // reabilita o botão submit
        $('#solicitar-selo-btn').prop('disabled', false);

      } else if (data.error) {
        Swal.fire({
          icon:'error',
          title:'Erro',
          html: data.error + '<br><button id="verificarIpBtn" class="btn btn-primary mt-2">Verificar IP do Selador</button>',
          didOpen: () => {
            $('#verificarIpBtn').click(function() {
              $(this).prop('disabled', true).text('Verificando... aguarde');
              $.ajax({
                url: 'verificar_ip.php',
                type: 'GET',
                success: function(resp) {
                  var ipData = {};
                  try{ ipData = JSON.parse(resp); }catch(e){ ipData = {}; }
                  if (ipData.sucesso) {
                    Swal.fire({
                      icon:'success',
                      title:'IP Verificado',
                      html: ipData.sucesso + '<br><button id="salvarIpBtn" class="btn btn-primary mt-2">Salvar</button>',
                      didOpen: () => {
                        $('#salvarIpBtn').click(function() {
                          $.ajax({
                            url: 'atualizar_ip.php',
                            type: 'POST',
                            data: { ip: ipData.ip },
                            success: function(updateResponse) {
                              Swal.fire({ icon:'success', title:'IP Atualizado', text:updateResponse, confirmButtonText:'OK' });
                            },
                            error: function() {
                              Swal.fire({ icon:'error', title:'Erro', text:'Erro ao atualizar o IP.', confirmButtonText:'OK' });
                            }
                          });
                        });
                      }
                    });
                  } else {
                    Swal.fire({ icon:'error', title:'Erro', text: (ipData.erro || 'Falha ao verificar.'), confirmButtonText:'OK' });
                  }
                },
                error: function() {
                  Swal.fire({ icon:'error', title:'Erro', text:'Erro ao verificar o IP.', confirmButtonText:'OK' });
                }
              });
            });
          }
        });
        $('#solicitar-selo-btn').prop('disabled', false);
      } else {
        Swal.fire({ icon:'error', title:'Erro', text:'Erro ao solicitar o selo.', confirmButtonText:'OK' });
        $('#solicitar-selo-btn').prop('disabled', false);
      }
    },
    error: function() {
      Swal.fire({ icon:'error', title:'Erro', text:'Erro ao solicitar o selo.', confirmButtonText:'OK' });
      $('#solicitar-selo-btn').prop('disabled', false);
    }
  });
});

/* Copiar número do selo (com fallback para HTTP/estações sem clipboard API) */
async function secureClipboardWrite(text){
  try{
    if (navigator.clipboard && window.isSecureContext){
      await navigator.clipboard.writeText(text);
      return true;
    }
  }catch(e){ /* continua para o fallback */ }

  // Fallback: textarea oculto + execCommand
  try{
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly','');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    ta.setSelectionRange(0, ta.value.length); // iOS
    const ok = document.execCommand('copy');
    document.body.removeChild(ta);
    if (ok) return true;
  }catch(e){ /* passa para o próximo fallback */ }

  // Fallback legado (IE)
  try{
    if (window.clipboardData){
      window.clipboardData.setData('Text', text);
      return true;
    }
  }catch(e){}

  return false;
}

$(document).on('click','.seal-copy-btn', async function(){
  const txt = $(this).data('copy') || '';
  if (!txt) return;

  const ok = await secureClipboardWrite(txt);
  if (ok){
    Swal.fire({ icon:'success', title:'Copiado!', text:'Número do selo copiado para a área de transferência.', timer:1500, showConfirmButton:false });
  }else{
    Swal.fire({ icon:'warning', title:'Não foi possível copiar', text:'Seu navegador bloqueou o acesso à área de transferência. Selecione e copie manualmente.', confirmButtonText:'OK' });
  }
});

/* ======================== Validação simples de data ======================== */
$(function(){
  const currentYear = new Date().getFullYear();
  function validateDate(input){
    var selectedDate = new Date($(input).val());
    if (selectedDate.getFullYear() > currentYear) {
      Swal.fire({ icon:'warning', title:'Data inválida', text:'O ano não pode ser maior que o ano atual.', confirmButtonText:'Ok' });
      $(input).val('');
    }
  }
  $('#data_ato').on('change', function(){ if ($(this).val()) validateDate(this); });
});

// Abrir o form para adicionar um novo selo
$(document).on('click', '#btnAddSelo', function(){
  $('#selo-form').slideDown(150);
  $(this).hide(); // esconde o botão enquanto o form está aberto
  setTimeout(()=> { $('#ato').focus(); }, 200);
});
</script>

<?php include(__DIR__ . '/../rodape.php'); ?>
</body>
</html>
