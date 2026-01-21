<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

// Ativar manipulador de erros personalizado para capturar avisos  
set_error_handler(function($errno, $errstr, $errfile, $errline) {  
    global $errorMessages;  
    $errorMessages[] = "$errstr em $errfile:$errline";  
    return true;  
});  

// Garantir que a saída seja apenas o JSON no final  
ob_start();  

// Adicionar o PhpSpreadsheet via Composer  
require '../vendor/autoload.php';  

use PhpOffice\PhpSpreadsheet\IOFactory;  
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;  

$errorMessages = [];  
$result = [  
    'status' => 'error',  
    'message' => 'Nenhum arquivo foi processado.'  
];  

// Cache para consultas de cidades do IBGE (evitar múltiplas requisições para a mesma cidade)
$ibgeCidadeCache = [];

/**
 * Função para calcular os dígitos verificadores da matrícula
 * (Mesma lógica usada em salvar_registro.php)
 */
function calcularDigitoVerificador($matriculaBase) {
    $multiplicadorFase1 = 32;
    $soma = 0;

    for ($i = 0; $i < 30; $i++) {
        $multiplicadorFase1--;
        $soma += intval($matriculaBase[$i]) * $multiplicadorFase1;
    }

    $digito1 = ($soma * 10) % 11;
    $digito1 = ($digito1 == 10) ? 1 : $digito1;

    $multiplicadorFase2 = 33;
    $soma2 = 0;

    for ($j = 0; $j < 30; $j++) {
        $multiplicadorFase2--;
        $soma2 += intval($matriculaBase[$j]) * $multiplicadorFase2;
    }

    $soma2 += $digito1 * 2;
    $digito2 = ($soma2 * 10) % 11;
    $digito2 = ($digito2 == 10) ? 1 : $digito2;

    return $digito1 . $digito2;
}

/**
 * Função para gerar a matrícula completa
 * (Mesma lógica usada em salvar_registro.php)
 */
function gerarMatricula($conn, $livro, $folha, $termo, $data_registro) {
    $cnsQuery = "SELECT cns FROM cadastro_serventia LIMIT 1";
    $cnsResult = $conn->query($cnsQuery);

    if ($cnsResult && $cnsResult->num_rows > 0) {
        $cnsRow = $cnsResult->fetch_assoc();
        $cns = str_pad($cnsRow['cns'], 6, "0", STR_PAD_LEFT);

        $livroFormatado = str_pad($livro, 5, "0", STR_PAD_LEFT);
        $folhaFormatada = str_pad($folha, 3, "0", STR_PAD_LEFT);
        $termoFormatado = str_pad($termo, 7, "0", STR_PAD_LEFT);
        
        if ($data_registro && $data_registro !== '0000-00-00') {
            $dataRegistroAno = explode("-", $data_registro)[0];
        } else {
            $dataRegistroAno = date('Y');
        }
        
        $tipoLivro = '1';
        $acervo = '01';

        $matriculaBase = $cns . $acervo . '55' . $dataRegistroAno . $tipoLivro . $livroFormatado . $folhaFormatada . $termoFormatado;
        $digitoVerificador = calcularDigitoVerificador($matriculaBase);

        return $matriculaBase . $digitoVerificador;
    }
    
    return null;
}

/**
 * Função para remover acentos de uma string
 */
function removerAcentos($string) {
    $acentos = [
        'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A',
        'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a',
        'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E',
        'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e',
        'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I',
        'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
        'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O',
        'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o',
        'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U',
        'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u',
        'Ç'=>'C', 'ç'=>'c',
        'Ñ'=>'N', 'ñ'=>'n',
        'Ý'=>'Y', 'ý'=>'y', 'ÿ'=>'y',
        'Ž'=>'Z', 'ž'=>'z',
        'Š'=>'S', 'š'=>'s'
    ];
    
    return strtr($string, $acentos);
}

/**
 * Função para consultar o código IBGE de uma cidade pela API do IBGE
 * Formato esperado: "NOME_CIDADE/UF" (ex: "SANTA INES/MA")
 */
function consultarCodigoIBGE($cidadeUF) {
    global $ibgeCidadeCache;
    
    $cacheKey = mb_strtoupper(trim($cidadeUF), 'UTF-8');
    if (isset($ibgeCidadeCache[$cacheKey])) {
        return $ibgeCidadeCache[$cacheKey];
    }
    
    // Separar cidade e UF (aceita / ou -)
    $partes = preg_split('/[\/\-]/', $cidadeUF);
    
    if (count($partes) < 2) {
        return null;
    }
    
    $nomeCidade = trim($partes[0]);
    $uf = trim($partes[count($partes) - 1]); // Pega o último elemento como UF
    
    $nomeCidadeNormalizado = removerAcentos(mb_strtoupper($nomeCidade, 'UTF-8'));
    $ufNormalizado = mb_strtoupper($uf, 'UTF-8');
    
    // Validar se UF tem 2 caracteres
    if (strlen($ufNormalizado) !== 2) {
        return null;
    }
    
    try {
        $url = "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$ufNormalizado}/municipios";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return null;
        }
        
        $municipios = json_decode($response, true);
        
        if (!is_array($municipios)) {
            return null;
        }
        
        // Procurar a cidade pelo nome (comparação exata sem acentos)
        foreach ($municipios as $municipio) {
            $nomeMunicipioNormalizado = removerAcentos(mb_strtoupper($municipio['nome'], 'UTF-8'));
            
            if ($nomeMunicipioNormalizado === $nomeCidadeNormalizado) {
                $codigoIBGE = (string)$municipio['id'];
                $ibgeCidadeCache[$cacheKey] = $codigoIBGE;
                return $codigoIBGE;
            }
        }
        
        // Se não encontrou exato, tentar busca parcial (similar_text)
        $melhorMatch = null;
        $melhorSimilaridade = 0;
        
        foreach ($municipios as $municipio) {
            $nomeMunicipioNormalizado = removerAcentos(mb_strtoupper($municipio['nome'], 'UTF-8'));
            
            similar_text($nomeCidadeNormalizado, $nomeMunicipioNormalizado, $percentual);
            
            if ($percentual > 80 && $percentual > $melhorSimilaridade) {
                $melhorSimilaridade = $percentual;
                $melhorMatch = (string)$municipio['id'];
            }
        }
        
        if ($melhorMatch) {
            $ibgeCidadeCache[$cacheKey] = $melhorMatch;
            return $melhorMatch;
        }
        
    } catch (Exception $e) {
        return null;
    }
    
    return null;
}

/**
 * Função para verificar se uma string contém apenas números
 */
function apenasNumeros($string) {
    return preg_match('/^\d+$/', trim($string));
}

/**
 * Função para processar o campo ibge_naturalidade
 * Se vier número, mantém o número
 * Se vier texto (CIDADE/UF), consulta a API do IBGE
 * Retorna array com [codigo_ibge, nome_cidade]
 */
function processarNaturalidade($valor) {
    if ($valor === null || trim($valor) === '') {
        return [null, null];
    }
    
    $valor = trim($valor);
    
    // Se for apenas números, é o código IBGE direto
    if (apenasNumeros($valor)) {
        return [$valor, null];
    }
    
    // Se contém letras, é o nome da cidade - consultar API do IBGE
    $codigoIBGE = consultarCodigoIBGE($valor);
    
    // Extrair apenas o nome da cidade (sem a UF) para o campo naturalidade
    $partes = preg_split('/[\/\-]/', $valor);
    $nomeCidade = mb_strtoupper(trim($partes[0]), 'UTF-8');
    
    return [$codigoIBGE, $nomeCidade];
}

/** 
 * Converte um valor vindo da planilha (string ou número Excel) para Y-m-d. 
 * Se não conseguir converter, retorna '0000-00-00'. 
 */  
function formatDateToMysql($value) {  
    if ($value === null || $value === '' || $value === '0000-00-00') {  
        return '0000-00-00';  
    }  

    if (is_numeric($value)) {  
        try {  
            $timestamp = ExcelDate::excelToTimestamp($value);  
            return date('Y-m-d', $timestamp);  
        } catch (Exception $e) {  
            // Continua para tentar parse como string  
        }  
    }  

    if (is_string($value)) {  
        $value = trim($value);  
        if ($value === '') {  
            return '0000-00-00';  
        }  

        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $value, $m)) {  
            $dia = str_pad($m[1], 2, '0', STR_PAD_LEFT);  
            $mes = str_pad($m[2], 2, '0', STR_PAD_LEFT);  
            $ano = $m[3];  
            if (strlen($ano) === 2) {  
                $ano = ($ano > 50) ? '19' . $ano : '20' . $ano;  
            }  
            return $ano . '-' . $mes . '-' . $dia;  
        }  

        $time = strtotime($value);  
        if ($time !== false) {  
            return date('Y-m-d', $time);  
        }  
    }  

    return '0000-00-00';  
}  

try {  
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivos'])) {  
        $arquivos = $_FILES['arquivos'];  

        $tipoPlanilha = isset($_POST['tipo_planilha']) && $_POST['tipo_planilha'] === 'completa' ? 'completa' : 'simples';  
        
        foreach ($arquivos['tmp_name'] as $index => $tmpName) {  
            $fileExtension = strtolower(pathinfo($arquivos['name'][$index], PATHINFO_EXTENSION));  
            $validExtension = in_array($fileExtension, ['xlsx', 'xls']);  
            
            if ($validExtension) {  
                try {  
                    $spreadsheet = IOFactory::load($tmpName);  
                    $worksheet = $spreadsheet->getActiveSheet();  
                    $linhasProcessadas = 0;  

                    foreach ($worksheet->getRowIterator() as $row) {  
                        if ($row->getRowIndex() == 1) {  
                            continue;  
                        }  
                        
                        $cellIterator = $row->getCellIterator();  
                        $cellIterator->setIterateOnlyExistingCells(false);  
                        
                        $dados = [];  
                        foreach ($cellIterator as $cell) {  
                            $dados[] = $cell->getValue();  
                        }  

                        if (empty($dados[0])) {  
                            continue;  
                        }  

                        // Valores padrão  
                        $termo = null;  
                        $livro = null;  
                        $folha = null;  
                        $data_registro = '0000-00-00';  
                        $data_nascimento = '0000-00-00';  
                        $nome_registrado = null;  
                        $nome_pai = null;  
                        $nome_mae = null;  
                        $matricula = null;  
                        $naturalidade = null;  
                        $ibge_naturalidade = null;  
                        $sexo = null;  

                        if ($tipoPlanilha === 'simples') {  
                            // Estrutura: termo, nome_registrado, livro, folha  
                            $termo = isset($dados[0]) ? trim($dados[0]) : null;  
                            $nome_registrado = isset($dados[1]) ? trim($dados[1]) : null;  
                            $livro = isset($dados[2]) ? trim($dados[2]) : null;  
                            $folha = isset($dados[3]) ? trim($dados[3]) : null;  
                        } else {  
                            // Planilha completa:  
                            // termo, livro, folha, data_registro, data_nascimento,  
                            // nome_registrado, nome_pai, nome_mae, matricula, ibge_naturalidade, sexo  
                            $termo = isset($dados[0]) ? trim($dados[0]) : null;  
                            $livro = isset($dados[1]) ? trim($dados[1]) : null;  
                            $folha = isset($dados[2]) ? trim($dados[2]) : null;  

                            $data_registro = isset($dados[3]) ? formatDateToMysql($dados[3]) : '0000-00-00';  
                            $data_nascimento = isset($dados[4]) ? formatDateToMysql($dados[4]) : '0000-00-00';  

                            $nome_registrado = isset($dados[5]) ? trim($dados[5]) : null;  
                            $nome_pai = isset($dados[6]) && trim($dados[6]) !== '' ? trim($dados[6]) : null;  
                            $nome_mae = isset($dados[7]) && trim($dados[7]) !== '' ? trim($dados[7]) : null;  
                            
                            // ========== PROCESSAMENTO DA MATRÍCULA ==========
                            // Se o campo matrícula estiver vazio, calcular automaticamente
                            $matriculaValor = isset($dados[8]) ? trim($dados[8]) : '';
                            if ($matriculaValor === '' || $matriculaValor === null) {
                                // Calcular matrícula usando a mesma lógica do cadastro manual
                                $matricula = gerarMatricula($conn, $livro, $folha, $termo, $data_registro);
                            } else {
                                $matricula = $matriculaValor;
                            }

                            // ========== PROCESSAMENTO DO IBGE_NATURALIDADE ==========
                            // Se vier número: salva direto em ibge_naturalidade
                            // Se vier texto (CIDADE/UF): consulta API IBGE e salva código em ibge_naturalidade
                            //                           e o nome da cidade em naturalidade
                            $ibgeValor = isset($dados[9]) ? trim($dados[9]) : '';
                            if ($ibgeValor !== '' && $ibgeValor !== null) {
                                list($codigoIbge, $nomeCidade) = processarNaturalidade($ibgeValor);
                                $ibge_naturalidade = $codigoIbge;
                                
                                // Se veio nome da cidade na planilha, salvar no campo naturalidade
                                if ($nomeCidade !== null) {
                                    $naturalidade = $nomeCidade;
                                }
                            }
                            
                            $sexo = isset($dados[10]) && trim($dados[10]) !== '' ? trim($dados[10]) : null;  
                        }  

                        $funcionario = $_SESSION['usuario_nome'] ?? $_SESSION['username'] ?? 'Sistema';  
                        $status = 'ativo';  

                        $sql = "INSERT INTO indexador_nascimento   
                                (termo, livro, folha, data_registro, data_nascimento, nome_registrado, nome_pai, nome_mae, funcionario, matricula, naturalidade, ibge_naturalidade, sexo, status)   
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";  

                        $stmt = $conn->prepare($sql);  

                        if ($stmt === false) {  
                            throw new Exception('Erro ao preparar statement: ' . $conn->error);  
                        }  

                        $stmt->bind_param(  
                            'ssssssssssssss',  
                            $termo,  
                            $livro,  
                            $folha,  
                            $data_registro,  
                            $data_nascimento,  
                            $nome_registrado,  
                            $nome_pai,  
                            $nome_mae,  
                            $funcionario,  
                            $matricula,  
                            $naturalidade,  
                            $ibge_naturalidade,  
                            $sexo,  
                            $status  
                        );  

                        if ($stmt->execute()) {  
                            $linhasProcessadas++;  
                        }  
                        
                        $stmt->close();
                    }  

                    $descricaoTipo = $tipoPlanilha === 'simples' ? 'planilha simples' : 'planilha completa';  

                    $result = [  
                        'status' => 'success',  
                        'message' => "$linhasProcessadas linhas processadas com sucesso ($descricaoTipo) no arquivo '{$arquivos['name'][$index]}'."  
                    ];  
                } catch (Exception $e) {  
                    $result = [  
                        'status' => 'error',  
                        'message' => "Erro ao processar o arquivo '{$arquivos['name'][$index]}': " . $e->getMessage()  
                    ];  
                }  
            } else {  
                $result = [  
                    'status' => 'error',  
                    'message' => "O arquivo '{$arquivos['name'][$index]}' não é um arquivo Excel válido."  
                ];  
            }  
        }  
    }  
} catch (Exception $e) {  
    $result = [  
        'status' => 'error',  
        'message' => 'Erro ao processar os arquivos: ' . $e->getMessage()  
    ];  
}  

// Limpar qualquer saída pendente  
ob_end_clean();  

// Adicionar mensagens de erro se houver  
if (!empty($errorMessages)) {  
    $result['errors'] = $errorMessages;  
}  

// Restaurar o manipulador de erros padrão  
restore_error_handler();  

// Retornar JSON  
header('Content-Type: application/json');  
echo json_encode($result);  
exit;  
?>
