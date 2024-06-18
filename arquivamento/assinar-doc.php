<?php
include(__DIR__ . '/session_check.php');
checkSession();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title>Atlas - Assinador</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../style/css/style.css">
    <link rel="icon" href="../style/img/favicon.png" type="image/png">
    <script src="../script/jquery-3.5.1.min.js"></script>
    <script src="../script/bootstrap.min.js"></script>
    <script src="../script/jquery.mask.min.js"></script>
    <script src="../script/is.min.js" type="text/javascript"></script>
    <script src="../script/serpro-signer-promise.js" type="text/javascript"></script>
    <script src="../script/serpro-signer-client.js" type="text/javascript"></script>
    <script src="../script/jquery.autogrow-textarea.js" type="text/javascript"></script>

    <style>
        body.dark-mode {
            background-color: #333;
            color: #ccc;
        }
        body.light-mode {
            background-color: #fff;
            color: #000;
        }
        .mode-switch {
            cursor: pointer;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body class="light-mode">
    <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
        <button class="mode-switch">🔄 Modo</button>
        <a href="../index.php">Página Inicial</a>
        <a href="index.php">Acervo Cadastrado</a>
        <a href="cadastro.php">Cadastrar Acervo</a>
        <a href="categorias.php">Gerenciamento de Categorias</a>
    </div>

    <div id="main-content-wrapper">
        <button class="openbtn" onclick="openNav()">&#9776; Menu</button>
        <div id="system-name">Atlas</div>
        <div id="welcome-section">
            <div>
                <h2>Bem-vindo</h2>
                <p>Olá, <?php echo htmlspecialchars($_SESSION['username']); ?>. Você está logado.</p>
            </div>
            <a href="../logout.php" id="logout-button" class="btn btn-danger">Sair</a>
        </div>
    </div>

    <div class="container">
        <div class="row col-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Assinar PDF</h3>
                </div>
                <div class="panel-body">
                    <form id="assinarPdf">
                        <div class="form-group">
                            <label for="file_input">Escolher Arquivo PDF</label>
                            <input id="input-file" type="file" onchange="convertToBase64();" />
                        </div>
                        <div class="form-group hidden">
                            <label for="content-value">Conteúdo do PDF (Base 64)</label>
                            <textarea id="content-value" class="form-control" rows="5" disabled></textarea>
                        </div>
                        <div class="form-group row">
                            <div class="col-sm-2">
                                <button type="submit" class="btn btn-primary">Assinar PDF</button>
                            </div>
                        </div>
                        <div class="form-group hidden">
                            <label for="sign-websocket">Comando WebSocket</label>
                            <textarea id="sign-websocket" class="form-control" rows="7" disabled></textarea>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row col-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">PDF Assinado</h3>
                </div>
                <div class="panel-body">
                    <form>
                        <div class="form-group ">
                            <label for="assinatura">Arquivo Assinado (PDF + Assinatura em Base 64)</label>
                            <textarea id="assinatura" class="form-control" rows="5" disabled></textarea>
                        </div>
                        <div class="form-group">
                            <button id="validarAssinaturaPdf" type="button" class="btn btn-primary">Validar Assinatura</button>
                            <button type="button" class="btn btn-primary" id="download-button" onclick="downloadPdf();">Download PDF</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../script/serpro-client-connector.js" type="text/javascript"></script>
    <script>
        function prettyCommandSign() {
            $('#sign-websocket').val(JSON.stringify({
                command: "sign",
                type: "pdf",
                inputData: $('#content-value').val()
            }, null, 2));
        }

        prettyCommandSign();

        // BASE 64
        function convertToBase64() {
            var selectedFile = document.getElementById("input-file").files;
            if (selectedFile.length > 0) {
                var fileToLoad = selectedFile[0];
                var fileReader = new FileReader();
                var base64;
                fileReader.onload = function(fileLoadedEvent) {
                    base64 = fileLoadedEvent.target.result;
                    if (base64.indexOf('data:application/pdf;base64,') == 0) {
                        base64 = base64.substring('data:application/pdf;base64,'.length, base64.length);
                    } else {
                        alert('O cabeçalho PDF não foi encontrado. Esse é mesmo um arquivo PDF?');
                    }
                    document.getElementById("content-value").value = base64;
                    prettyCommandSign();
                };
                fileReader.readAsDataURL(fileToLoad);
            }
        }

        // Download PDF
        function downloadPdf() {
            const data = $('#assinatura').val();
            const linkSource = `data:application/pdf;base64,${data}`;
            const downloadLink = document.createElement("a");
            const fileName = $('#input-file').prop('files')[0].name;
            downloadLink.href = linkSource;
            downloadLink.download = fileName;
            downloadLink.click();
        }

        // Enviar PDF assinado para o servidor
        function sendSignedPdfToServer(base64Data, fileName) {
            $.ajax({
                url: 'save_signed_pdf.php',
                method: 'POST',
                data: {
                    pdfData: base64Data,
                    fileName: fileName
                },
                success: function(response) {
                    console.log('PDF assinado salvo no servidor.');
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Erro ao salvar o PDF no servidor:', textStatus, errorThrown);
                }
            });
        }

        // Assinar PDF
        $('#assinarPdf').on('submit', function(e) {
            e.preventDefault();
            var base64Content = $('#content-value').val();
            var signCommand = {
                command: "sign",
                type: "pdf",
                inputData: base64Content
            };
            window.SerproSignerClient.sign(JSON.stringify(signCommand))
                .then(function(response) {
                    $('#assinatura').val(response);
                    const fileName = $('#input-file').prop('files')[0].name;
                    downloadPdf();
                    $('#download-button').show();
                    sendSignedPdfToServer(response, fileName);
                })
                .catch(function(error) {
                    alert('Erro ao assinar o PDF.');
                });
        });

        $(document).ready(function() {
            // Função para alternar modos claro e escuro
            $('.mode-switch').on('click', function() {
                var body = $('body');
                body.toggleClass('dark-mode light-mode');
            });
        });

        function openNav() {
            document.getElementById("mySidebar").style.width = "250px";
            document.getElementById("main-content-wrapper").style.marginLeft = "250px";
        }

        function closeNav() {
            document.getElementById("mySidebar").style.width = "0";
            document.getElementById("main-content-wrapper").style.marginLeft = "0";
        }
    </script>
</body>
</html>
