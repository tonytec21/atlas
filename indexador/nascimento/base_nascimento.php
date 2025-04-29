<?php
include(__DIR__ . '/session_check.php');
checkSession();
include(__DIR__ . '/db_connection.php');
date_default_timezone_set('America/Sao_Paulo');

/**
 * Funções auxiliares para Validação / Reindentação / Mapeamento
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
 * Reformatar (indent) o XML para termos número de linha mais preciso
 * durante a validação. Retorna o caminho de um arquivo temporário.
 */
function reformatXml($sourcePath) {
    $dom = new DOMDocument();
    $dom->load($sourcePath, LIBXML_NOBLANKS);
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;

    $tempPath = tempnam(sys_get_temp_dir(), 'xmlreindent_');
    $dom->save($tempPath);

    return $tempPath;
}

/**
 * Lê um arquivo XML (já reformatado) linha a linha
 * e mapeia onde aparecem tags <NOMEREGISTRADO>...</NOMEREGISTRADO>.
 */
function mapearNomesRegistrados($arquivoXml) {
    $linhas = file($arquivoXml);
    $mapeamento = [];
    foreach ($linhas as $indice => $conteudo) {
        if (preg_match('/<NOMEREGISTRADO>(.*?)<\/NOMEREGISTRADO>/', $conteudo, $matches)) {
            $nome = trim($matches[1]);
            $mapeamento[$indice + 1] = $nome;
        }
    }
    return $mapeamento;
}

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

/**
 * -> AQUI a função que localiza <MOVIMENTONASCIMENTOTN> em qualquer formato.
 * Se a raiz for <CARGAREGISTROS>, retorna $xml->MOVIMENTONASCIMENTOTN
 * Se a raiz for <MOVIMENTONASCIMENTOTN>, retorna array com o $xml.
 */
function getListaMovimentosNascimentos(SimpleXMLElement $xml) {
    $rootName = $xml->getName();

    if ($rootName === 'CARGAREGISTROS') {
        // Pode existir 1 ou mais <MOVIMENTONASCIMENTOTN>
        return $xml->MOVIMENTONASCIMENTOTN; // é um "array" SimpleXMLElement
    }
    if ($rootName === 'MOVIMENTONASCIMENTOTN') {
        // Retorna um array com 1 item
        return [$xml];
    }

    // Se não reconhecer, retorna array vazio
    return [];
}

/**
 * Função que, após o XML ser validado, faz a LEITURA e insere os dados
 * na tabela tb_xml_nascimento (não normalizada).
 * Ajuste nomes e quantidades de colunas conforme sua estrutura real.
 */
function inserirDadosNoBanco($xmlFile, $conn) {
    // Carrega o XML
    $xml = simplexml_load_file($xmlFile);
    if (!$xml) {
        echo "<p>Falha ao carregar o XML para importação.</p>";
        return;
    }

    // Prepara a query de INSERT (75 colunas no exemplo)
    $sql = "INSERT INTO tb_xml_nascimento (
      INDICEREGISTRO,
      NOMEREGISTRADO,
      CPFREGISTRADO,
      MATRICULA,
      DATAREGISTRO,
      DNV,
      DATANASCIMENTO,
      HORANASCIMENTO,
      LOCALNASCIMENTO,
      SEXO,
      POSSUIGEMEOS,
      NUMEROGEMEOS,
      CODIGOIBGEMUNNASCIMENTO,
      PAISNASCIMENTO,
      NACIONALIDADE,
      TEXTONACIONALIDADEESTRANGEIRO,
      ORGAOEMISSOREXTERIOR,
      INFORMACOESCONSULADO,
      OBSERVACOES,

      FILIACAO1_INDICEREGISTRO,
      FILIACAO1_INDICEFILIACAO,
      FILIACAO1_NOME,
      FILIACAO1_SEXO,
      FILIACAO1_CPF,
      FILIACAO1_DATANASCIMENTO,
      FILIACAO1_IDADE,
      FILIACAO1_IDADE_DIAS_MESES_ANOS,
      FILIACAO1_CODIGOIBGEMUNLOGRADOURO,
      FILIACAO1_LOGRADOURO,
      FILIACAO1_NUMEROLOGRADOURO,
      FILIACAO1_COMPLEMENTOLOGRADOURO,
      FILIACAO1_BAIRRO,
      FILIACAO1_NACIONALIDADE,
      FILIACAO1_DOMICILIOESTRANGEIRO,
      FILIACAO1_CODIGOIBGEMUNNATURALIDADE,
      FILIACAO1_TEXTOLIVREMUNICIPIONAT,
      FILIACAO1_CODIGOOCUPACAOSDC,

      FILIACAO2_INDICEREGISTRO,
      FILIACAO2_INDICEFILIACAO,
      FILIACAO2_NOME,
      FILIACAO2_SEXO,
      FILIACAO2_CPF,
      FILIACAO2_DATANASCIMENTO,
      FILIACAO2_IDADE,
      FILIACAO2_IDADE_DIAS_MESES_ANOS,
      FILIACAO2_CODIGOIBGEMUNLOGRADOURO,
      FILIACAO2_LOGRADOURO,
      FILIACAO2_NUMEROLOGRADOURO,
      FILIACAO2_COMPLEMENTOLOGRADOURO,
      FILIACAO2_BAIRRO,
      FILIACAO2_NACIONALIDADE,
      FILIACAO2_DOMICILIOESTRANGEIRO,
      FILIACAO2_CODIGOIBGEMUNNATURALIDADE,
      FILIACAO2_TEXTOLIVREMUNICIPIONAT,
      FILIACAO2_CODIGOOCUPACAOSDC,

      DOCUMENTO1_INDICEREGISTRO,
      DOCUMENTO1_INDICEFILIACAO,
      DOCUMENTO1_DONO,
      DOCUMENTO1_TIPO_DOC,
      DOCUMENTO1_DESCRICAO,
      DOCUMENTO1_NUMERO,
      DOCUMENTO1_NUMERO_SERIE,
      DOCUMENTO1_CODIGOORGAOEMISSOR,
      DOCUMENTO1_UF_EMISSAO,
      DOCUMENTO1_DATA_EMISSAO,

      DOCUMENTO2_INDICEREGISTRO,
      DOCUMENTO2_INDICEFILIACAO,
      DOCUMENTO2_DONO,
      DOCUMENTO2_TIPO_DOC,
      DOCUMENTO2_DESCRICAO,
      DOCUMENTO2_NUMERO,
      DOCUMENTO2_NUMERO_SERIE,
      DOCUMENTO2_CODIGOORGAOEMISSOR,
      DOCUMENTO2_UF_EMISSAO,
      DOCUMENTO2_DATA_EMISSAO
    ) VALUES (".str_repeat('?,', 75);
    $sql = rtrim($sql, ',').")";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "<p>Falha ao preparar statement: ".$conn->error."</p>";
        return;
    }
    $types = str_repeat('s', 75);

    function bind_array_params(mysqli_stmt $stmt, $types, array &$params) {
        $bind_names[] = $types;
        foreach ($params as $key => $value) {
            $bind_names[] = &$params[$key]; 
        }
        return call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }

    $inseridos = 0;

    // *** AQUI: pegamos todos os <MOVIMENTONASCIMENTOTN> via nossa função ***
    $listaMovimentos = getListaMovimentosNascimentos($xml);
    foreach ($listaMovimentos as $mov) {
        // Dentro de cada <MOVIMENTONASCIMENTOTN>, pode ter 1..N <REGISTRONASCIMENTOINCLUSAO>
        foreach ($mov->REGISTRONASCIMENTOINCLUSAO as $regInclusao) {
            // Campos principais
            $dataArr = [
                trim((string)$regInclusao->INDICEREGISTRO),
                trim((string)$regInclusao->NOMEREGISTRADO),
                trim((string)$regInclusao->CPFREGISTRADO),
                trim((string)$regInclusao->MATRICULA),
                trim((string)$regInclusao->DATAREGISTRO),
                trim((string)$regInclusao->DNV),
                trim((string)$regInclusao->DATANASCIMENTO),
                trim((string)$regInclusao->HORANASCIMENTO),
                trim((string)$regInclusao->LOCALNASCIMENTO),
                trim((string)$regInclusao->SEXO),
                trim((string)$regInclusao->POSSUIGEMEOS),
                trim((string)$regInclusao->NUMEROGEMEOS),
                trim((string)$regInclusao->CODIGOIBGEMUNNASCIMENTO),
                trim((string)$regInclusao->PAISNASCIMENTO),
                trim((string)$regInclusao->NACIONALIDADE),
                trim((string)$regInclusao->TEXTONACIONALIDADEESTRANGEIRO),
                trim((string)$regInclusao->ORGAOEMISSOREXTERIOR),
                trim((string)$regInclusao->INFORMACOESCONSULADO),
                trim((string)$regInclusao->OBSERVACOES),
            ];

            // FILIACAO (até 2)
            $filiacoes = $regInclusao->FILIACAONASCIMENTO;
            $f1 = array_fill(0,18,'');
            $f2 = array_fill(0,18,'');
            if (isset($filiacoes[0])) {
                $f = $filiacoes[0];
                $f1 = [
                    trim((string)$f->INDICEREGISTRO),
                    trim((string)$f->INDICEFILIACAO),
                    trim((string)$f->NOME),
                    trim((string)$f->SEXO),
                    trim((string)$f->CPF),
                    trim((string)$f->DATANASCIMENTO),
                    trim((string)$f->IDADE),
                    trim((string)$f->IDADE_DIAS_MESES_ANOS),
                    trim((string)$f->CODIGOIBGEMUNLOGRADOURO),
                    trim((string)$f->LOGRADOURO),
                    trim((string)$f->NUMEROLOGRADOURO),
                    trim((string)$f->COMPLEMENTOLOGRADOURO),
                    trim((string)$f->BAIRRO),
                    trim((string)$f->NACIONALIDADE),
                    trim((string)$f->DOMICILIOESTRANGEIRO),
                    trim((string)$f->CODIGOIBGEMUNNATURALIDADE),
                    trim((string)$f->TEXTOLIVREMUNICIPIONAT),
                    trim((string)$f->CODIGOOCUPACAOSDC),
                ];
            }
            if (isset($filiacoes[1])) {
                $f = $filiacoes[1];
                $f2 = [
                    trim((string)$f->INDICEREGISTRO),
                    trim((string)$f->INDICEFILIACAO),
                    trim((string)$f->NOME),
                    trim((string)$f->SEXO),
                    trim((string)$f->CPF),
                    trim((string)$f->DATANASCIMENTO),
                    trim((string)$f->IDADE),
                    trim((string)$f->IDADE_DIAS_MESES_ANOS),
                    trim((string)$f->CODIGOIBGEMUNLOGRADOURO),
                    trim((string)$f->LOGRADOURO),
                    trim((string)$f->NUMEROLOGRADOURO),
                    trim((string)$f->COMPLEMENTOLOGRADOURO),
                    trim((string)$f->BAIRRO),
                    trim((string)$f->NACIONALIDADE),
                    trim((string)$f->DOMICILIOESTRANGEIRO),
                    trim((string)$f->CODIGOIBGEMUNNATURALIDADE),
                    trim((string)$f->TEXTOLIVREMUNICIPIONAT),
                    trim((string)$f->CODIGOOCUPACAOSDC),
                ];
            }

            // DOCUMENTOS (até 2) - se existirem
            $docs = $regInclusao->DOCUMENTOS;
            $d1 = array_fill(0,10,'');
            $d2 = array_fill(0,10,'');
            if (isset($docs[0])) {
                $d = $docs[0];
                $d1 = [
                    trim((string)$d->INDICEREGISTRO),
                    trim((string)$d->INDICEFILIACAO),
                    trim((string)$d->DONO),
                    trim((string)$d->TIPO_DOC),
                    trim((string)$d->DESCRICAO),
                    trim((string)$d->NUMERO),
                    trim((string)$d->NUMERO_SERIE),
                    trim((string)$d->CODIGOORGAOEMISSOR),
                    trim((string)$d->UF_EMISSAO),
                    trim((string)$d->DATA_EMISSAO),
                ];
            }
            if (isset($docs[1])) {
                $d = $docs[1];
                $d2 = [
                    trim((string)$d->INDICEREGISTRO),
                    trim((string)$d->INDICEFILIACAO),
                    trim((string)$d->DONO),
                    trim((string)$d->TIPO_DOC),
                    trim((string)$d->DESCRICAO),
                    trim((string)$d->NUMERO),
                    trim((string)$d->NUMERO_SERIE),
                    trim((string)$d->CODIGOORGAOEMISSOR),
                    trim((string)$d->UF_EMISSAO),
                    trim((string)$d->DATA_EMISSAO),
                ];
            }

            // Junta tudo (75 campos)
            $params = array_merge($dataArr, $f1, $f2, $d1, $d2);

            bind_array_params($stmt, $types, $params);
            if (!$stmt->execute()) {
                echo "<p>Falha ao inserir registro: ".$stmt->error."</p>";
            } else {
                $inseridos++;
            }
        } // Fim do foreach <REGISTRONASCIMENTOINCLUSAO>
    } // Fim do foreach <MOVIMENTONASCIMENTOTN>

    $stmt->close();
    echo "<p>Importação concluída. Registros inseridos: <strong>$inseridos</strong></p>";
}

// ======================== HTML e Validador ========================
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
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fa fa-check-circle"></i> Validar XML
                    </button>
                </div>
            </div>
        </form>
        <hr>

        <?php
        // ----------------- PROCESSO DE VALIDAÇÃO + INSERT -----------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_FILES['xmlFile'])) {
                $xmlFile = $_FILES['xmlFile']['tmp_name'];
                $xsdFile = __DIR__ . '/../validar_xml/catalogo-crc.xsd';

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
                    // Reformatar p/ ter linhas adequadas na validação
                    libxml_use_internal_errors(true);
                    $xmlReformatado = reformatXml($xmlFile);

                    $mapaNomes = mapearNomesRegistrados($xmlReformatado);
                    $dom = new DomDocument();

                    if ($dom->load($xmlReformatado)) {
                        // Tenta validar
                        if ($dom->schemaValidate($xsdFile)) {
                            echo "<script>
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Validação bem-sucedida!',
                                    text: 'O XML está de acordo com o XSD.',
                                    confirmButtonText: 'OK'
                                });
                            </script>";

                            // Se OK, faz o INSERT no banco
                            inserirDadosNoBanco($xmlFile, $conn);

                        } else {
                            $errors = libxml_get_errors();
                            libxml_clear_errors();

                            $mensagensTraduzidas = '';
                            foreach ($errors as $error) {
                                $linhaErro = $error->line;
                                $nomeRegistro = obterNomeRegistradoPorLinha($mapaNomes, $linhaErro);
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
                                    text: 'Erros encontrados no XML. Nenhum dado foi inserido.',
                                    footer: '<pre>' + `$mensagensTraduzidas` + '</pre>',
                                    confirmButtonText: 'OK'
                                });
                            </script>";
                        }
                        if (file_exists($xmlReformatado)) {
                            unlink($xmlReformatado);
                        }
                    } else {
                        echo "<script>
                            Swal.fire({
                                icon: 'error',
                                title: 'Erro ao carregar XML!',
                                text: 'Arquivo XML inválido ou corrompido. Nenhum dado foi inserido.',
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
