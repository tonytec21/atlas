<?php  
// Configuração  
$uploadDir = __DIR__ . '/uploads/';  
$pythonPath = 'C:\Users\tonyt\AppData\Local\Programs\Python\Python313\python.exe';  

// Criar diretório de uploads se não existir  
if (!file_exists($uploadDir)) {  
    mkdir($uploadDir, 0777, true);  
}  

// Verificar Python  
$pythonAvailable = false;  
$pythonVersion = "";  
exec("\"$pythonPath\" --version 2>&1", $output, $returnVar);  
if ($returnVar === 0) {  
    $pythonAvailable = true;  
    $pythonVersion = $output[0] ?? 'Python disponível';  
}  

// Verificar dependências Python  
$dependenciesOk = true;  
$missingDeps = [];  
if ($pythonAvailable) {  
    $requiredDeps = ['pdf2image', 'PIL', 'pypdf', 'reportlab'];  
    foreach ($requiredDeps as $dep) {  
        $cmd = "\"$pythonPath\" -c \"import " . ($dep == 'PIL' ? 'PIL' : $dep) . "\" 2>&1";  
        exec($cmd, $output, $returnVar);  
        if ($returnVar !== 0) {  
            $dependenciesOk = false;  
            $missingDeps[] = $dep;  
        }  
    }  
}  

// Verificar Poppler  
$popplerAvailable = file_exists('C:\Program Files\poppler-24.08.0\Library\bin\pdfinfo.exe');  

// Processar upload de arquivo  
if ($_SERVER['REQUEST_METHOD'] === 'POST') {  
    // Upload do arquivo PDF  
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {  
        $jobId = uniqid('job_');  
        $uploadedFile = $_FILES['pdf_file']['tmp_name'];  
        $pdfPath = $uploadDir . $jobId . '_input.pdf';  
        
        if (move_uploaded_file($uploadedFile, $pdfPath)) {  
            echo json_encode(['success' => true, 'job_id' => $jobId]);  
            exit;  
        } else {  
            echo json_encode(['error' => 'Falha ao mover o arquivo']);  
            exit;  
        }  
    }  
    
    // Iniciar processamento  
    if (isset($_POST['action']) && $_POST['action'] === 'start_process') {  
        $jobId = $_POST['job_id'] ?? '';  
        $dpi = intval($_POST['dpi'] ?? 200);  
        $numProcesses = $_POST['num_processes'] ?? 'auto';  
        
        if (empty($jobId)) {  
            echo json_encode(['error' => 'ID de trabalho não fornecido']);  
            exit;  
        }  
        
        $pdfPath = $uploadDir . $jobId . '_input.pdf';  
        $outputPath = $uploadDir . $jobId . '_output.zip';  
        $progressPath = $uploadDir . $jobId . '_progress.json';  
        $logPath = $uploadDir . $jobId . '_log.txt';  
        
        if (!file_exists($pdfPath)) {  
            echo json_encode(['error' => 'Arquivo não encontrado']);  
            exit;  
        }  
        
        // Inicializar arquivo de progresso  
        $progress = [  
            'current' => 0,  
            'total' => 0,  
            'percentage' => 0,  
            'status' => 'initializing',  
            'message' => 'Iniciando processamento...'  
        ];  
        file_put_contents($progressPath, json_encode($progress));  
        
        // Comando para executar o script Python  
        $scriptPath = __DIR__ . '/pdf_splitter.py';  
        $cmd = "\"$pythonPath\" \"$scriptPath\" \"$pdfPath\" \"$outputPath\" \"$progressPath\" $dpi $numProcesses > \"$logPath\" 2>&1";  
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {  
            // No Windows, usar sintaxe de start para execução em background  
            pclose(popen("start /B $cmd", "r"));  
        } else {  
            // Em sistemas Unix, usar & para execução em background  
            exec("$cmd &");  
        }  
        
        echo json_encode(['success' => true, 'job_id' => $jobId]);  
        exit;  
    }  
}  
?>  
<!DOCTYPE html>  
<html lang="pt-BR">  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>PDF Splitter | Divida seus PDFs</title>  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">  
    <style>  
        :root {  
            --primary: #3a86ff;  
            --primary-dark: #2667cc;  
            --secondary: #8338ec;  
            --success: #06d6a0;  
            --warning: #ffbe0b;  
            --danger: #ef476f;  
            --light: #f8f9fa;  
            --dark: #212529;  
        }  
        
        body {  
            background-color: #f8f9fa;  
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;  
            line-height: 1.6;  
            color: #333;  
            padding-top: 2rem;  
            padding-bottom: 3rem;  
        }  
        
        .main-container {  
            background-color: #fff;  
            border-radius: 1rem;  
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);  
            padding: 2.5rem;  
            margin-bottom: 2rem;  
            transition: transform 0.3s ease, box-shadow 0.3s ease;  
        }  
        
        .main-container:hover {  
            transform: translateY(-5px);  
            box-shadow: 0 0.75rem 2rem rgba(0, 0, 0, 0.15);  
        }  
        
        .page-header {  
            text-align: center;  
            margin-bottom: 2.5rem;  
        }  
        
        .page-title {  
            color: var(--primary);  
            font-weight: 700;  
            margin-bottom: 0.75rem;  
        }  
        
        .status-card {  
            padding: 1.5rem;  
            border-radius: 0.75rem;  
            margin-bottom: 2rem;  
            transition: all 0.3s ease;  
        }  
        
        .status-card.success {  
            background-color: rgba(6, 214, 160, 0.1);  
            border-left: 5px solid var(--success);  
        }  
        
        .status-card.warning {  
            background-color: rgba(255, 190, 11, 0.1);  
            border-left: 5px solid var(--warning);  
        }  
        
        .status-card.danger {  
            background-color: rgba(239, 71, 111, 0.1);  
            border-left: 5px solid var(--danger);  
        }  
        
        .status-card h3 {  
            color: var(--dark);  
            font-size: 1.4rem;  
            margin-bottom: 1rem;  
            font-weight: 600;  
        }  
        
        .status-card p, .status-card li {  
            margin-bottom: 0.5rem;  
            color: #495057;  
        }  
        
        .status-card code {  
            background-color: rgba(0, 0, 0, 0.05);  
            padding: 0.25rem 0.5rem;  
            border-radius: 0.25rem;  
            font-size: 0.9em;  
            font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace;  
        }  
        
        .feature-list {  
            padding-left: 1.5rem;  
            margin-bottom: 2rem;  
        }  
        
        .feature-list li {  
            margin-bottom: 0.75rem;  
            position: relative;  
        }  
        
        .feature-list li::before {  
            content: "";  
            position: absolute;  
            left: -1.5rem;  
            top: 0.5rem;  
            width: 0.75rem;  
            height: 0.75rem;  
            border-radius: 50%;  
            background-color: var(--primary);  
        }  
        
        .file-input-container {  
            position: relative;  
            margin-bottom: 1.5rem;  
        }  
        
        .file-drop-area {  
            position: relative;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            height: 150px;  
            padding: 20px;  
            border: 2px dashed #ced4da;  
            border-radius: 1rem;  
            transition: border-color 0.3s ease, background-color 0.3s ease;  
            background-color: #f8f9fa;  
            cursor: pointer;  
            margin-bottom: 1.5rem;  
        }  
        
        .file-drop-area:hover {  
            border-color: var(--primary);  
            background-color: rgba(58, 134, 255, 0.05);  
        }  
        
        .file-message {  
            text-align: center;  
            color: #6c757d;  
            font-size: 1.1rem;  
        }  
        
        .file-message i {  
            font-size: 2rem;  
            display: block;  
            margin-bottom: 0.75rem;  
            color: var(--primary);  
        }  
        
        .file-input {  
            position: absolute;  
            left: 0;  
            top: 0;  
            height: 100%;  
            width: 100%;  
            opacity: 0;  
            cursor: pointer;  
        }  
        
        .progress-section {  
            display: none;  
            margin-top: 2.5rem;  
        }  
        
        .progress-container {  
            height: 1.5rem;  
            background-color: #e9ecef;  
            border-radius: 1rem;  
            margin-bottom: 1rem;  
            overflow: hidden;  
        }  
        
        .progress-bar {  
            height: 100%;  
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);  
            border-radius: 1rem;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            color: white;  
            font-weight: 600;  
            transition: width 0.5s ease;  
        }  
        
        .btn-primary {  
            background-color: var(--primary);  
            border-color: var(--primary);  
            font-weight: 600;  
            padding: 0.625rem 1.5rem;  
            border-radius: 0.5rem;  
            transition: all 0.3s ease;  
        }  
        
        .btn-primary:hover {  
            background-color: var(--primary-dark);  
            border-color: var(--primary-dark);  
            transform: translateY(-2px);  
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);  
        }  
        
        .settings-card {  
            background-color: rgba(58, 134, 255, 0.05);  
            border-radius: 0.75rem;  
            padding: 1.5rem;  
            margin-bottom: 1.5rem;  
            border: 1px solid rgba(58, 134, 255, 0.2);  
        }  
        
        .settings-card h4 {  
            color: var(--primary);  
            font-size: 1.2rem;  
            margin-bottom: 1.25rem;  
            font-weight: 600;  
        }  
        
        .settings-row {  
            margin-bottom: 1.25rem;  
        }  
        
        .settings-row:last-child {  
            margin-bottom: 0;  
        }  
        
        .settings-row label {  
            font-weight: 500;  
            margin-bottom: 0.5rem;  
            color: #495057;  
        }  
        
        .form-select {  
            border-radius: 0.5rem;  
            border: 1px solid #ced4da;  
            padding: 0.625rem 1rem;  
            transition: border-color 0.3s ease, box-shadow 0.3s ease;  
        }  
        
        .form-select:focus {  
            border-color: var(--primary);  
            box-shadow: 0 0 0 0.25rem rgba(58, 134, 255, 0.25);  
        }  
        
        .settings-hint {  
            font-size: 0.875rem;  
            color: #6c757d;  
            margin-top: 0.5rem;  
        }  
        
        .download-button {  
            display: none;  
            margin-top: 1.5rem;  
            background-color: var(--success);  
            border-color: var(--success);  
            font-weight: 600;  
            padding: 0.625rem 1.5rem;  
            border-radius: 0.5rem;  
            transition: all 0.3s ease;  
        }  
        
        .download-button:hover {  
            background-color: #05b386;  
            border-color: #05b386;  
            transform: translateY(-2px);  
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);  
        }  
        
        .processing-spinner {  
            display: inline-block;  
            width: 1.5rem;  
            height: 1.5rem;  
            vertical-align: text-bottom;  
            border: 0.2em solid currentColor;  
            border-right-color: transparent;  
            border-radius: 50%;  
            animation: spinner 0.75s linear infinite;  
            margin-right: 0.5rem;  
        }  
        
        @keyframes spinner {  
            to { transform: rotate(360deg); }  
        }  
        
        .footer {  
            text-align: center;  
            color: #6c757d;  
            font-size: 0.9rem;  
            margin-top: 3rem;  
        }  
    </style>  
</head>  
<body>  
    <div class="container">  
        <div class="row justify-content-center">  
            <div class="col-md-10">  
                <div class="main-container">  
                    <div class="page-header">  
                        <h1 class="page-title"><i class="fas fa-file-pdf me-2"></i>PDF Splitter</h1>  
                        <p class="lead">Divida cada página do seu PDF em duas metades automaticamente</p>  
                    </div>  
                    
                    <!-- Status do Sistema -->  
                    <?php if ($pythonAvailable && $dependenciesOk && $popplerAvailable): ?>  
                        <div class="status-card success">  
                            <h3><i class="fas fa-check-circle me-2"></i>Sistema Pronto</h3>  
                            <p>Python detectado: <code><?php echo htmlspecialchars($pythonVersion); ?></code></p>  
                            <p>Poppler configurado: <code>C:\Program Files\poppler-24.08.0</code></p>  
                            <p>Todas as dependências estão instaladas e o sistema está pronto para processar seus PDFs.</p>  
                        </div>  
                    <?php elseif (!$pythonAvailable): ?>  
                        <div class="status-card danger">  
                            <h3><i class="fas fa-times-circle me-2"></i>Python Não Detectado</h3>  
                            <p>Não foi possível localizar o Python no caminho configurado:</p>  
                            <p><code><?php echo htmlspecialchars($pythonPath); ?></code></p>  
                            <p>Verifique se o Python está instalado e atualize o caminho no código.</p>  
                        </div>  
                    <?php elseif (!$popplerAvailable): ?>  
                        <div class="status-card warning">  
                            <h3><i class="fas fa-exclamation-triangle me-2"></i>Poppler Não Encontrado</h3>  
                            <p>O Poppler não foi encontrado no caminho esperado:</p>  
                            <p><code>C:\Program Files\poppler-24.08.0</code></p>  
                            <p>Certifique-se de que o Poppler está instalado corretamente neste local.</p>  
                        </div>  
                    <?php elseif (!$dependenciesOk): ?>  
                        <div class="status-card warning">  
                            <h3><i class="fas fa-exclamation-triangle me-2"></i>Dependências Python Faltando</h3>  
                            <p>As seguintes dependências Python estão faltando:</p>  
                            <ul>  
                                <?php foreach ($missingDeps as $dep): ?>  
                                    <li><code><?php echo htmlspecialchars($dep); ?></code></li>  
                                <?php endforeach; ?>  
                            </ul>  
                            <p>Instale-as com o comando:</p>  
                            <p><code>pip install <?php echo implode(' ', $missingDeps); ?></code></p>  
                        </div>  
                    <?php endif; ?>  
                    
                    <!-- Lista de Recursos -->  
                    <div class="card mb-4">  
                        <div class="card-body">  
                            <h3 class="card-title"><i class="fas fa-list-check me-2"></i>Recursos</h3>  
                            <ul class="feature-list mb-0">  
                                <li>Converte cada página do PDF em imagem de alta qualidade</li>  
                                <li>Divide as páginas verticalmente em duas metades iguais</li>  
                                <li>Remove bordas brancas desnecessárias para otimizar o espaço</li>  
                                <li>Gera PDFs individuais para cada metade</li>  
                                <li>Compacta todos os arquivos em um ZIP para download fácil</li>  
                            </ul>  
                        </div>  
                    </div>  
                    
                    <!-- Formulário de Upload -->  
                    <div id="upload-section">  
                        <form id="upload-form" method="post" enctype="multipart/form-data">  
                            <div class="file-input-container">  
                                <div class="file-drop-area" id="file-drop-area">  
                                    <div class="file-message">  
                                        <i class="fas fa-cloud-upload-alt"></i>  
                                        <span id="file-name">Arraste um arquivo PDF aqui ou clique para selecionar</span>  
                                    </div>  
                                    <input type="file" name="pdf_file" id="pdf-file" class="file-input" accept="application/pdf" required>  
                                </div>  
                            </div>  
                            
                            <div class="settings-card">  
                                <h4><i class="fas fa-sliders-h me-2"></i>Configurações</h4>  
                                <div class="row">  
                                    <div class="col-md-6">  
                                        <div class="settings-row">  
                                            <label for="dpi">Qualidade de Imagem:</label>  
                                            <select name="dpi" id="dpi" class="form-select">  
                                                <option value="300">Alta (300 DPI)</option>  
                                                <option value="200" selected>Média (200 DPI) - Recomendado</option>  
                                                <option value="150">Baixa (150 DPI)</option>  
                                                <option value="100">Muito Baixa (100 DPI)</option>  
                                            </select>  
                                            <p class="settings-hint">DPI mais alto = melhor qualidade, mas mais lento</p>  
                                        </div>  
                                    </div>  
                                    <div class="col-md-6">  
                                        <div class="settings-row">  
                                            <label for="num-processes">Processamento Paralelo:</label>  
                                            <select name="num_processes" id="num-processes" class="form-select">  
                                                <option value="auto" selected>Automático (Recomendado)</option>  
                                                <option value="1">1 CPU (Economia de memória)</option>  
                                                <option value="2">2 CPUs</option>  
                                                <option value="4">4 CPUs</option>  
                                                <option value="8">8 CPUs (Mais rápido)</option>  
                                            </select>  
                                            <p class="settings-hint">Mais CPUs = processamento mais rápido</p>  
                                        </div>  
                                    </div>  
                                </div>  
                            </div>  
                            
                            <div class="d-grid gap-2">  
                                <button type="submit" id="submit-button" class="btn btn-primary"  
                                        <?php if (!$pythonAvailable || !$dependenciesOk || !$popplerAvailable) echo 'disabled'; ?>>  
                                    <i class="fas fa-cog me-2"></i>Processar PDF  
                                </button>  
                            </div>  
                        </form>  
                    </div>  
                    
                    <!-- Seção de Progresso -->  
                    <div class="progress-section" id="progress-section">  
                        <div class="card mb-4">  
                            <div class="card-body">  
                                <h4 class="card-title d-flex align-items-center">  
                                    <div class="processing-spinner" id="processing-spinner"></div>  
                                    Processando seu PDF  
                                    <span class="badge bg-primary ms-auto" id="progress-percentage">0%</span>  
                                </h4>  
                                
                                <div class="progress-container">  
                                    <div class="progress-bar" id="progress-bar" style="width:0%">0%</div>  
                                </div>  
                                
                                <p class="mt-3 mb-0" id="progress-message">Iniciando processamento...</p>  
                                
                                <div class="d-grid gap-2 mt-4">  
                                    <a href="#" id="download-button" class="btn download-button">  
                                        <i class="fas fa-download me-2"></i>Baixar Resultado  
                                    </a>  
                                </div>  
                            </div>  
                        </div>  
                        
                        <div class="alert alert-info">  
                            <i class="fas fa-info-circle me-2"></i>  
                            O processamento pode levar alguns minutos dependendo do tamanho do arquivo.  
                            Não feche esta janela até que o processamento seja concluído.  
                        </div>  
                    </div>  
                </div>  
                
                <div class="footer">  
                    <p>PDF Splitter &copy; 2023 - Todos os direitos reservados</p>  
                    <p class="mb-0">Desenvolvido com <i class="fas fa-heart text-danger"></i> por Sua Empresa</p>  
                </div>  
            </div>  
        </div>  
    </div>  
    
    <script>  
        document.addEventListener('DOMContentLoaded', function() {  
            const form = document.getElementById('upload-form');  
            const uploadSection = document.getElementById('upload-section');  
            const progressSection = document.getElementById('progress-section');  
            const progressBar = document.getElementById('progress-bar');  
            const progressPercentage = document.getElementById('progress-percentage');  
            const progressMessage = document.getElementById('progress-message');  
            const downloadButton = document.getElementById('download-button');  
            const fileInput = document.getElementById('pdf-file');  
            const fileNameElement = document.getElementById('file-name');  
            const fileDropArea = document.getElementById('file-drop-area');  
            const processingSpinner = document.getElementById('processing-spinner');  
            
            let jobId = null;  
            let progressInterval = null;  
            let errorCount = 0;  
            
            // Configurar a área de arraste e solte  
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {  
                fileDropArea.addEventListener(eventName, preventDefaults, false);  
            });  
            
            function preventDefaults(e) {  
                e.preventDefault();  
                e.stopPropagation();  
            }  
            
            ['dragenter', 'dragover'].forEach(eventName => {  
                fileDropArea.addEventListener(eventName, highlight, false);  
            });  
            
            ['dragleave', 'drop'].forEach(eventName => {  
                fileDropArea.addEventListener(eventName, unhighlight, false);  
            });  
            
            function highlight() {  
                fileDropArea.classList.add('border-primary');  
                fileDropArea.style.backgroundColor = 'rgba(58, 134, 255, 0.05)';  
            }  
            
            function unhighlight() {  
                fileDropArea.classList.remove('border-primary');  
                fileDropArea.style.backgroundColor = '#f8f9fa';  
            }  
            
            fileDropArea.addEventListener('drop', handleDrop, false);  
            
            function handleDrop(e) {  
                const dt = e.dataTransfer;  
                const files = dt.files;  
                
                if (files && files.length) {  
                    fileInput.files = files;  
                    updateFileName();  
                }  
            }  
            
            // Atualizar nome do arquivo quando selecionado  
            fileInput.addEventListener('change', updateFileName);  
            
            function updateFileName() {  
                if (fileInput.files.length > 0) {  
                    const file = fileInput.files[0];  
                    fileNameElement.textContent = file.name;  
                    fileDropArea.classList.add('border-primary');  
                } else {  
                    fileNameElement.textContent = 'Arraste um arquivo PDF aqui ou clique para selecionar';  
                    fileDropArea.classList.remove('border-primary');  
                }  
            }  
            
            // Envio do formulário  
            form.addEventListener('submit', function(e) {  
                e.preventDefault();  
                
                if (!fileInput.files.length) {  
                    alert('Por favor, selecione um arquivo PDF.');  
                    return;  
                }  
                
                // Preparar para envio  
                uploadSection.style.display = 'none';  
                progressSection.style.display = 'block';  
                progressBar.style.width = '0%';  
                progressBar.textContent = '0%';  
                progressPercentage.textContent = '0%';  
                progressMessage.textContent = 'Enviando arquivo...';  
                downloadButton.style.display = 'none';  
                
                // Enviar arquivo  
                const formData = new FormData(form);  
                
                fetch('index.php', {  
                    method: 'POST',  
                    body: formData  
                })  
                .then(response => response.json())  
                .then(data => {  
                    if (data.error) {  
                        throw new Error(data.error);  
                    }  
                    
                    if (data.job_id) {  
                        jobId = data.job_id;  
                        progressMessage.textContent = 'Arquivo enviado. Iniciando processamento...';  
                        
                        // Iniciar processamento  
                        const processFormData = new FormData();  
                        processFormData.append('action', 'start_process');  
                        processFormData.append('job_id', jobId);  
                        processFormData.append('dpi', document.getElementById('dpi').value);  
                        processFormData.append('num_processes', document.getElementById('num-processes').value);  
                        
                        return fetch('index.php', {  
                            method: 'POST',  
                            body: processFormData  
                        });  
                    }  
                })  
                .then(response => response.json())  
                .then(data => {  
                    if (data.error) {  
                        throw new Error(data.error);  
                    }  
                    
                    // Iniciar verificação de progresso  
                    errorCount = 0;  
                    progressInterval = setInterval(checkProgress, 1000);  
                })  
                .catch(error => {  
                    progressMessage.textContent = 'Erro: ' + error.message;  
                    console.error('Erro:', error);  
                });  
            });  
            
            // Verificar progresso do processamento  
            function checkProgress() {  
                if (!jobId) return;  
                
                fetch(`check_progress.php?job_id=${jobId}&t=${Date.now()}`)  
                .then(response => response.json())  
                .then(data => {  
                    if (data.error) {  
                        errorCount++;  
                        if (errorCount > 5) {  
                            clearInterval(progressInterval);  
                            progressMessage.textContent = 'Erro na verificação de progresso: ' + data.error;  
                        }  
                        return;  
                    }  
                    
                    errorCount = 0;  
                    
                    // Atualizar progresso  
                    const percentage = data.percentage || 0;  
                    progressBar.style.width = `${percentage}%`;  
                    progressBar.textContent = `${percentage}%`;  
                    progressPercentage.textContent = `${percentage}%`;  
                    
                    // Atualizar mensagem  
                    if (data.message) {  
                        progressMessage.textContent = data.message;  
                    } else if (data.current && data.total) {  
                        progressMessage.textContent = `Processando página ${data.current} de ${data.total}`;  
                    }  
                    
                    // Verificar status  
                    if (data.status === 'completed') {  
                        clearInterval(progressInterval);  
                        progressBar.style.width = '100%';  
                        progressBar.textContent = '100%';  
                        progressPercentage.textContent = '100%';  
                        progressMessage.textContent = 'Processamento concluído com sucesso!';  
                        processingSpinner.style.display = 'none';  
                        
                        // Mostrar botão de download  
                        if (data.download_url) {  
                            downloadButton.href = data.download_url;  
                            downloadButton.style.display = 'block';  
                        }  
                    }   
                    else if (data.status === 'error') {  
                        clearInterval(progressInterval);  
                        progressMessage.textContent = 'Erro: ' + (data.message || 'Erro desconhecido');  
                        processingSpinner.style.display = 'none';  
                    }  
                })  
                .catch(error => {  
                    console.error('Erro ao verificar progresso:', error);  
                    errorCount++;  
                    
                    if (errorCount > 5) {  
                        clearInterval(progressInterval);  
                        progressMessage.textContent = 'Erro de conexão ao verificar progresso.';  
                    }  
                });  
            }  
        });  
    </script>  
</body>  
</html>