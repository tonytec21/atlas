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

        body.dark-mode .timeline::before { background: #444; }  
        body.dark-mode .timeline-item .timeline-panel { background: #333; border-color: #444; color: #ddd; }  
        body.dark-mode .timeline-item .timeline-panel::before { border-left-color: #444; }  
        body.dark-mode .timeline-item .timeline-panel::after { border-left-color: #333; }  

        .center-form {  
            display: flex;  
            justify-content: center;  
            align-items: center;  
            min-height: 70vh;  
        }  

        .form-group-horizontal {  
            display: flex;  
            align-items: center;  
            justify-content: center;  
        }  

        .form-group-horizontal .btn {  
            margin-left: 10px;  
        }  

        .notification-container {  
            margin-top: 20px;  
            overflow: hidden;  
        }  

        .drop-zone {  
            max-width: 500px;  
            width: 100%;  
            height: 200px;  
            padding: 25px;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
            text-align: center;  
            font-size: 1.2rem;  
            font-weight: 500;  
            cursor: pointer;  
            color: #777;  
            border: 2px dashed #3498db;  
            border-radius: 10px;  
            background-color: #f8f9fa;  
            transition: all 0.3s ease;  
            margin: 0 auto 20px;  
        }  

        .drop-zone:hover {  
            background-color: #eef7fe;  
        }  

        .drop-zone.drop-zone--over {  
            border-style: solid;  
            background-color: #e3f2fd;  
        }  

        .drop-zone__input {  
            display: none;  
        }  

        .drop-zone__prompt {  
            display: flex;  
            flex-direction: column;  
            align-items: center;  
            gap: 10px;  
        }  

        .drop-zone__thumb {  
            width: auto;  
            height: 100%;  
            border-radius: 10px;  
            overflow: hidden;  
            background-color: #f8f9fa;  
            background-size: cover;  
            position: relative;  
        }  

        .drop-zone__thumb::after {  
            content: attr(data-label);  
            position: relative;  
            bottom: 0;  
            left: 0;  
            width: 100%;  
            padding: 5px 0;  
            color: #777;  
            background: rgba(255, 255, 255, 0.75);  
            font-size: 14px;  
            text-align: center;  
        }  

        body.dark-mode .drop-zone__thumb {  
            background-color: #2d3436;  
        }  

        body.dark-mode .drop-zone__thumb::after {  
            color: #ddd;  
            background: rgba(45, 52, 54, 0.85);  
        }

        .upload-button {  
            display: block;  
            position: relative;  
            width: 500px;  
            max-width: 100%;  
            padding: 12px;  
            background-color: #3498db;  
            color: white;  
            border: none;  
            border-radius: 8px;  
            font-size: 16px;  
            font-weight: 500;  
            cursor: pointer;  
            transition: background-color 0.3s ease;  
            margin: 0 auto;  
            display: flex;  
            align-items: center;  
            justify-content: center;  
        }  

        .upload-button:hover {  
            background-color: #2980b9;  
        }  

        .upload-button:active {  
            background-color: #2473a6;  
        }  

        .upload-button:disabled {  
            background-color: #7f8c8d;  
            cursor: not-allowed;  
        }  

        .button-loading,  
        .button-text {  
            display: flex;  
            align-items: center;  
            gap: 8px;  
        }  

        .d-none {  
            display: none !important;  
        }  

        .mdi-spin {  
            animation: spin 1s infinite linear;  
        }  

        @keyframes spin {  
            from {  
                transform: rotate(0deg);  
            }  
            to {  
                transform: rotate(360deg);  
            }  
        }  

        body.dark-mode .drop-zone {  
            background-color: #2d3436;  
            border-color: #3498db;  
            color: #ddd;  
        }  

        body.dark-mode .drop-zone:hover {  
            background-color: #2d3436;  
        }  

        body.dark-mode .drop-zone.drop-zone--over {  
            background-color: #34495e;  
        }  

        .upload-container {  
            width: 100%;  
            max-width: 500px;  
            margin: 0 auto;  
            display: flex;  
            flex-direction: column;  
            align-items: center;  
        }  

        .content-container {  
            padding: 20px;  
            background-color: #f0f8ff;
            min-height: calc(100vh - 60px);
        }  

        .white-container {  
            background-color: #ffffff;  
            border-radius: 8px;  
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);  
            padding: 25px;  
            margin-bottom: 20px;  
        }  

        .white-container h2 {  
            color: #333;  
            font-size: 24px;  
            margin-bottom: 20px;  
            font-weight: 500;  
        }  

        .white-container hr {  
            border-top: 1px solid #e0e0e0;  
            margin: 20px 0;  
        }  

        .form-content {  
            max-width: 800px;  
            margin: 0 auto;  
        }  

        body.dark-mode .content-container {  
            background-color: #1a1a1a;  
        }  

        body.dark-mode .white-container {  
            background-color: #2d2d2d;  
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);  
        }  

        body.dark-mode .white-container h2 {  
            color: #ffffff;  
        }  

        body.dark-mode hr {  
            border-top-color: #404040;  
        }  

        .drop-zone {  
            max-width: 100%;  
            margin: 20px auto;  
        }  

        .upload-button {  
            max-width: 100%;  
            margin: 20px auto;  
        }  

        .notification-container {  
            max-width: 100%;  
            margin: 20px auto;  
        }
    </style>  
</head>  
<body class="light-mode">  
    <?php  
    include(__DIR__ . '/../menu.php');  
    ?>  

    <div id="main" class="main-content">  
            <div class="white-container">  
                <h2>Envio de Tabela de Emolumentos</h2>  
                <hr>  
                
                <div class="form-content">  
                    <form id="uploadForm" enctype="multipart/form-data">  
                        <div class="drop-zone">  
                            <span class="drop-zone__prompt">  
                                <i class="mdi mdi-cloud-upload" style="font-size: 2em; color: #3498db;"></i>  
                                <br>  
                                Arraste o arquivo ou clique para selecionar  
                            </span>  
                            <input type="file" name="file" class="drop-zone__input" id="file" accept=".txt" required>  
                        </div>  
                        <button type="button" class="upload-button" id="uploadButton" onclick="uploadFile()">  
                            <span class="button-text">Enviar Tabela</span>  
                            <span class="button-loading d-none">  
                                <i class="mdi mdi-loading mdi-spin"></i>  
                                Enviando...  
                            </span>  
                        </button>  
                    </form>  
                    <div id="message" class="notification-container mt-3"></div>  
                </div>  
            </div>  
    </div>  

    <script src="../script/jquery-3.5.1.min.js"></script>  
    <script src="../script/bootstrap.min.js"></script>  
    <script src="../script/jquery.mask.min.js"></script>  
    <script>  
        function uploadFile() {  
            const button = document.getElementById('uploadButton');  
            const buttonText = button.querySelector('.button-text');  
            const buttonLoading = button.querySelector('.button-loading');  
            const form = document.getElementById('uploadForm');  
            const fileInput = document.getElementById('file');  

            if (fileInput.files.length === 0) {  
                Swal.fire({  
                    icon: 'warning',  
                    title: 'Atenção',  
                    text: 'Por favor, selecione um arquivo.'  
                });  
                return;  
            }  

            button.disabled = true;  
            buttonText.classList.add('d-none');  
            buttonLoading.classList.remove('d-none');  

            const formData = new FormData(form);  

            fetch('process_upload.php', {  
                method: 'POST',  
                body: formData  
            })  
            .then(response => response.json())  
            .then(data => {  
                if (data.status === 'ignored') {  
                    let ignoredList = data.ignoredAtos.map(ato => `ATO '${ato}' já existe e será ignorado.`).join('<br>');  
                    Swal.fire({  
                        icon: 'info',  
                        title: 'Atenção',  
                        html: ignoredList  
                    });  
                } else if (data.status === 'success') {  
                    Swal.fire({  
                        icon: 'success',  
                        title: 'Sucesso',  
                        text: `${data.message} ${data.insertedCount} atos foram inseridos com sucesso.`  
                    });  
                } else {  
                    Swal.fire({  
                        icon: 'error',  
                        title: 'Erro',  
                        text: data.message  
                    });  
                }  
            })  
            .catch(error => {  
                Swal.fire({  
                    icon: 'error',  
                    title: 'Erro',  
                    text: 'Erro ao fazer upload do arquivo: ' + error.message  
                });  
            })  
            .finally(() => {  
                button.disabled = false;  
                buttonText.classList.remove('d-none');  
                buttonLoading.classList.add('d-none');  
            });  
        }  

        document.querySelectorAll(".drop-zone__input").forEach((inputElement) => {  
            const dropZoneElement = inputElement.closest(".drop-zone");  

            dropZoneElement.addEventListener("click", (e) => {  
                inputElement.click();  
            });  

            inputElement.addEventListener("change", (e) => {  
                if (inputElement.files.length) {  
                    updateThumbnail(dropZoneElement, inputElement.files[0]);  
                }  
            });  

            dropZoneElement.addEventListener("dragover", (e) => {  
                e.preventDefault();  
                dropZoneElement.classList.add("drop-zone--over");  
            });  

            ["dragleave", "dragend"].forEach((type) => {  
                dropZoneElement.addEventListener(type, (e) => {  
                    dropZoneElement.classList.remove("drop-zone--over");  
                });  
            });  

            dropZoneElement.addEventListener("drop", (e) => {  
                e.preventDefault();  

                if (e.dataTransfer.files.length) {  
                    inputElement.files = e.dataTransfer.files;  
                    updateThumbnail(dropZoneElement, e.dataTransfer.files[0]);  
                }  

                dropZoneElement.classList.remove("drop-zone--over");  
            });  
        });  

        function updateThumbnail(dropZoneElement, file) {  
            let thumbnailElement = dropZoneElement.querySelector(".drop-zone__thumb");  

            if (dropZoneElement.querySelector(".drop-zone__prompt")) {  
                dropZoneElement.querySelector(".drop-zone__prompt").remove();  
            }  

            if (!thumbnailElement) {  
                thumbnailElement = document.createElement("div");  
                thumbnailElement.classList.add("drop-zone__thumb");  
                dropZoneElement.appendChild(thumbnailElement);  
            }  

            thumbnailElement.dataset.label = file.name;  
        }  
    </script>  
    <?php  
    include(__DIR__ . '/../rodape.php');  
    ?>  
</body>  
</html>