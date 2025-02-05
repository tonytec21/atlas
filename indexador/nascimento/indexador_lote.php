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
    <title>Atlas - Indexador em Lote</title>
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../style/css/style.css">
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../../style/css/dataTables.bootstrap4.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="../../script/jquery-3.6.0.min.js"></script>
    <script src="../../script/jquery.dataTables.min.js"></script>
    <script src="../../script/dataTables.bootstrap4.min.js"></script>
    <script src="../../script/bootstrap.bundle.min.js"></script>
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
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-spinner {
            position: relative;
            margin-top: 20%;
            margin-left: 50%;
            width: 50px;
            height: 50px;
            border: 5px solid var(--bg-primary);
            border-top: 5px solid var(--accent-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-spinner span {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 14px;
            font-weight: bold;
            color: var(--text-primary); /* Ajuste conforme necessário */
            animation: none; /* Garante que a contagem não gire */
        }


        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<?php include(__DIR__ . '/../../menu.php'); ?>
<div id="main" class="main-content">
    <div class="container">
        <div class="page-header">
            <h3>Indexador em Lote</h3>
        </div>

        <form id="formProcessar" method="post" enctype="multipart/form-data">
            <div class="mb-4">
                <label for="arquivos" class="form-label">Selecione os arquivos PDF</label>
                <input type="file" id="arquivos" name="arquivos[]" class="form-control" multiple accept="application/pdf">
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-upload"></i> Processar Arquivos
            </button>
        </form>

        <div class="loading-overlay">
            <div class="loading-spinner">
                <span>0%</span>
            </div>
        </div>

    </div>
</div>

<script>
$(document).ready(function() {
    $('#formProcessar').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        $('.loading-overlay').show();

        // Criar um novo XMLHttpRequest para monitorar o progresso
        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                $('.loading-overlay .loading-spinner').html(`<span>${percentComplete}%</span>`);
            }
        });

        xhr.addEventListener('load', function() {
            $('.loading-overlay').hide();

            try {
                const result = JSON.parse(xhr.responseText);

                if (result.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso',
                        text: result.message,
                        confirmButtonColor: '#28a745'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: result.message,
                        confirmButtonColor: '#dc3545'
                    });
                }
            } catch (e) {
                console.error(e);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: 'Resposta inválida do servidor.',
                    confirmButtonColor: '#dc3545'
                });
            }
        });

        xhr.addEventListener('error', function() {
            $('.loading-overlay').hide();
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao processar os arquivos.',
                confirmButtonColor: '#dc3545'
            });
        });

        xhr.open('POST', 'processar_lote.php');
        xhr.send(formData);
    });
});
</script>
<?php include(__DIR__ . '/../../rodape.php'); ?>
</body>
</html>
