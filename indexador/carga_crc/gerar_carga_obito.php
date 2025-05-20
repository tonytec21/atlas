<?php  
require_once __DIR__ . '/db_connection.php';  

date_default_timezone_set('America/Sao_Paulo');  

$queryCNS = "SELECT cns FROM cadastro_serventia LIMIT 1";  
$resultCNS = $conn->query($queryCNS);  
$rowCNS = $resultCNS->fetch_assoc();  
$cns = $rowCNS['cns'] ?? '000000';  

function valorOuPadrao($valor, $padrao = "NAO DECLARADO") {  
    return !empty($valor) ? htmlspecialchars($valor) : $padrao;  
}  

function formatarData($data) {  
    // Verifica se a data é válida e não é a data zero  
    if (empty($data) || $data == '0000-00-00' || strtotime($data) <= 0) {  
        return '00/00/0000';  
    }  
    return date('d/m/Y', strtotime($data));  
}  

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ids'])) {  
    $ids = implode(",", array_map('intval', $_POST['selected_ids']));  
    $query = "SELECT * FROM indexador_obito WHERE id IN ($ids) AND status = 'A'";  
    $result = $conn->query($query);  

    if ($result && $result->num_rows > 0) {  
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><CARGAREGISTROS></CARGAREGISTROS>');  
        $xml->addChild('VERSAO', '2.7');  
        $xml->addChild('ACAO', 'CARGA');  
        $xml->addChild('CNS', $cns);  
        $movimento = $xml->addChild('MOVIMENTOOBITOTO');  

        while ($row = $result->fetch_assoc()) {  
            $registro = $movimento->addChild('REGISTROOBITOINCLUSAO');  

            $registro->addChild('INDICEREGISTRO', $row['id']);  
            $registro->addChild('FLAGDESCONHECIDO', 'N');  
            $registro->addChild('NOMEFALECIDO', valorOuPadrao($row['nome_registrado']));  
            $registro->addChild('CPFFALECIDO', '');  
            $registro->addChild('MATRICULA', valorOuPadrao($row['matricula']));  
            $registro->addChild('DATAREGISTRO', formatarData($row['data_registro']));  
            $registro->addChild('NOMEPAI', valorOuPadrao($row['nome_pai'], 'IGNORADO'));  
            $registro->addChild('CPFPAI', '');  
            $registro->addChild('SEXOPAI', 'M');  
            $registro->addChild('NOMEMAE', valorOuPadrao($row['nome_mae'], 'IGNORADA'));  
            $registro->addChild('CPFMAE', '');  
            $registro->addChild('SEXOMAE', 'F');  
            $registro->addChild('DATAOBITO', formatarData($row['data_obito']));  
            $registro->addChild('HORAOBITO', substr(valorOuPadrao($row['hora_obito'], '00:00'), 0, 5));  
            $registro->addChild('SEXO', 'I');  
            $registro->addChild('CORPELE', 'IGNORADA');  
            $registro->addChild('ESTADOCIVIL', 'IGNORADO');  
            $registro->addChild('DATANASCIMENTOFALECIDO', formatarData($row['data_nascimento']));  

            try {  
                // Verificar se as datas são válidas antes de calcular a idade  
                if (!empty($row['data_nascimento']) && $row['data_nascimento'] != '0000-00-00' &&   
                    !empty($row['data_obito']) && $row['data_obito'] != '0000-00-00') {  
                    
                    $dataNascimento = new DateTime($row['data_nascimento']);  
                    $dataObito = new DateTime($row['data_obito']);  
                    
                    // Verificar se as datas são válidas  
                    if ($dataNascimento->format('Y') > 1900 && $dataObito->format('Y') > 1900) {  
                        $intervalo = $dataNascimento->diff($dataObito);  

                        if ($intervalo->y >= 1) {  
                            $registro->addChild('IDADE', $intervalo->y);  
                            $registro->addChild('IDADE_DIAS_MESES_ANOS', 'A');  
                        } elseif ($intervalo->m >= 1) {  
                            $registro->addChild('IDADE', $intervalo->m);  
                            $registro->addChild('IDADE_DIAS_MESES_ANOS', 'M');  
                        } else {  
                            $dias = max($intervalo->d, 1);  
                            $registro->addChild('IDADE', $dias);  
                            $registro->addChild('IDADE_DIAS_MESES_ANOS', 'D');  
                        }  
                    } else {  
                        throw new Exception("Data inválida");  
                    }  
                } else {  
                    throw new Exception("Data não preenchida");  
                }  
            } catch (Exception $e) {  
                $registro->addChild('IDADE', '0');  
                $registro->addChild('IDADE_DIAS_MESES_ANOS', ''); // Trocado 'I' por '' (vazio)  
            }  

            $registro->addChild('ELEITOR', 'I');  
            $registro->addChild('POSSUIBENS', 'I');  
            $registro->addChild('CODIGOOCUPACAOSDC', '');  
            $registro->addChild('PAISNASCIMENTO', '076');  
            $registro->addChild('NACIONALIDADE', '076');  
            $registro->addChild('CODIGOIBGEMUNNATURALIDADE', '');  
            $registro->addChild('TEXTOLIVREMUNICIPIONAT', 'NAO DECLARADO');  
            
            // Deixando vazio em vez de usar "NAO DECLARADO"  
            $codigoIbge = !empty($row['ibge_cidade_endereco']) ? $row['ibge_cidade_endereco'] : '';  
            $registro->addChild('CODIGOIBGEMUNLOGRADOURO', $codigoIbge);  
            
            $registro->addChild('DOMICILIOESTRANGEIROFALECIDO', '');  
            $registro->addChild('LOGRADOURO', '');  
            $registro->addChild('NUMEROLOGRADOURO', '');  
            $registro->addChild('COMPLEMENTOLOGRADOURO', '');  
            $registro->addChild('BAIRRO', '');  

            $beneficio = $registro->addChild('BENEFICIOS_PREVIDENCIARIOS');  
            $beneficio->addChild('INDICEREGISTRO', $row['id']);  
            $beneficio->addChild('NUMEROBENEFICIO', '');  

            $documento = $registro->addChild('DOCUMENTOS');  
            $documento->addChild('INDICEREGISTRO', $row['id']);  
            $documento->addChild('DONO', 'FALECIDO');  
            $documento->addChild('TIPO_DOC', '');  
            $documento->addChild('DESCRICAO', '');  
            $documento->addChild('NUMERO', '');  
            $documento->addChild('NUMERO_SERIE', '');  
            $documento->addChild('CODIGOORGAOEMISSOR', '');  
            $documento->addChild('UF_EMISSAO', '');  
            $documento->addChild('DATA_EMISSAO', '');  

            $registro->addChild('TIPOLOCALOBITO', 'IGNORADO');  
            $registro->addChild('TIPOMORTE', 'IGNORADA');  
            $registro->addChild('NUMDECLARACAOOBITO', '');  
            $registro->addChild('NUMDECLARACAOOBITOIGNORADA', 'S');  
            $registro->addChild('PAISOBITO', '076');  
            
            // Deixando vazio em vez de usar "NAO DECLARADO"  
            $codigoIbgeObito = !empty($row['ibge_cidade_obito']) ? $row['ibge_cidade_obito'] : '';  
            $registro->addChild('CODIGOIBGEMUNLOGRADOUROOBITO', $codigoIbgeObito);  
            
            $registro->addChild('ENDERECOLOCALOBITOESTRANGEIRO', '');  
            $registro->addChild('LOGRADOUROOBITO', '');  
            $registro->addChild('NUMEROLOGRADOUROOBITO', '');  
            $registro->addChild('COMPLEMENTOLOGRADOUROOBITO', '');  
            $registro->addChild('BAIRROOBITO', '');  

            $registro->addChild('CAUSAMORTEANTECEDENTES_A', '');  
            $registro->addChild('CAUSAMORTEANTECEDENTES_B', '');  
            $registro->addChild('CAUSAMORTEANTECEDENTES_C', '');  
            $registro->addChild('CAUSAMORTEANTECEDENTES_D', '');  
            $registro->addChild('CAUSAMORTEOUTRASCOND_A', '');  
            $registro->addChild('CAUSAMORTEOUTRASCOND_B', '');  

            $registro->addChild('LUGARFALECIMENTO', '');  
            $registro->addChild('LUGARSEPULTAMENTOCEMITERIO', '');  

            $registro->addChild('NOMEATESTANTEPRIMARIO', 'NAO DECLARADO');  
            $registro->addChild('CRMATESTANTEPRIMARIO', 'NAO DECLARADO');  
            $registro->addChild('NOMEATESTANTESECUNDARIO', '');  
            $registro->addChild('CRMATESTANTESECUNDARIO', '');  
            $registro->addChild('NOMEDECLARANTE', 'NAO DECLARADO');  
            $registro->addChild('CPFDECLARANTE', '');  
            $registro->addChild('ORGAOEMISSOREXTERIOR', '');  
            $registro->addChild('INFORMACOESCONSULADO', '');  
            $registro->addChild('OBSERVACOES', '');  
        }  

        $dom = new DOMDocument('1.0', 'UTF-8');  
        $dom->preserveWhiteSpace = false;  
        $dom->formatOutput = true;  
        $dom->loadXML($xml->asXML());  

        $arquivo = 'carga_obito.xml';  
        $dom->save($arquivo);  

        header('Content-Type: application/xml');  
        header("Content-Disposition: attachment; filename=$arquivo");  
        readfile($arquivo);  
        unlink($arquivo);  
        exit;  
    } else {  
        echo "<script>alert('Nenhum registro válido encontrado.'); history.back();</script>";  
    }  
} else {  
    echo "<script>alert('Nenhum registro selecionado.'); history.back();</script>";  
}  

$conn->close();