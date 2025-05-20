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
    <title>Atlas - Indexador TXT Óbito</title>  
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
        :root {  
            --bg-primary: #ffffff;  
            --bg-secondary: #f8f9fa;  
            --bg-tertiary: #e9ecef;  
            --text-primary: #2c3e50;  
            --text-secondary: #6c757d;  
            --border-color: #dee2e6;  
            --accent-color: #2196F3;  
            --success-color: #28a745;  
        }  

        body {  
            font-family: 'Inter', sans-serif;  
            background-color: var(--bg-secondary);  
            color: var(--text-primary);  
            margin: 0;  
            padding: 0;  
        }  

        .container {  
            background-color: var(--bg-primary);  
            border-radius: 16px;  
            padding: 2rem;  
            margin-top: 20px;  
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);  
        }  

        .page-header {  
            display: flex;  
            align-items: center;  
            justify-content: space-between;  
            margin-bottom: 2rem;  
            padding-bottom: 1rem;  
            border-bottom: 2px solid var(--border-color);  
        }  

        .btn-primary {  
            background: linear-gradient(45deg, var(--accent-color), #1976D2);  
            color: white;  
            border: none;  
            padding: 12px 24px;  
            border-radius: 8px;  
            font-weight: 600;  
            transition: all 0.3s ease;  
        }  
        
        .btn-primary:hover {  
            transform: translateY(-2px);  
            box-shadow: 0 5px 15px rgba(33, 150, 243, 0.3);  
        }  

        .loading-overlay {  
            position: fixed;  
            top: 0;  
            left: 0;  
            right: 0;  
            bottom: 0;  
            background-color: rgba(0,0,0,0.7);  
            display: none;  
            align-items: center;  
            justify-content: center;  
            z-index: 9999;  
            flex-direction: column;  
        }  
        
        .progress-container {  
            width: 300px;  
            background-color: var(--bg-primary);  
            border-radius: 12px;  
            padding: 30px;  
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);  
        }  
        
        .progress-title {  
            text-align: center;  
            margin-bottom: 15px;  
            color: var(--text-primary);  
            font-weight: 600;  
        }  
        
        .progress-bar-container {  
            height: 8px;  
            background-color: var(--bg-tertiary);  
            border-radius: 4px;  
            margin-bottom: 10px;  
            overflow: hidden;  
        }  
        
        .progress-bar {  
            height: 100%;  
            background: linear-gradient(90deg, var(--accent-color), #1976D2);  
            border-radius: 4px;  
            width: 0%;  
            transition: width 0.3s ease;  
        }  
        
        .progress-text {  
            text-align: right;  
            color: var(--text-primary);  
            font-size: 14px;  
            font-weight: 600;  
        }  
        
        /* Custom Dropzone Styling */  
        .dropzone {  
            border: 2px dashed var(--border-color);  
            border-radius: 12px;  
            background: var(--bg-secondary);  
            min-height: 200px;  
            padding: 20px;  
            display: flex;  
            flex-direction: column;  
            align-items: center;  
            justify-content: center;  
            cursor: pointer;  
            transition: all 0.3s ease;  
            margin-bottom: 20px;  
        }  
        
        .dropzone:hover {  
            border-color: var(--accent-color);  
            background-color: rgba(33, 150, 243, 0.05);  
        }  
        
        .dropzone .dz-message {  
            text-align: center;  
        }  
        
        .dropzone .dz-message .text {  
            font-size: 18px;  
            font-weight: 500;  
            color: var(--text-primary);  
            margin-top: 15px;  
        }  
        
        .dropzone .dz-message .icon {  
            font-size: 48px;  
            color: var(--accent-color);  
        }  
        
        .dropzone .dz-preview {  
            margin: 10px;  
        }  
        
        .dropzone .dz-preview .dz-image {  
            border-radius: 8px;  
        }  
        
        .dropzone .dz-preview .dz-success-mark,  
        .dropzone .dz-preview .dz-error-mark {  
            margin-top: -25px;  
        }  
        
        .file-info {  
            font-size: 14px;  
            color: var(--text-secondary);  
            margin-top: 5px;  
        }  
    </style>  
</head>  
<body>  
<?php include(__DIR__ . '/../../menu.php'); ?>  
<div id="main" class="main-content">  
    <div class="container">  
        <div class="d-flex flex-wrap justify-content-center align-items-center text-center mb-1">  
                <div class="col-md-auto mb-2">
                    <a href="index.php" class="btn btn-secondary text-white">
                        <i class="fa fa-home"></i> Indexador
                    </a>
                </div>
        </div> 
        <hr>
        <div class="d-flex justify-content-center align-items-center text-center mb-3">  
            <h3>Indexador de Arquivos TXT - Óbito</h3>  
        </div>  
        <hr>

        <div class="mb-4">  
            <div id="dropzoneForm" class="dropzone">  
                <div class="dz-message needsclick">  
                    <div class="icon"><i class="fas fa-cloud-upload-alt"></i></div>  
                    <div class="text">Arraste e solte arquivos TXT aqui<br><span class="file-info">ou clique para selecionar</span></div>  
                </div>  
            </div>  
        </div>  
        <button type="button" id="processBtn" class="btn btn-primary w-100" disabled>  
            <i class="fas fa-upload"></i> Processar Arquivos  
        </button>  

        <div class="loading-overlay">  
            <div class="progress-container">  
                <div class="progress-title">Processando arquivos...</div>  
                <div class="progress-bar-container">  
                    <div class="progress-bar"></div>  
                </div>  
                <div class="progress-text">0%</div>  
            </div>  
        </div>  

    </div>  
</div>  

<script>  
// Inicialização do Dropzone  
Dropzone.autoDiscover = false;  

$(document).ready(function() {  
    // Array para armazenar arquivos  
    let uploadedFiles = [];  
    
    let myDropzone = new Dropzone("#dropzoneForm", {  
        url: "#", // Impedimos o upload automático  
        autoProcessQueue: false,  
        addRemoveLinks: true,  
        acceptedFiles: "text/plain",  
        maxFilesize: 10, // MB  
        parallelUploads: 10,  
        dictDefaultMessage: "Arraste e solte arquivos aqui para upload",  
        dictFallbackMessage: "Seu navegador não suporta arrastar e soltar arquivos para upload.",  
        dictFileTooBig: "Arquivo muito grande ({{filesize}}MB). Tamanho máximo: {{maxFilesize}}MB.",  
        dictInvalidFileType: "Este tipo de arquivo não é permitido. Apenas arquivos TXT são aceitos.",  
        dictRemoveFile: "Remover",  
        dictMaxFilesExceeded: "Não é possível carregar mais arquivos.",  
        init: function() {  
            const dropzone = this;  
            
            // Ativa/desativa o botão de processamento dependendo se há arquivos  
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
    
    // Quando o botão de processar for clicado  
    $("#processBtn").on("click", function() {  
        if (uploadedFiles.length === 0) {  
            Swal.fire({  
                icon: 'warning',  
                title: 'Nenhum arquivo',  
                text: 'Por favor, adicione pelo menos um arquivo para processar.',  
                confirmButtonColor: '#2196F3'  
            });  
            return;  
        }  
        
        $(".loading-overlay").css("display", "flex");  
        
        // Processar arquivos manualmente  
        let processedCount = 0;  
        let successCount = 0;  
        let errorCount = 0;  
        let successMessages = [];  
        let errorMessages = [];  
        
        // Função para processar próximo arquivo  
        function processNextFile(index) {  
            if (index >= uploadedFiles.length) {  
                // Todos os arquivos foram processados  
                $(".loading-overlay").css("display", "none");  
                
                let message = '';  
                
                if (successCount > 0) {  
                    message += `<strong>${successCount} arquivo(s) processado(s) com sucesso:</strong><br>`;  
                    successMessages.forEach(msg => {  
                        message += `- ${msg}<br>`;  
                    });  
                }  
                
                if (errorCount > 0) {  
                    if (successCount > 0) message += '<br>';  
                    message += `<strong>${errorCount} arquivo(s) com erro:</strong><br>`;  
                    errorMessages.forEach(msg => {  
                        message += `- ${msg}<br>`;  
                    });  
                }  
                
                const icon = successCount > 0 ? (errorCount > 0 ? 'warning' : 'success') : 'error';  
                
                Swal.fire({  
                    icon: icon,  
                    title: successCount > 0 ? 'Processamento Concluído' : 'Erro no Processamento',  
                    html: message,  
                    confirmButtonColor: successCount > 0 ? '#28a745' : '#dc3545'  
                }).then((result) => {  
                    if (result.isConfirmed) {  
                        // Limpar todos os arquivos  
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
            
            // Atualizar barra de progresso  
            const progress = Math.round(((index + 1) / uploadedFiles.length) * 100);  
            $(".progress-bar").css("width", progress + "%");  
            $(".progress-text").text(progress + "%");  
            
            $.ajax({  
                url: 'processar_txt_obito.php',  
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
                        console.error('Erro ao analisar resposta:', e);  
                        console.log('Resposta bruta:', response);  
                        errorCount++;  
                        errorMessages.push(`${file.name}: Resposta inválida do servidor`);  
                    }  
                    
                    // Processar próximo arquivo  
                    processNextFile(index + 1);  
                },  
                error: function(xhr, status, error) {  
                    processedCount++;  
                    errorCount++;  
                    errorMessages.push(`${file.name}: ${error || 'Erro ao processar o arquivo'}`);  
                    
                    // Processar próximo arquivo mesmo em caso de erro  
                    processNextFile(index + 1);  
                }  
            });  
        }  
        
        // Iniciar processamento  
        processNextFile(0);  
    });  
});  
</script>  
<?php include(__DIR__ . '/../../rodape.php'); ?>  
</body>  
</html>