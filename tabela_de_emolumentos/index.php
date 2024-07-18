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

        /* Dark mode styles */
        body.dark-mode .timeline::before { background: #444; }
        body.dark-mode .timeline-item .timeline-panel { background: #333; border-color: #444; color: #ddd; }
        body.dark-mode .timeline-item .timeline-panel::before { border-left-color: #444; }
        body.dark-mode .timeline-item .timeline-panel::after { border-left-color: #333; }

        /* Center the form */
        .center-form {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 70vh;
        }

        /* Align button horizontally */
        .form-group-horizontal {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-group-horizontal .btn {
            margin-left: 10px;
        }

        /* Container for notifications */
        .notification-container {
            margin-top: 20px;
            overflow: hidden;
        }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

<div id="main" class="main-content">
    <div class="container center-form">
        <div class="w-50">
            <h3 style="text-align:center">Envie a Tabela de Emolumentos</h3>
            <hr>
            <form id="uploadForm" enctype="multipart/form-data" class="d-flex flex-column align-items-center">
                <div class="form-group form-group-horizontal">
                    <input type="file" class="form-control-file" name="file" id="file" accept=".txt" required>
                    <button type="button" class="btn btn-primary" onclick="uploadFile()">Enviar</button>
                </div>
            </form>
            <div id="message" class="notification-container mt-3"></div>
        </div>
    </div>
</div>

<script src="../script/jquery-3.5.1.min.js"></script>
<script src="../script/bootstrap.min.js"></script>
<script src="../script/jquery.mask.min.js"></script>
<script>
    $(document).ready(function() {
        // Carregar o modo do usuário
        $.ajax({
            url: '../load_mode.php',
            method: 'GET',
            success: function(mode) {
                $('body').removeClass('light-mode dark-mode').addClass(mode);
            }
        });

        // Função para alternar modos claro e escuro
        $('.mode-switch').on('click', function() {
            var body = $('body');
            body.toggleClass('dark-mode light-mode');

            var mode = body.hasClass('dark-mode') ? 'dark-mode' : 'light-mode';
            $.ajax({
                url: '../save_mode.php',
                method: 'POST',
                data: { mode: mode },
                success: function(response) {
                    console.log(response);
                }
            });
        });
    });

    function uploadFile() {
        const form = document.getElementById('uploadForm');
        const fileInput = document.getElementById('file');
        const messageDiv = document.getElementById('message');

        if (fileInput.files.length === 0) {
            messageDiv.innerHTML = '<div class="alert alert-warning">Por favor, selecione um arquivo.</div>';
            return;
        }

        const formData = new FormData(form);

        fetch('process_upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ignored') {
                let message = '<div class="alert alert-info">';
                data.ignoredAtos.forEach(ato => {
                    message += `ATO '${ato}' já existe e será ignorado.<br>`;
                });
                message += '</div>';
                messageDiv.innerHTML = message;
            } else if (data.status === 'success') {
                messageDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
            } else {
                messageDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
        })
        .catch(error => {
            messageDiv.innerHTML = '<div class="alert alert-danger">Erro ao fazer upload do arquivo: ' + error.message + '</div>';
        });
    }
</script>
<?php
include(__DIR__ . '/../rodape.php');
?>
</body>
</html>
