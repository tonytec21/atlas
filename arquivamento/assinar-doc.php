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
    <script>
        const commands = [
            {"command": "sign","type": "text", "inputData": "teste"},
            {"command": "sign","type": "text", "inputData": "teste", "signaturePolicy":"RT"},
            {"command": "sign","type": "text", "inputData": "teste", "textEncoding": "ISO-8859-1"},
            {"command": "sign","type": "text", "inputData": "teste","attached": "true"},
            {"command": "sign","type": "hash", "inputData": " hash em base64 "},
            {"command": "sign","type": "base64", "inputData": "conteúdo em base64"},
            {"command": "sign","type": "PDF", "inputData": "arquivo PDF em base64"},
            {"command": "verify","type": "text", "inputData": "texto assinado", "inputSignature":" Assinatura em base64"},
            {"command": "verify","type": "base64", "inputData": " Contéudo em base64", "inputSignature":" Assinatura em base64"},
            {"command": "verify","type": "base64", "inputSignature":" Assinatura em base64"},
            {"command": "verify","type": "hash", "inputData": "hash em base64", "algorithmOIDHash": " oid do algoritmo"},
            {"command": "verify","type": "pdf", "inputData": "arquivo PDF em base64"},
            {"command": "TimeStamp","inputContent":"Contéudo em base64", "type":"raw"},
            {"command": "TimeStamp","inputContent":"Contéudo em base64", "type":"Signature"},
            {"command": "TimeStamp","inputContent":"Contéudo em base64", "type":"raw"},
            {"command": "attached","inputSignature":"Assinatura em base64"},
            {"command": "cosign", "type": "hash","inputData": "hash em base64", "signatureToCoSign":"Assinatura em base64"},
            {"command": "cosign", "type": "base64","inputData": "Contéudo em base64", "signatureToCoSign":"Assinatura em base64"},
            {"command": "cosign", "type": "base64","inputData": "Contéudo em base64", "signatureToCoSign":"Assinatura em base64","signaturePolicy":"RT"}
        ];

        var conn = new WebSocket("wss://127.0.0.1:65156/signer");
        conn.onmessage = function(e) { 
            const result = JSON.parse(e.data);
            console.log('#', result);
            if (result.error) {  
            prettyResult(result.error);
            } else {
            prettyResult(result);
            }
        }

        function sendData(value){
            console.log('command', value);
            conn.send(value);
        }

        function exec(index){
            cmd = commands[index];
            sendData(JSON.stringify(cmd));
        }

        function customExec(){
            cmd = $('#custom-websocket').val();
            sendData(cmd);
        }

    </script>

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
        .label-default[href]:focus,.label-default[href]:hover{
            background-color:#5e5e5e;
        }
        .label-primary{
            background-color:#337ab7;
        }
        .label-primary[href]:focus,.label-primary[href]:hover{
            background-color:#286090;
        }
        .label-success{
            background-color:#5cb85c;
        }
        .label-success[href]:focus,.label-success[href]:hover{
            background-color:#449d44;
        }
        .label-info{
            background-color:#5bc0de;
        }
        .label-info[href]:focus,.label-info[href]:hover{
            background-color:#31b0d5;
        }
        .label-warning{
            background-color:#f0ad4e;
        }
        .label-warning[href]:focus,.label-warning[href]:hover{
            background-color:#ec971f;
        }
        .label-danger{
            background-color:#d9534f;
        }
        .label-danger[href]:focus,.label-danger[href]:hover{
            background-color:#c9302c;
        }
        .label{
            display:inline;padding:.2em .6em .3em;
            font-size:75%;
            font-weight:700;
            line-height:1;
            color:#fff;
            text-align:center;
            white-space:nowrap;
            vertical-align:baseline;
            border-radius:.25em;
        }
        a.label:focus,a.label:hover{
            color:#fff;
            text-decoration:none;
            cursor:pointer;
        }
        .label:empty{
            display:none;
        }
        .btn .label{
            position:relative;
            top:-1px;
        }
        .label-default{
            background-color:#777;
        }
    </style>
</head>
<body class="light-mode">
<?php
include(__DIR__ . '/../menu.php');
?>

    <div class="container">
    <div class="px-2 font-weight-bold">WebSocket Server está:</div>
      <div class="label label-success badge-pill js-server-status js-server-status-on">ONLINE</div>
      <div class="label label-danger badge-pill js-server-status js-server-status-off">OFFLINE</div>
      <p><a class="js-server-authorization" href="http://127.0.0.1:65056/" target="_blank">Favor autorizar o assinador!</a></p>
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

<?php
include(__DIR__ . '/../rodape.php');
?>

</body>
</html>
