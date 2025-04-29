<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/**
 * Traduz mensagens de erro do libxml para português.
 */
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

/**
 * Lê o XML de $sourcePath e salva reformatado (indentado) em um arquivo temporário.
 * Retorna o caminho do arquivo temporário.
 */
function reformatXml($sourcePath) {
    $dom = new DOMDocument();
    // Carrega o XML (caso o XML seja muito grande, avalie usar outras flags)
    $dom->load($sourcePath, LIBXML_NOBLANKS);

    // Remove espaços em branco e ativa a formatação
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;

    // Cria um arquivo temporário
    $tempPath = tempnam(sys_get_temp_dir(), 'xmlreindent_');
    // Salva o XML reformatado nesse arquivo
    $dom->save($tempPath);

    return $tempPath;
}

/**
 * Lê um arquivo XML (já reformatado) linha a linha
 * e mapeia onde aparecem tags <NOMEREGISTRADO>...</NOMEREGISTRADO>.
 * Retorna um array: [numero_da_linha => nome_registrado].
 */
function mapearNomesRegistrados($arquivoXml) {
    $linhas = file($arquivoXml); // Lê cada linha do arquivo em um array
    $mapeamento = [];

    foreach ($linhas as $indice => $conteudo) {
        // Se estiver algo como: <NOMEREGISTRADO>Fulano</NOMEREGISTRADO>
        // (sem namespaces, sem quebra de linha no meio etc.)
        if (preg_match('/<NOMEREGISTRADO>(.*?)<\/NOMEREGISTRADO>/', $conteudo, $matches)) {
            $nome = trim($matches[1]);
            // $indice começa em 0, mas a linha "real" é $indice+1
            $mapeamento[$indice + 1] = $nome;
        }
    }

    return $mapeamento;
}

/**
 * Dado um array [linha => nome], encontra o "nome" mais recente
 * para linha <= $linhaErro (ou string vazia se nada encontrado).
 */
function obterNomeRegistradoPorLinha($mapeamento, $linhaErro) {
    $ultimoNome = '';
    foreach ($mapeamento as $linha => $nome) {
        if ($linha <= $linhaErro) {
            $ultimoNome = $nome;
        } else {
            break;
        }
    }
    return $ultimoNome;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atlas - Validação de XML CRC</title>
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
        <h3>Validação de Arquivo XML - CRC</h3>
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

                // Verifica se o XSD existe
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
                    // 1) Reformatar e criar arquivo temporário indentado
                    $xmlReformatado = reformatXml($xmlFile);

                    // 2) Mapeia linhas do XML reformatado, procurando <NOMEREGISTRADO>
                    $mapaNomes = mapearNomesRegistrados($xmlReformatado);

                    libxml_use_internal_errors(true);
                    $dom = new DomDocument();

                    // 3) Carrega do arquivo temporário reformatado
                    if ($dom->load($xmlReformatado)) {
                        // 4) Valida
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
                            // 5) Tratar e exibir erros
                            $errors = libxml_get_errors();
                            libxml_clear_errors();

                            $mensagensTraduzidas = '';
                            foreach ($errors as $error) {
                                $linhaErro = $error->line;
                                $nomeRegistro = obterNomeRegistradoPorLinha($mapaNomes, $linhaErro);

                                // Traduz a mensagem de libxml
                                $mensagemTraduzida = traduzirMensagemErro($error->message);

                                if (!empty($nomeRegistro)) {
                                    $mensagensTraduzidas .= "Registro: \"$nomeRegistro\" - Linha $linhaErro => $mensagemTraduzida\n";
                                } else {
                                    $mensagensTraduzidas .= "Linha $linhaErro => $mensagemTraduzida\n";
                                }
                            }

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

                        // Remove o arquivo temporário depois de usar (opcional)
                        if (file_exists($xmlReformatado)) {
                            unlink($xmlReformatado);
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
