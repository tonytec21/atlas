<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

function traduzirMensagemErro($mensagem) {
    $traducoes = [
        "/Element '(.*?)':/" => "Elemento '$1':",
        "/\[facet 'enumeration'\]/" => "[restrição 'enumeration']", 
        "/is not an element of the set/" => "não é um elemento do conjunto",
        "/\[facet 'maxLength'\]/" => "[restrição 'comprimento máximo']", 
        "/The value has a length of '(.*?)';/" => "O valor tem um comprimento de '$1';",
        "/this exceeds the allowed maximum length of '(.*?)'\./" => "isso excede o comprimento máximo permitido de '$1'.",
        "/The value '(.*?)'/" => "O valor '$1'", 
    ];

    foreach ($traducoes as $regex => $traduzido) {
        $mensagem = preg_replace($regex, $traduzido, $mensagem);
    }

    return $mensagem;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Validação de XML</title>
    <link rel="stylesheet" href="../../style/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../style/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../style/css/style.css">
    <link rel="icon" href="../../style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="../../style/css/materialdesignicons.min.css">
    <script src="../../script/sweetalert2.js"></script>
    <script src="../../script/jquery-3.6.0.min.js"></script>
</head>
<body class="light-mode">
<?php include(__DIR__ . '/../../menu.php'); ?>

<div id="main" class="main-content">
    <div class="container">
        <h3>Validação de Arquivo XML</h3>
        <hr>
        <form action="" method="post" enctype="multipart/form-data" id="formValidacao">
            <div class="row mb-4">
                <div class="col-md-12">
                    <label for="xmlFile" class="form-label">Selecione o arquivo XML:</label>
                    <input type="file" id="xmlFile" name="xmlFile" class="form-control" accept=".xml" required>
                </div>
                <div class="col-md-12 text-center mt-4">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa fa-check-circle"></i> Validar XML</button>
                </div>
            </div>
        </form>
        <hr>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_FILES['xmlFile'])) {
                $xmlFile = $_FILES['xmlFile']['tmp_name'];
                $xsdFile = __DIR__ . '/catalogo-crc.xsd';

                if (!file_exists($xsdFile)) {
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: 'Arquivo XSD não encontrado no servidor.',
                            confirmButtonText: 'OK'
                        });
                    </script>";
                } else {
                    libxml_use_internal_errors(true);
                    $dom = new DomDocument();

                    if ($dom->load($xmlFile)) {
                        if ($dom->schemaValidate($xsdFile)) {
                            echo "<script>
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Validação bem-sucedida!',
                                    text: 'O XML está de acordo com o XSD.',
                                    confirmButtonText: 'OK'
                                });
                            </script>";
                        } else {
                            $errors = libxml_get_errors();
                            $mensagensTraduzidas = '';

                            foreach ($errors as $error) {
                                $mensagensTraduzidas .= traduzirMensagemErro($error->message) . "\\n";
                            }

                            libxml_clear_errors();

                            echo "<script>
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Erro de validação!',
                                    text: 'Erros encontrados no XML.',
                                    footer: '<pre>' + `$mensagensTraduzidas` + '</pre>',
                                    confirmButtonText: 'OK'
                                });
                            </script>";
                        }
                    } else {
                        echo "<script>
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro ao carregar XML!',
                                text: 'Arquivo XML inválido ou corrompido.',
                                confirmButtonText: 'OK'
                            });
                        </script>";
                    }
                }
            } else {
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: 'Por favor, envie um arquivo XML.',
                        confirmButtonText: 'OK'
                    });
                </script>";
            }
        }
        ?>
    </div>
</div>

<?php include(__DIR__ . '/../../rodape.php'); ?>
</body>
</html>
