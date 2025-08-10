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
    <title>Atlas - Cadastro de Tarefas</title>  
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">  
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">  
    <link rel="stylesheet" href="../style/css/style.css">  
    <link rel="icon" href="../style/img/favicon.png" type="image/png">  
        <style>  
        /* Variáveis de cores para tema claro (padrão) */  
        :root {  
            --background-primary: #f8f9fa;  
            --background-secondary: #ffffff;  
            --text-primary: #212529;  
            --text-secondary: #6c757d;  
            --border-color: #dee2e6;  
            --accent-color: #0d6efd;  
            --accent-hover: #0b5ed7;  
            --danger-color: #dc3545;  
            --danger-hover: #bb2d3b;  
            --input-background: #ffffff;  
            --input-border: #ced4da;  
            --shadow-color: rgba(0, 0, 0, 0.1);  
        }  

        /* Variáveis de cores para tema escuro */  
        body.dark-mode {  
            --background-primary: #1a1d21;  
            --background-secondary: #242729;  
            --text-primary: #ffffff;  
            --text-secondary: #adb5bd;  
            --border-color: #373b3e;  
            --input-background: #2a2e32;  
            --input-border: #373b3e;  
            --shadow-color: rgba(0, 0, 0, 0.25);  
        }  

        .task-form {  
            background: var(--background-secondary);  
            border-radius: 8px;  
            padding: 25px;  
            margin: 20px 0;  
            box-shadow: 0 2px 6px var(--shadow-color);  
        }  

        .form-label {  
            color: var(--text-primary);  
            font-weight: 500;  
            margin-bottom: 0.5rem;  
        }  

        .form-control-modern {  
            background: var(--input-background);  
            border: 1px solid var(--input-border);  
            color: var(--text-primary);  
            padding: 0.575rem 1rem;  
            border-radius: 4px;  
            width: 100%;  
            transition: all 0.2s ease-in-out;  
        }  

        .form-control-modern:focus {  
            border-color: var(--accent-color);  
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);  
            outline: none;  
        }  

        .form-row {  
            display: flex;  
            margin: 0 -15px 1.5rem -15px;  
            border-bottom: 1px solid var(--border-color);  
            padding-bottom: 1.5rem;  
        }  

        .form-group {  
            padding: 0 15px;  
            margin-bottom: 1rem;  
        }  

        /* Upload área */  
        .file-upload-wrapper {  
            border: 2px dashed var(--border-color);  
            background: var(--background-primary);  
            padding: 2rem;  
            text-align: center;  
            border-radius: 8px;  
            cursor: pointer;  
            transition: all 0.3s ease;  
            position: relative;  
        }  

        .file-upload-wrapper:hover {  
            border-color: var(--accent-color);  
            background: var(--background-primary);  
        }  

        .file-upload-icon {  
            color: var(--accent-color);  
            font-size: 2rem;  
            margin-bottom: 1rem;  
        }  

        .modern-file-input {  
            position: absolute;  
            width: 100%;  
            height: 100%;  
            top: 0;  
            left: 0;  
            opacity: 0;  
            cursor: pointer;  
        }  

        .upload-text {  
            color: var(--text-secondary);  
            font-size: 0.875rem;  
        }  

        /* Selected Files */  
        .selected-files {  
            margin-top: 1rem;  
        }  

        .file-item {  
            display: flex;  
            align-items: center;  
            justify-content: space-between;  
            padding: 0.5rem 1rem;  
            background: var(--background-primary);  
            border: 1px solid var(--border-color);  
            border-radius: 4px;  
            margin-bottom: 0.5rem;  
        }  

        .file-info {  
            display: flex;  
            align-items: center;  
            gap: 0.5rem;  
            color: var(--text-primary);  
        }  

        .file-name {  
            font-size: 0.875rem;  
        }  

        .file-size {  
            color: var(--text-secondary);  
            font-size: 0.75rem;  
        }  

        .remove-file {  
            background: none;  
            border: none;  
            color: var(--danger-color);  
            cursor: pointer;  
            padding: 0.25rem;  
            transition: all 0.2s;  
        }  

        .remove-file:hover {  
            color: var(--danger-hover);  
            transform: scale(1.1);  
        }  

        /* Botão Salvar */  
        .btn-save {  
            background: var(--accent-color);  
            color: white;  
            border: none;  
            padding: 0.75rem 1.5rem;  
            border-radius: 4px;  
            font-weight: 500;  
            width: 100%;  
            transition: all 0.2s ease;  
        }  

        .btn-save:hover {  
            background: var(--accent-hover);  
            transform: translateY(-1px);  
        }  

        /* Seleções */  
        select.form-control-modern {  
            appearance: none;  
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");  
            background-repeat: no-repeat;  
            background-position: right 1rem center;  
            padding-right: 2.5rem;  
        }  

        /* Ajustes para tema claro */  
        body {  
            background-color: #f0f2f5;  
        }  

        /* Ajustes para tema escuro */  
        body.dark-mode {  
            background-color: #1a1d21;  
        }  

        body.dark-mode .task-form {  
            background: var(--background-secondary);  
        }  

        body.dark-mode .form-control-modern {  
            background: var(--input-background);  
            border-color: var(--input-border);  
        }  

        body.dark-mode .file-upload-wrapper {  
            background: var(--background-primary);  
            border-color: var(--border-color);  
        }  

        /* Responsividade */  
        @media (max-width: 768px) {  
            .form-row {  
                flex-direction: column;  
            }  

            .form-group {  
                width: 100%;  
            }  
        }  
    </style>
</head>  
<body class="light-mode">  
    <?php include(__DIR__ . '/../menu.php'); ?>  

    <div id="main" class="main-content">  
        <div class="container">  
            <!-- HERO / TÍTULO -->
                <section class="page-hero">
                <div class="title-row">
                    <div class="title-icon"><i class="fa fa-tasks"></i></div>
                    <div class="title-texts">
                    <h1>Cadastro de Tarefas</h1>
                    <div class="subtitle muted">Crie, atribua e acompanhe tarefas com filtros rápidos e status em tempo real.</div>
                    </div>
                </div>
                </section>
                <hr>

            <div class="task-form">                  
                <form id="taskForm" enctype="multipart/form-data" method="POST" action="save_task.php">  
                    <div class="form-row">  
                        <div class="form-group col-md-6">  
                            <label class="form-label">Título da Tarefa:</label>  
                            <input type="text" class="form-control-modern" id="title" name="title" required>  
                        </div>  
                        <div class="form-group col-md-3">  
                            <label class="form-label">Categoria:</label>  
                            <select class="form-control-modern" id="category" name="category" required>  
                                <option value="">Selecione</option>  
                                <?php  
                                $sql = "SELECT id, titulo FROM categorias WHERE status = 'ativo'";  
                                $result = $conn->query($sql);  
                                if ($result->num_rows > 0) {  
                                    while($row = $result->fetch_assoc()) {  
                                        echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                    }  
                                }  
                                ?>  
                            </select>  
                        </div>  
                      
                        <div class="form-group col-md-3">  
                            <label class="form-label">Data Limite para Conclusão:</label>  
                            <input type="datetime-local" class="form-control-modern" id="deadline" name="deadline" required>  
                        </div>  
                        <div class="form-group col-md-3">  
                            <label class="form-label">Nível de Prioridade:</label>  
                            <select class="form-control-modern" id="priority" name="priority" required>  
                                <option value="">Selecione</option>  
                                <option value="Baixa">Baixa</option>  
                                <option value="Média">Média</option>  
                                <option value="Alta">Alta</option>  
                                <option value="Crítica">Crítica</option>  
                            </select>  
                        </div>  
                        <div class="form-group col-md-3">  
                            <label class="form-label">Funcionário Responsável:</label>  
                            <select class="form-control-modern" id="employee" name="employee" required>  
                                <option value="">Selecione</option>  
                                <?php  
                                $sql = "SELECT id, nome_completo FROM funcionarios WHERE status = 'ativo'";  
                                $result = $conn->query($sql);  
                                $loggedInUser = $_SESSION['username'];  
                                if ($result->num_rows > 0) {  
                                    while($row = $result->fetch_assoc()) {  
                                        $selected = ($row['nome_completo'] == $loggedInUser) ? 'selected' : '';  
                                        echo "<option value='" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "' $selected>" .   
                                            htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                    }  
                                }  
                                ?>  
                            </select>  
                        </div>

                        <div class="form-group col-md-3">
                            <label class="form-label">Revisor (Opcional):</label>
                            <select class="form-control-modern" id="reviewer" name="reviewer">
                                <option value="">Selecione</option>
                                <?php  
                                $sql = "SELECT id, nome_completo FROM funcionarios WHERE status = 'ativo'";  
                                $result = $conn->query($sql);  
                                if ($result->num_rows > 0) {  
                                    while($row = $result->fetch_assoc()) {  
                                        echo "<option value='" . htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "'>" .   
                                            htmlspecialchars($row['nome_completo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                    }  
                                }  
                                ?>  
                            </select>
                        </div>

                        <div class="form-group col-md-3">  
                            <label class="form-label">Origem:</label>  
                            <select class="form-control-modern" id="origin" name="origin" required>  
                                <option value="">Selecione</option>  
                                <?php  
                                $sql = "SELECT id, titulo FROM origem WHERE status = 'ativo'";  
                                $result = $conn->query($sql);  
                                if ($result->num_rows > 0) {  
                                    while($row = $result->fetch_assoc()) {  
                                        echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['titulo'], ENT_QUOTES, 'UTF-8') . "</option>";  
                                    }  
                                }  
                                ?>  
                            </select>  
                        </div>  
                    </div>  

                    <div class="form-row">  
                        <div class="form-group col-12">  
                            <label class="form-label">Descrição:</label>  
                            <textarea class="form-control-modern" id="description" name="description" rows="5"></textarea>  
                        </div>  
                    </div>  

                    <div class="form-row">  
                        <div class="form-group col-12">  
                            <label class="form-label">Anexos:</label>  
                            <div class="file-upload-wrapper">  
                                <input type="file" id="attachments" name="attachments[]" multiple class="modern-file-input">  
                                <div class="file-upload-icon">  
                                    <i class="fa fa-cloud-upload"></i>  
                                </div>  
                                <div>Arraste os arquivos ou clique para selecionar</div>  
                            </div>  
                            <div id="selectedFiles" class="selected-files"></div>  
                        </div>  
                    </div>  

                    <input type="hidden" id="createdBy" name="createdBy" value="<?php echo $_SESSION['username']; ?>">  
                    <input type="hidden" id="createdAt" name="createdAt" value="<?php echo date('Y-m-d H:i:s'); ?>">  

                    <button type="submit" class="btn-save">Salvar Tarefa</button>  
                </form>  
            </div>  
        </div>  
    </div>  

    <script src="../script/jquery-3.5.1.min.js"></script>  
    <script src="../script/bootstrap.min.js"></script>  
    <script src="../script/jquery.mask.min.js"></script>  
    <script src="../script/toastr.min.js"></script>  
    
    <script>  
    document.addEventListener('DOMContentLoaded', function() {  
        // Configuração da data mínima  
        const deadlineInput = document.getElementById('deadline');  
        const now = new Date();  
        const year = now.getFullYear();  
        const month = ('0' + (now.getMonth() + 1)).slice(-2);  
        const day = ('0' + now.getDate()).slice(-2);  
        const hours = ('0' + now.getHours()).slice(-2);  
        const minutes = ('0' + now.getMinutes()).slice(-2);  
        
        const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;  
        deadlineInput.min = minDateTime;  

        // Gerenciamento de arquivos  
        const fileInput = document.getElementById('attachments');  
        const selectedFilesDiv = document.getElementById('selectedFiles');  
        const uploadText = document.querySelector('.file-upload-wrapper div:last-child');  
        let filesArray = [];  

        fileInput.addEventListener('change', function(e) {  
            const files = Array.from(e.target.files);  
            updateFileList(files);  
        });  

        function updateFileList(newFiles) {  
            filesArray = newFiles;  
            selectedFilesDiv.innerHTML = '';  

            filesArray.forEach((file, index) => {  
                const fileItem = document.createElement('div');  
                fileItem.className = 'file-item';  

                const fileInfo = document.createElement('div');  
                fileInfo.className = 'file-info';  
                
                let fileIcon = 'fa-file-o';  
                if (file.type.includes('image')) fileIcon = 'fa-file-image-o';  
                else if (file.type.includes('pdf')) fileIcon = 'fa-file-pdf-o';  
                else if (file.type.includes('word')) fileIcon = 'fa-file-word-o';  
                else if (file.type.includes('excel')) fileIcon = 'fa-file-excel-o';  

                fileInfo.innerHTML = `  
                    <i class="fa ${fileIcon}"></i>  
                    <span class="file-name">${file.name}</span>  
                    <span class="file-size">(${formatFileSize(file.size)})</span>  
                `;  

                const removeButton = document.createElement('button');  
                removeButton.className = 'remove-file';  
                removeButton.innerHTML = '<i class="fa fa-times"></i>';  
                removeButton.onclick = () => removeFile(index);  

                fileItem.appendChild(fileInfo);  
                fileItem.appendChild(removeButton);  
                selectedFilesDiv.appendChild(fileItem);  
            });  

            uploadText.textContent = filesArray.length > 0 ? 'Adicionar mais arquivos' : 'Arraste os arquivos ou clique para selecionar';  
        }  

        function removeFile(index) {  
            const dt = new DataTransfer();  
            filesArray.forEach((file, i) => {  
                if (i !== index) dt.items.add(file);  
            });  
            fileInput.files = dt.files;  
            updateFileList(Array.from(dt.files));  
        }  

        function formatFileSize(bytes) {  
            if (bytes === 0) return '0 Bytes';  
            const k = 1024;  
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];  
            const i = Math.floor(Math.log(bytes) / Math.log(k));  
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];  
        }  

        // Drag and Drop  
        const dropZone = document.querySelector('.file-upload-wrapper');  

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {  
            dropZone.addEventListener(eventName, preventDefaults, false);  
        });  

        function preventDefaults(e) {  
            e.preventDefault();  
            e.stopPropagation();  
        }  

        ['dragenter', 'dragover'].forEach(eventName => {  
            dropZone.addEventListener(eventName, highlight, false);  
        });  

        ['dragleave', 'drop'].forEach(eventName => {  
            dropZone.addEventListener(eventName, unhighlight, false);  
        });  

        function highlight(e) {  
            const accentColor = getComputedStyle(document.documentElement)  
                .getPropertyValue('--accent-color').trim() || '#0d6efd';  
            dropZone.style.borderColor = accentColor;  
            dropZone.style.background = 'rgba(0, 123, 255, 0.1)';  
        }  

        function unhighlight(e) {  
            dropZone.style.borderColor = '#dee2e6';  
            dropZone.style.background = 'transparent';  
        }    

        dropZone.addEventListener('drop', handleDrop, false);  

        function handleDrop(e) {  
            const dt = e.dataTransfer;  
            const files = Array.from(dt.files);  
            fileInput.files = dt.files;  
            updateFileList(files);  
        }  

        // Formulário  
            const taskForm = document.getElementById('taskForm');  
            taskForm.addEventListener('submit', function(e) {  
                e.preventDefault();  
                
                // Validação básica  
                const requiredFields = taskForm.querySelectorAll('[required]');  
                let isValid = true;  

                requiredFields.forEach(field => {  
                    if (!field.value) {  
                        isValid = false;  
                        field.classList.add('is-invalid');  
                    } else {  
                        field.classList.remove('is-invalid');  
                    }  
                });  

                if (!isValid) {  
                    toastr.error('Por favor, preencha todos os campos obrigatórios.');  
                    return;  
                }  

                // Desabilitar o botão de submit e mostrar loading  
                const submitButton = taskForm.querySelector('button[type="submit"]');  
                submitButton.disabled = true;  
                submitButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Salvando...';  

                // Envio do formulário  
                const formData = new FormData(taskForm);  
                
                fetch('save_task.php', {  
                    method: 'POST',  
                    body: formData  
                })  
                .then(response => {  
                    if (!response.ok) {  
                        throw new Error('Erro na rede ou servidor');  
                    }  
                    return response.text().then(text => {  
                        try {  
                            return JSON.parse(text);  
                        } catch (e) {  
                            console.error('Resposta do servidor:', text);  
                            throw new Error('Resposta inválida do servidor');  
                        }  
                    });  
                })  
                .then(data => {  
                    if (data.success) {  
                        toastr.success('Tarefa salva com sucesso!');  
                        // Reduzir o tempo de espera para 500ms  
                        setTimeout(() => {  
                            window.location.href = `index.php?token=${data.token}`;  
                        }, 500);  
                    } else {  
                        toastr.error(data.message || 'Erro ao salvar a tarefa.');  
                        // Reabilitar o botão em caso de erro  
                        submitButton.disabled = false;  
                        submitButton.innerHTML = 'Salvar Tarefa';  
                    }  
                })  
                .catch(error => {  
                    console.error('Erro:', error);  
                    toastr.error('Erro ao processar a requisição.');  
                    // Reabilitar o botão em caso de erro  
                    submitButton.disabled = false;  
                    submitButton.innerHTML = 'Salvar Tarefa';  
                });  
            });  

            // Configuração do Toastr para mensagens mais rápidas  
            toastr.options = {  
                "closeButton": true,  
                "debug": false,  
                "newestOnTop": false,  
                "progressBar": true,  
                "positionClass": "toast-top-right",  
                "preventDuplicates": true,  
                "onclick": null,  
                "showDuration": "200",  
                "hideDuration": "500",  
                "timeOut": "2000",  
                "extendedTimeOut": "500",  
                "showEasing": "swing",  
                "hideEasing": "linear",  
                "showMethod": "fadeIn",  
                "hideMethod": "fadeOut"  
            }; 
    });  
    </script>  

    <?php include(__DIR__ . '/../rodape.php'); ?>  
</body>  
</html>