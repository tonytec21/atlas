<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  
date_default_timezone_set('America/Sao_Paulo');  
?>  
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Atlas - Indexador XLSX Óbito</title>  
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../../style/css/style.css">  
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">  
    <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">  
    <link rel="stylesheet" href="../../style/css/dataTables.bootstrap4.min.css">  
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">  
    <!-- Dropzone CSS -->  
    <link href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" rel="stylesheet" type="text/css">  
    <script src="../../script/jquery-3.6.0.min.js"></script>  
    <script src="../../script/jquery.dataTables.min.js"></script>  
    <script src="../../script/dataTables.bootstrap4.min.js"></script>  
    <script src="../../script/bootstrap.bundle.min.js"></script>  
    <!-- Dropzone JS -->  
    <script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>  
    <!-- SweetAlert2 -->  
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>  

    <style>
/* ===================== CSS VARIABLES ===================== */  
:root {  
  --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;  
  --space-xs: 4px;  
  --space-sm: 8px;  
  --space-md: 16px;  
  --space-lg: 24px;  
  --space-xl: 32px;  
  --radius-sm: 10px;  
  --radius-md: 14px;  
  --radius-lg: 20px;  
  --radius-full: 9999px;  
  --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.06);  
  --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.12), 0 4px 16px rgba(0, 0, 0, 0.08);  
  --shadow-2xl: 0 24px 64px rgba(0, 0, 0, 0.20), 0 12px 32px rgba(0, 0, 0, 0.16);  
  --bg-primary: #fafbfc;  
  --bg-secondary: #f4f6f8;  
  --bg-tertiary: #ffffff;  
  --text-primary: #0d1117;  
  --text-secondary: #424a53;  
  --text-tertiary: #656d76;  
  --border-primary: rgba(13, 17, 23, 0.08);  
  --border-secondary: rgba(13, 17, 23, 0.12);  
  --brand-primary: #475569;  
  --brand-primary-dark: #334155;  
  --gradient-primary: linear-gradient(135deg, #475569 0%, #1e293b 100%);  
  --gradient-surface: linear-gradient(145deg, rgba(255, 255, 255, 0.95) 0%, rgba(250, 251, 252, 0.98) 100%);  
  --gradient-mesh: radial-gradient(at 0% 0%, rgba(71, 85, 105, 0.08) 0px, transparent 50%),  
    radial-gradient(at 100% 0%, rgba(30, 41, 59, 0.08) 0px, transparent 50%),  
    radial-gradient(at 100% 100%, rgba(148, 163, 184, 0.06) 0px, transparent 50%),  
    radial-gradient(at 0% 100%, rgba(100, 116, 139, 0.06) 0px, transparent 50%);  
}  

.dark-mode {  
  --bg-primary: #0d1117;  
  --bg-secondary: #161b22;  
  --bg-tertiary: #21262d;  
  --text-primary: #f0f6fc;  
  --text-secondary: #c9d1d9;  
  --text-tertiary: #8b949e;  
  --border-primary: rgba(240, 246, 252, 0.10);  
  --border-secondary: rgba(240, 246, 252, 0.14);  
  --gradient-surface: linear-gradient(145deg, rgba(33, 38, 45, 0.95) 0%, rgba(22, 27, 34, 0.98) 100%);  
}  

body {
  font-family: var(--font-primary) !important;
  background: var(--bg-primary) !important;
  color: var(--text-primary) !important;
  margin: 0 !important;
  padding: 0 !important;
  min-height: 100vh !important;
}

.main-content {
  flex: 1;
  padding: var(--space-lg);
  padding-top: calc(var(--space-lg) + 60px);
  background: var(--gradient-mesh), var(--bg-primary);
  min-height: 100vh;
}

.container {
  max-width: 900px;
  margin: 0 auto;
  padding: 0 var(--space-md);
}

.page-hero {
  background: var(--gradient-surface);
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-lg);
  padding: var(--space-xl) var(--space-lg);
  margin-bottom: var(--space-xl);
  box-shadow: var(--shadow-md);
  position: relative;
  overflow: hidden;
}

.page-hero::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--gradient-primary);
}

.title-row {
  display: flex;
  align-items: flex-start;
  gap: var(--space-lg);
}

.title-icon {
  width: 64px;
  height: 64px;
  border-radius: var(--radius-md);
  background: linear-gradient(135deg, rgba(71, 85, 105, 0.15) 0%, rgba(30, 41, 59, 0.1) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.title-icon i {
  font-size: 28px;
  color: var(--brand-primary);
}

.page-hero h1 {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 var(--space-xs) 0;
}

.hero-subtitle {
  font-size: 0.95rem;
  color: var(--text-tertiary);
  margin: 0;
  line-height: 1.5;
}

.card-upload {
  background: var(--bg-tertiary);
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  overflow: hidden;
}

.card-upload .card-header {
  background: transparent;
  padding: var(--space-lg);
  border-bottom: 1px solid var(--border-primary);
}

.card-upload .card-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.card-upload .card-subtitle {
  font-size: 0.875rem;
  color: var(--text-tertiary);
}

.card-upload .card-body {
  padding: var(--space-lg);
}

.tipo-planilha-group {
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-md);
  padding: var(--space-md);
  margin-bottom: var(--space-lg);
}

.tipo-planilha-group label.font-weight-bold {
  font-size: 0.9rem;
  color: var(--text-secondary);
  font-weight: 600;
}

.custom-control-label {
  font-weight: 500;
  color: var(--text-primary);
}

.custom-radio .custom-control-input:checked ~ .custom-control-label::before {
  background-color: var(--brand-primary);
  border-color: var(--brand-primary);
}

.dropzone {
  border: 2px dashed var(--border-secondary);
  border-radius: var(--radius-md);
  background: linear-gradient(145deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
  min-height: 220px;
  padding: var(--space-xl);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  margin-bottom: var(--space-lg);
}

.dropzone:hover {
  border-color: var(--brand-primary);
  background: linear-gradient(145deg, rgba(71, 85, 105, 0.05) 0%, rgba(30, 41, 59, 0.03) 100%);
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.dropzone .dz-message {
  text-align: center;
  margin: 0;
}

.dropzone .dz-message .icon {
  width: 72px;
  height: 72px;
  border-radius: var(--radius-full);
  background: linear-gradient(135deg, rgba(71, 85, 105, 0.15) 0%, rgba(30, 41, 59, 0.1) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto var(--space-md);
}

.dropzone .dz-message .icon i {
  font-size: 32px;
  color: var(--brand-primary);
}

.dropzone .dz-message .text {
  font-size: 1rem;
  font-weight: 500;
  color: var(--text-primary);
  margin-bottom: var(--space-xs);
}

.dropzone .dz-message .file-info {
  font-size: 0.85rem;
  color: var(--text-tertiary);
}

.btn-primary {
  background: var(--gradient-primary);
  border: none;
  color: white;
  font-weight: 600;
  padding: var(--space-md) var(--space-xl);
  border-radius: var(--radius-md);
  font-size: 1rem;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 14px rgba(71, 85, 105, 0.3);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(71, 85, 105, 0.4);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  box-shadow: none;
}

.btn-outline-secondary {
  border: 1px solid var(--border-secondary);
  background: var(--bg-tertiary);
  color: var(--text-secondary);
  font-weight: 500;
  padding: var(--space-sm) var(--space-md);
  border-radius: var(--radius-sm);
  transition: all 0.2s ease;
}

.btn-outline-secondary:hover {
  background: var(--bg-secondary);
  border-color: var(--brand-primary);
  color: var(--brand-primary);
}

.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(8px);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.progress-container {
  background: var(--bg-tertiary);
  border: 1px solid var(--border-primary);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  width: 360px;
  max-width: 90vw;
  box-shadow: var(--shadow-2xl);
}

.progress-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
  text-align: center;
  margin-bottom: var(--space-md);
}

.progress-bar-container {
  height: 8px;
  background: var(--bg-secondary);
  border-radius: var(--radius-full);
  overflow: hidden;
  margin-bottom: var(--space-sm);
}

.progress-bar {
  height: 100%;
  width: 0;
  background: var(--gradient-primary);
  border-radius: var(--radius-full);
  transition: width 0.3s ease;
}

.progress-text {
  font-size: 0.85rem;
  color: var(--text-tertiary);
  text-align: center;
}

.info-box {
  background: linear-gradient(145deg, rgba(71, 85, 105, 0.08) 0%, rgba(30, 41, 59, 0.05) 100%);
  border: 1px solid rgba(71, 85, 105, 0.2);
  border-radius: var(--radius-md);
  padding: var(--space-md);
  margin-top: var(--space-lg);
}

.info-box h6 {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--brand-primary);
  margin-bottom: var(--space-sm);
  display: flex;
  align-items: center;
  gap: var(--space-sm);
}

.info-box p {
  font-size: 0.85rem;
  color: var(--text-secondary);
  margin: 0;
  line-height: 1.6;
}

.info-box code {
  background: rgba(71, 85, 105, 0.1);
  color: var(--brand-primary-dark);
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.8rem;
}

@media (max-width: 575.98px) {
  .page-hero { padding: var(--space-lg) var(--space-md); }
  .page-hero h1 { font-size: 1.35rem; }
  .title-icon { width: 52px; height: 52px; }
  .title-icon i { font-size: 24px; }
  .card-upload .card-header, .card-upload .card-body { padding: var(--space-md); }
  .dropzone { min-height: 180px; padding: var(--space-lg); }
}
    </style>
</head>  
<body>  
<?php include(__DIR__ . '/../../menu.php'); ?>  
<main id="main" class="main-content">
  <div class="container">  
    
    <section class="page-hero">  
      <div class="title-row">  
        <div class="title-icon">  
          <i class="fa fa-cloud-upload" aria-hidden="true"></i>
        </div>
        <div>
          <h1>Indexador XLSX — Óbito</h1>
          <p class="hero-subtitle">
            Importe planilhas <strong>simples</strong> ou <strong>completas</strong> para alimentar o indexador de óbitos de forma rápida e segura.
          </p>
        </div>
      </div>
    </section>

    <div class="card card-upload mb-3">  
      <div class="card-header border-0">  
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">  
          <div>  
            <h5 class="card-title mb-1">Configurações de importação</h5>  
            <p class="card-subtitle mb-0">Escolha o tipo de planilha e envie seus arquivos XLSX.</p>  
          </div>  
          <div class="mt-3 mt-md-0">
            <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center">
              <i class="fa fa-arrow-left mr-2"></i>
              <span>Voltar ao Indexador</span>
            </a>
          </div>
        </div>  
      </div>  
      <div class="card-body pt-2">  

        <div class="mb-3 tipo-planilha-group">  
          <label class="font-weight-bold d-block mb-2">Tipo de planilha</label>  
          <div class="custom-control custom-radio custom-control-inline mb-1">  
            <input type="radio" id="tipoSimples" name="tipo_planilha" class="custom-control-input" value="simples">  
            <label class="custom-control-label" for="tipoSimples">Simples</label>  
          </div>  
          <div class="custom-control custom-radio custom-control-inline mb-1">  
            <input type="radio" id="tipoCompleta" name="tipo_planilha" class="custom-control-input" value="completa" checked>  
            <label class="custom-control-label" for="tipoCompleta">Completa</label>  
          </div>  
          <div class="mt-1">  
            <small id="tipoPlanilhaDescricao" class="text-muted">  
              Simples: termo, nome_registrado, livro, folha.  
            </small>  
          </div>  
        </div>  

        <div class="mb-3">  
          <div id="dropzoneForm" class="dropzone">  
            <div class="dz-message needsclick text-center">  
              <div class="icon"><i class="fa fa-cloud-upload"></i></div>  
              <div class="text">Arraste e solte arquivos XLSX aqui<br><span class="file-info">ou clique para selecionar</span></div>  
            </div>  
          </div>  
        </div>  

        <button type="button" id="processBtn" class="btn btn-primary btn-lg btn-block" disabled>  
          <i class="fa fa-upload mr-2"></i>  
          Processar arquivos selecionados  
        </button>  

        <div class="info-box">
          <h6><i class="fa fa-info-circle"></i> Estrutura das planilhas</h6>
          <p>
            <strong>Simples:</strong> <code>termo</code>, <code>nome_registrado</code>, <code>livro</code>, <code>folha</code><br>
            <strong>Completa:</strong> <code>termo</code>, <code>livro</code>, <code>folha</code>, <code>data_registro</code>, <code>data_nascimento</code>, <code>data_obito</code>, <code>hora_obito</code>, <code>nome_registrado</code>, <code>nome_pai</code>, <code>nome_mae</code>, <code>matricula</code>, <code>cidade_endereco</code>, <code>cidade_obito</code><br><br>
            <em>Se a matrícula não for preenchida, será calculada automaticamente.</em><br>
            <em>Os campos <code>cidade_endereco</code> e <code>cidade_obito</code> aceitam código IBGE (número) ou formato "CIDADE/UF" (ex: SANTA INES/MA). O código IBGE será preenchido automaticamente via consulta à API.</em>
          </p>
        </div>

      </div>  
    </div>  

  </div>  

  <div class="loading-overlay">  
    <div class="progress-container">  
      <div class="progress-title">Processando arquivos...</div>  
      <div class="progress-bar-container">  
        <div class="progress-bar"></div>  
      </div>  
      <div class="progress-text">0%</div>  
    </div>  
  </div>  

</main>  

<script>  
Dropzone.autoDiscover = false;  

$(document).ready(function() {  
    let uploadedFiles = [];  
    let tipoPlanilhaSelecionado = $('input[name="tipo_planilha"]:checked').val() || 'simples';  

    function atualizarDescricaoTipo() {  
        if (tipoPlanilhaSelecionado === 'simples') {  
            $('#tipoPlanilhaDescricao').text('Simples: termo, nome_registrado, livro, folha.');  
        } else {  
            $('#tipoPlanilhaDescricao').text('Completa: termo, livro, folha, data_registro, data_nascimento, data_obito, hora_obito, nome_registrado, nome_pai, nome_mae, matricula, cidade_endereco, cidade_obito.');  
        }  
    }  

    $('input[name="tipo_planilha"]').on('change', function() {  
        tipoPlanilhaSelecionado = $(this).val();  
        atualizarDescricaoTipo();  
    });  

    atualizarDescricaoTipo();  

    let myDropzone = new Dropzone("#dropzoneForm", {  
        url: "#",  
        autoProcessQueue: false,  
        addRemoveLinks: true,  
        acceptedFiles: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel",  
        maxFilesize: 10,  
        parallelUploads: 10,  
        dictDefaultMessage: "Arraste e solte arquivos aqui para upload",  
        dictFallbackMessage: "Seu navegador não suporta arrastar e soltar arquivos para upload.",  
        dictFileTooBig: "Arquivo muito grande ({{filesize}}MB). Tamanho máximo: {{maxFilesize}}MB.",  
        dictInvalidFileType: "Este tipo de arquivo não é permitido. Apenas arquivos XLSX são aceitos.",  
        dictRemoveFile: "Remover",  
        dictMaxFilesExceeded: "Não é possível carregar mais arquivos.",  
        init: function() {  
            this.on("addedfile", function(file) {  
                uploadedFiles.push(file);  
                $("#processBtn").prop("disabled", false);  
            });  
            this.on("removedfile", function(file) {  
                uploadedFiles = uploadedFiles.filter(f => f !== file);  
                if (uploadedFiles.length === 0) {  
                    $("#processBtn").prop("disabled", true);  
                }  
            });  
        }  
    });  
      
    $("#processBtn").on("click", function() {  
        if (uploadedFiles.length === 0) {  
            Swal.fire({ icon: 'warning', title: 'Nenhum arquivo', text: 'Por favor, adicione pelo menos um arquivo para processar.', confirmButtonColor: '#475569' });  
            return;  
        }  

        $(".loading-overlay").css("display", "flex");  

        let processedCount = 0, successCount = 0, errorCount = 0;  
        let successMessages = [], errorMessages = [];  

        function processNextFile(index) {  
            if (index >= uploadedFiles.length) {  
                $(".loading-overlay").css("display", "none");  
                
                let message = '';  
                if (successCount > 0) {  
                    message += `<strong>${successCount} arquivo(s) processado(s) com sucesso:</strong><br>`;  
                    successMessages.forEach(msg => { message += `- ${msg}<br>`; });  
                }  
                if (errorCount > 0) {  
                    if (successCount > 0) message += '<br>';  
                    message += `<strong>${errorCount} arquivo(s) com erro:</strong><br>`;  
                    errorMessages.forEach(msg => { message += `- ${msg}<br>`; });  
                }  
                
                const icon = successCount > 0 ? (errorCount > 0 ? 'warning' : 'success') : 'error';  
                
                Swal.fire({  
                    icon: icon,  
                    title: successCount > 0 ? 'Processamento Concluído' : 'Erro no Processamento',  
                    html: message,  
                    confirmButtonColor: successCount > 0 ? '#28a745' : '#dc3545'  
                }).then((result) => {  
                    if (result.isConfirmed) {  
                        myDropzone.removeAllFiles(true);  
                        uploadedFiles = [];  
                        $("#processBtn").prop("disabled", true);  
                    }  
                });  
                return;  
            }  
            
            const file = uploadedFiles[index];  
            const formData = new FormData();  
            formData.append('arquivos[]', file);  
            formData.append('tipo_planilha', tipoPlanilhaSelecionado);  
            
            const progress = Math.round(((index + 1) / uploadedFiles.length) * 100);  
            $(".progress-bar").css("width", progress + "%");  
            $(".progress-text").text(progress + "%");  
            
            $.ajax({  
                url: 'processar_xlsx_obito.php',  
                type: 'POST',  
                data: formData,  
                processData: false,  
                contentType: false,  
                success: function(response) {  
                    processedCount++;  
                    try {  
                        const result = typeof response === 'string' ? JSON.parse(response) : response;  
                        if (result.status === 'success') {  
                            successCount++;  
                            successMessages.push(`${file.name}: ${result.message}`);  
                        } else {  
                            errorCount++;  
                            errorMessages.push(`${file.name}: ${result.message}`);  
                        }  
                    } catch (e) {  
                        errorCount++;  
                        errorMessages.push(`${file.name}: Resposta inválida do servidor`);  
                    }  
                    processNextFile(index + 1);  
                },  
                error: function(xhr, status, error) {  
                    processedCount++;  
                    errorCount++;  
                    errorMessages.push(`${file.name}: ${error || 'Erro ao processar o arquivo'}`);  
                    processNextFile(index + 1);  
                }  
            });  
        }  
        
        processNextFile(0);  
    });  
});  
</script>  
<?php include(__DIR__ . '/../../rodape.php'); ?>  
</body>  
</html>
