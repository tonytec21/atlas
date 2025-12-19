<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  
?>  
<!DOCTYPE html>  
<html lang="pt-br">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>Atlas - Tabela de Emolumentos</title>  
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../style/css/style.css">  
    <link rel="icon" href="../style/img/favicon.png" type="image/png">  
    <link rel="stylesheet" href="../style/css/sweetalert2.min.css">  
    <script src="../script/sweetalert2.js"></script>  
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font/css/materialdesignicons.min.css">  
    <style>  
        :root {
            --primary-color: #4361ee;
            --primary-hover: #3a56d4;
            --primary-light: #eef2ff;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --bg-light: #f8fafc;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --radius-sm: 6px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.dark-mode {
            --primary-light: #1e293b;
            --border-color: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --bg-light: #0f172a;
            --white: #1e293b;
        }

        .status-label {  
            display: inline-block;  
            padding: 0.2em 0.6em;  
            font-size: 75%;  
            font-weight: 700;  
            line-height: 2;  
            color: #fff;  
            text-align: center;  
            white-space: nowrap;  
            vertical-align: baseline;  
            border-radius: 0.25em;  
            width: 100px;  
        }  

        .main-content {
            padding: calc(var(--header-height) + 40px) 45px 20px!important;
        }

        .content-container {  
            padding: 30px;  
            background-color: var(--bg-light);
            min-height: calc(100vh - 60px);
        }  

        .white-container {  
            background-color: var(--white);  
            border-radius: var(--radius-lg);  
            box-shadow: var(--shadow-md);  
            padding: 30px;  
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }  

        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .page-header-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), #7c3aed);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .page-header h2 {  
            color: var(--text-primary);  
            font-size: 26px;  
            margin: 0;  
            font-weight: 600;  
        }

        .page-header-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
        }

        .divider {
            height: 1px;
            background: linear-gradient(to right, var(--border-color), transparent);
            margin: 25px 0;
        }

        .form-content {  
            max-width: 600px;  
            margin: 0 auto;  
        }

        /* Drop Zone Styles */
        .drop-zone-wrapper {
            margin-bottom: 25px;
        }

        .drop-zone {  
            width: 100%;
            min-height: 220px;
            padding: 40px 30px;
            display: flex;  
            flex-direction: column;
            align-items: center;  
            justify-content: center;  
            text-align: center;  
            cursor: pointer;  
            border: 2px dashed var(--border-color);  
            border-radius: var(--radius-lg);  
            background-color: var(--bg-light);  
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .drop-zone::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.03), rgba(124, 58, 237, 0.03));
            opacity: 0;
            transition: var(--transition);
        }

        .drop-zone:hover {  
            border-color: var(--primary-color);
            background-color: var(--primary-light);
        }

        .drop-zone:hover::before {
            opacity: 1;
        }

        .drop-zone.drop-zone--over {  
            border-style: solid;
            border-color: var(--primary-color);
            background-color: var(--primary-light);
            transform: scale(1.01);
        }

        .drop-zone.drop-zone--over::before {
            opacity: 1;
        }

        .drop-zone__input {  
            display: none;  
        }

        .drop-zone__icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), #7c3aed);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .drop-zone:hover .drop-zone__icon {
            transform: scale(1.1);
        }

        .drop-zone__icon i {
            font-size: 36px;
            color: white;
        }

        .drop-zone__title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .drop-zone__subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .drop-zone__formats {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background-color: var(--white);
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }

        .drop-zone__formats i {
            color: var(--success-color);
            font-size: 18px;
        }

        .drop-zone__formats span {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* File Preview */
        .drop-zone__preview {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background-color: var(--white);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            width: 100%;
            max-width: 400px;
        }

        .drop-zone__preview-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .drop-zone__preview-icon i {
            font-size: 24px;
            color: white;
        }

        .drop-zone__preview-info {
            flex: 1;
            min-width: 0;
            text-align: left;
        }

        .drop-zone__preview-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }

        .drop-zone__preview-size {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .drop-zone__preview-remove {
            width: 32px;
            height: 32px;
            border: none;
            background-color: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .drop-zone__preview-remove:hover {
            background-color: #fee2e2;
        }

        .drop-zone__preview-remove i {
            font-size: 16px;
            color: var(--danger-color);
        }

        /* Upload Button */
        .upload-button {  
            width: 100%;
            padding: 16px 32px;
            background: linear-gradient(135deg, var(--primary-color), #7c3aed);
            color: white;  
            border: none;  
            border-radius: var(--radius-md);  
            font-size: 16px;  
            font-weight: 600;  
            cursor: pointer;  
            transition: var(--transition);
            display: flex;  
            align-items: center;  
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }  

        .upload-button:hover {  
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }

        .upload-button:active {  
            transform: translateY(0);
        }

        .upload-button:disabled {  
            background: linear-gradient(135deg, #94a3b8, #64748b);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .upload-button i {
            font-size: 20px;
        }

        .button-loading,  
        .button-text {  
            display: flex;  
            align-items: center;  
            gap: 10px;  
        }  

        .d-none {  
            display: none !important;  
        }

        /* Info Cards */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }

        .info-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background-color: var(--bg-light);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }

        .info-card-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .info-card-icon.blue {
            background-color: #dbeafe;
            color: #3b82f6;
        }

        .info-card-icon.green {
            background-color: #dcfce7;
            color: #22c55e;
        }

        .info-card-icon.purple {
            background-color: #f3e8ff;
            color: #a855f7;
        }

        .info-card-icon.orange {
            background-color: #ffedd5;
            color: #f97316;
        }

        .info-card-text {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .info-card-text strong {
            color: var(--text-primary);
            display: block;
            margin-bottom: 2px;
        }

        /* Spinner Animation */
        .mdi-spin {  
            animation: spin 1s infinite linear;  
        }  

        @keyframes spin {  
            from { transform: rotate(0deg); }  
            to { transform: rotate(360deg); }  
        }

        /* Dark Mode Adjustments */
        body.dark-mode .drop-zone__formats {
            background-color: var(--bg-light);
        }

        body.dark-mode .drop-zone__preview {
            background-color: var(--bg-light);
        }

        body.dark-mode .drop-zone__preview-remove {
            background-color: rgba(239, 68, 68, 0.1);
        }

        body.dark-mode .info-card-icon.blue {
            background-color: rgba(59, 130, 246, 0.1);
        }

        body.dark-mode .info-card-icon.green {
            background-color: rgba(34, 197, 94, 0.1);
        }

        body.dark-mode .info-card-icon.purple {
            background-color: rgba(168, 85, 247, 0.1);
        }

        body.dark-mode .info-card-icon.orange {
            background-color: rgba(249, 115, 22, 0.1);
        }

        /* Progress Bar */
        .progress-container {
            display: none;
            margin-top: 20px;
        }

        .progress-container.active {
            display: block;
        }

        .progress-bar-wrapper {
            height: 8px;
            background-color: var(--bg-light);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), #7c3aed);
            border-radius: 4px;
            transition: width 0.3s ease;
            width: 0%;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* Notification Container */
        .notification-container {  
            margin-top: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: calc(var(--header-height) + 20px) 15px 20px!important;
            }

            .white-container {
                padding: 20px;
            }

            .drop-zone {
                min-height: 180px;
                padding: 25px 20px;
            }

            .drop-zone__icon {
                width: 60px;
                height: 60px;
            }

            .drop-zone__icon i {
                font-size: 28px;
            }

            .info-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>  
</head>  
<body class="light-mode">  
    <?php include(__DIR__ . '/../menu.php'); ?>  

    <div id="main" class="main-content">  
        <div class="white-container">  
            <div class="page-header">
                <div class="page-header-icon">
                    <i class="mdi mdi-file-table-outline"></i>
                </div>
                <div>
                    <h2>Tabela de Emolumentos</h2>
                    <p class="page-header-subtitle">Importe sua planilha Excel com os dados de emolumentos</p>
                </div>
            </div>
            <div class="divider"></div>
            
            <div class="form-content">  
                <form id="uploadForm" enctype="multipart/form-data">  
                    <div class="drop-zone-wrapper">
                        <div class="drop-zone" id="dropZone">  
                            <div class="drop-zone__prompt" id="dropZonePrompt">
                                <div class="drop-zone__icon">
                                    <i class="mdi mdi-cloud-upload-outline"></i>
                                </div>
                                <div class="drop-zone__title">Arraste sua planilha aqui</div>
                                <div class="drop-zone__subtitle">ou clique para selecionar o arquivo</div>
                                <div class="drop-zone__formats">
                                    <i class="mdi mdi-microsoft-excel"></i>
                                    <span>Formato aceito: .xlsx</span>
                                </div>
                            </div>
                            <input type="file" name="file" class="drop-zone__input" id="file" accept=".xlsx" required>  
                        </div>
                    </div>

                    <div class="progress-container" id="progressContainer">
                        <div class="progress-bar-wrapper">
                            <div class="progress-bar" id="progressBar"></div>
                        </div>
                        <div class="progress-text">
                            <span id="progressStatus">Processando...</span>
                            <span id="progressPercent">0%</span>
                        </div>
                    </div>

                    <button type="button" class="upload-button" id="uploadButton" onclick="uploadFile()">  
                        <span class="button-text">
                            <i class="mdi mdi-upload"></i>
                            Enviar Planilha
                        </span>  
                        <span class="button-loading d-none">  
                            <i class="mdi mdi-loading mdi-spin"></i>  
                            Processando...  
                        </span>  
                    </button>  
                </form>

                <div class="info-cards">
                    <div class="info-card">
                        <div class="info-card-icon blue">
                            <i class="mdi mdi-identifier"></i>
                        </div>
                        <div class="info-card-text">
                            <strong>CÓDIGO</strong>
                            Ex: 13.1.15, 14.a, 14.B
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon green">
                            <i class="mdi mdi-text-box-outline"></i>
                        </div>
                        <div class="info-card-text">
                            <strong>ATOS</strong>
                            Descrição do ato
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon purple">
                            <i class="mdi mdi-currency-usd"></i>
                        </div>
                        <div class="info-card-text">
                            <strong>Valores</strong>
                            EMOLUMENTOS, FERC, FADEP...
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-card-icon orange">
                            <i class="mdi mdi-calculator"></i>
                        </div>
                        <div class="info-card-text">
                            <strong>TOTAL</strong>
                            Soma dos valores
                        </div>
                    </div>
                </div>

                <div id="message" class="notification-container"></div>  
            </div>  
        </div>  
    </div>  

    <script src="../script/jquery-3.5.1.min.js"></script>  
    <script src="../script/bootstrap.min.js"></script>  
    <script src="../script/jquery.mask.min.js"></script>  
    <script>
        const dropZone = document.getElementById('dropZone');
        const dropZonePrompt = document.getElementById('dropZonePrompt');
        const fileInput = document.getElementById('file');
        const uploadButton = document.getElementById('uploadButton');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressStatus = document.getElementById('progressStatus');
        const progressPercent = document.getElementById('progressPercent');

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function showFilePreview(file) {
            dropZonePrompt.innerHTML = `
                <div class="drop-zone__preview">
                    <div class="drop-zone__preview-icon">
                        <i class="mdi mdi-file-excel"></i>
                    </div>
                    <div class="drop-zone__preview-info">
                        <div class="drop-zone__preview-name">${file.name}</div>
                        <div class="drop-zone__preview-size">${formatFileSize(file.size)}</div>
                    </div>
                    <button type="button" class="drop-zone__preview-remove" onclick="removeFile(event)">
                        <i class="mdi mdi-close"></i>
                    </button>
                </div>
            `;
        }

        function resetDropZone() {
            dropZonePrompt.innerHTML = `
                <div class="drop-zone__icon">
                    <i class="mdi mdi-cloud-upload-outline"></i>
                </div>
                <div class="drop-zone__title">Arraste sua planilha aqui</div>
                <div class="drop-zone__subtitle">ou clique para selecionar o arquivo</div>
                <div class="drop-zone__formats">
                    <i class="mdi mdi-microsoft-excel"></i>
                    <span>Formato aceito: .xlsx</span>
                </div>
            `;
            fileInput.value = '';
        }

        function removeFile(event) {
            event.stopPropagation();
            resetDropZone();
        }

        dropZone.addEventListener('click', (e) => {
            if (!e.target.closest('.drop-zone__preview-remove')) {
                fileInput.click();
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (fileInput.files.length) {
                const file = fileInput.files[0];
                if (file.name.endsWith('.xlsx')) {
                    showFilePreview(file);
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Formato Inválido',
                        text: 'Por favor, selecione um arquivo .xlsx'
                    });
                    fileInput.value = '';
                }
            }
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drop-zone--over');
        });

        ['dragleave', 'dragend'].forEach((type) => {
            dropZone.addEventListener(type, (e) => {
                dropZone.classList.remove('drop-zone--over');
            });
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drop-zone--over');

            if (e.dataTransfer.files.length) {
                const file = e.dataTransfer.files[0];
                if (file.name.endsWith('.xlsx')) {
                    fileInput.files = e.dataTransfer.files;
                    showFilePreview(file);
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Formato Inválido',
                        text: 'Por favor, selecione um arquivo .xlsx'
                    });
                }
            }
        });

        function updateProgress(percent, status) {
            progressBar.style.width = percent + '%';
            progressPercent.textContent = percent + '%';
            if (status) {
                progressStatus.textContent = status;
            }
        }

        function uploadFile() {
            const button = uploadButton;
            const buttonText = button.querySelector('.button-text');
            const buttonLoading = button.querySelector('.button-loading');
            const form = document.getElementById('uploadForm');

            if (fileInput.files.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Atenção',
                    text: 'Por favor, selecione um arquivo .xlsx'
                });
                return;
            }

            const file = fileInput.files[0];
            if (!file.name.endsWith('.xlsx')) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Formato Inválido',
                    text: 'Por favor, selecione um arquivo .xlsx'
                });
                return;
            }

            button.disabled = true;
            buttonText.classList.add('d-none');
            buttonLoading.classList.remove('d-none');
            progressContainer.classList.add('active');
            updateProgress(10, 'Enviando arquivo...');

            const formData = new FormData(form);

            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 50);
                    updateProgress(percentComplete, 'Enviando arquivo...');
                }
            });

            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        updateProgress(100, 'Concluído!');
                        
                        setTimeout(() => {
                            progressContainer.classList.remove('active');
                            updateProgress(0, 'Processando...');

                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Sucesso!',
                                    html: `<p>${data.message}</p><p><strong>${data.insertedCount}</strong> registros foram inseridos com sucesso.</p>`,
                                    confirmButtonColor: '#4361ee'
                                });
                                resetDropZone();
                            } else if (data.status === 'partial') {
                                let html = `<p>${data.message}</p>`;
                                html += `<p><strong>${data.insertedCount}</strong> registros inseridos.</p>`;
                                if (data.errors && data.errors.length > 0) {
                                    html += `<p style="color: #ef4444; margin-top: 10px;"><strong>Erros encontrados:</strong></p>`;
                                    html += `<div style="max-height: 200px; overflow-y: auto; text-align: left; font-size: 13px;">`;
                                    data.errors.slice(0, 10).forEach(err => {
                                        html += `<p style="margin: 5px 0; padding: 5px; background: #fef2f2; border-radius: 4px;">Linha ${err.linha}: ${err.erro}</p>`;
                                    });
                                    if (data.errors.length > 10) {
                                        html += `<p style="color: #64748b;">... e mais ${data.errors.length - 10} erros</p>`;
                                    }
                                    html += `</div>`;
                                }
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Importação Parcial',
                                    html: html,
                                    confirmButtonColor: '#4361ee'
                                });
                                resetDropZone();
                            } else if (data.status === 'ignored') {
                                let ignoredList = data.ignoredAtos.map(ato => `Código '${ato}' já existe`).join('<br>');
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Registros Ignorados',
                                    html: `<p>Os seguintes registros já existem no banco:</p><div style="max-height: 200px; overflow-y: auto; text-align: left; margin-top: 10px;">${ignoredList}</div>`,
                                    confirmButtonColor: '#4361ee'
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erro',
                                    text: data.message,
                                    confirmButtonColor: '#4361ee'
                                });
                            }
                        }, 500);
                    } catch (e) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: 'Erro ao processar resposta do servidor',
                            confirmButtonColor: '#4361ee'
                        });
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao enviar arquivo',
                        confirmButtonColor: '#4361ee'
                    });
                }

                button.disabled = false;
                buttonText.classList.remove('d-none');
                buttonLoading.classList.add('d-none');
            });

            xhr.addEventListener('error', () => {
                progressContainer.classList.remove('active');
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Erro de conexão ao enviar arquivo',
                    confirmButtonColor: '#4361ee'
                });
                button.disabled = false;
                buttonText.classList.remove('d-none');
                buttonLoading.classList.add('d-none');
            });

            xhr.open('POST', 'process_upload.php', true);
            xhr.send(formData);

            // Simular progresso de processamento
            let progress = 50;
            const progressInterval = setInterval(() => {
                if (progress < 90) {
                    progress += Math.random() * 10;
                    updateProgress(Math.round(progress), 'Processando planilha...');
                } else {
                    clearInterval(progressInterval);
                }
            }, 500);
        }
    </script>  
    <?php include(__DIR__ . '/../rodape.php'); ?>  
</body>  
</html>