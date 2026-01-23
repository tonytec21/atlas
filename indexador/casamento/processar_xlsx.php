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
 * Função para gerar a matrícula completa de casamento
 * (Mesma lógica usada em salvar_registro.php)
 * tipoLivro: 2 para CIVIL, 3 para RELIGIOSO
 */
function gerarMatriculaCasamento($conn, $livro, $folha, $termo, $data_registro, $tipo_casamento) {
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
        
        // Tipo de livro: 2 para CIVIL, 3 para RELIGIOSO
        $tipoLivro = ($tipo_casamento === 'CIVIL') ? '2' : '3';
        $acervo = '01';
        $fixo55 = '55';

        $matriculaBase = $cns . $acervo . $fixo55 . $dataRegistroAno . $tipoLivro . $livroFormatado . $folhaFormatada . $termoFormatado;
        $digitoVerificador = calcularDigitoVerificador($matriculaBase);

        return $matriculaBase . $digitoVerificador;
    }
    
    return null;
}

/**
 * Função para sanitizar texto
 * (Mesma lógica usada em salvar_registro.php)
 */
function sanitize_text($s) {
    if ($s === null) return '';
    $s = str_replace(array("\r\n","\r","\n"), ' ', trim($s));
    $s = str_replace('&','&amp;', $s);
    $s = str_replace('<','&lt;',  $s);
    $s = str_replace('>','&gt;',  $s);
    $s = str_replace('"','&quot;', $s);
    $s = preg_replace("/(?<!\\p{L})'|'(?!\\p{L})/u", '&#39;', $s);
    return $s;
}

/**
 * Normaliza o valor do tipo de casamento
 */
function normalizarTipoCasamento($valor) {
    if (empty($valor)) return 'CIVIL';
    
    $valor = mb_strtoupper(trim($valor), 'UTF-8');
    
    // Remove acentos e caracteres especiais para comparação
    $valor = preg_replace('/[^A-Z]/', '', $valor);
    
    if (strpos($valor, 'RELIG') !== false) {
        return 'RELIGIOSO';
    }
    
    return 'CIVIL';
}

/**
 * Normaliza o valor do regime de bens
 */
function normalizarRegimeBens($valor) {
    if (empty($valor)) return 'COMUNHAO_PARCIAL';
    
    $valor = mb_strtoupper(trim($valor), 'UTF-8');
    
    // Mapeamento de valores possíveis
    $mapeamento = [
        'COMUNHAO PARCIAL' => 'COMUNHAO_PARCIAL',
        'COMUNHAO_PARCIAL' => 'COMUNHAO_PARCIAL',
        'PARCIAL' => 'COMUNHAO_PARCIAL',
        'COMUNHAO UNIVERSAL' => 'COMUNHAO_UNIVERSAL',
        'COMUNHAO_UNIVERSAL' => 'COMUNHAO_UNIVERSAL',
        'UNIVERSAL' => 'COMUNHAO_UNIVERSAL',
        'PARTICIPACAO FINAL AQUESTOS' => 'PARTICIPACAO_FINAL_AQUESTOS',
        'PARTICIPACAO_FINAL_AQUESTOS' => 'PARTICIPACAO_FINAL_AQUESTOS',
        'PARTICIPACAO' => 'PARTICIPACAO_FINAL_AQUESTOS',
        'AQUESTOS' => 'PARTICIPACAO_FINAL_AQUESTOS',
        'SEPARACAO BENS' => 'SEPARACAO_BENS',
        'SEPARACAO_BENS' => 'SEPARACAO_BENS',
        'SEPARACAO DE BENS' => 'SEPARACAO_BENS',
        'SEPARACAO LEGAL BENS' => 'SEPARACAO_LEGAL_BENS',
        'SEPARACAO_LEGAL_BENS' => 'SEPARACAO_LEGAL_BENS',
        'SEPARACAO LEGAL DE BENS' => 'SEPARACAO_LEGAL_BENS',
        'LEGAL' => 'SEPARACAO_LEGAL_BENS',
        'OUTROS' => 'OUTROS',
        'OUTRO' => 'OUTROS',
        'IGNORADO' => 'IGNORADO',
        'NAO INFORMADO' => 'IGNORADO',
        'NÃO INFORMADO' => 'IGNORADO',
    ];
    
    // Normaliza removendo acentos e underscores para comparação
    $valorNormalizado = str_replace('_', ' ', $valor);
    
    foreach ($mapeamento as $chave => $retorno) {
        if ($valor === $chave || $valorNormalizado === $chave) {
            return $retorno;
        }
    }
    
    // Se não encontrou, tenta uma busca parcial
    if (strpos($valor, 'PARCIAL') !== false) return 'COMUNHAO_PARCIAL';
    if (strpos($valor, 'UNIVERSAL') !== false) return 'COMUNHAO_UNIVERSAL';
    if (strpos($valor, 'AQUESTO') !== false) return 'PARTICIPACAO_FINAL_AQUESTOS';
    if (strpos($valor, 'LEGAL') !== false) return 'SEPARACAO_LEGAL_BENS';
    if (strpos($valor, 'SEPARACAO') !== false || strpos($valor, 'SEPARAÇÃO') !== false) return 'SEPARACAO_BENS';
    if (strpos($valor, 'IGNOR') !== false) return 'IGNORADO';
    
    return 'COMUNHAO_PARCIAL'; // Valor padrão
}

/**
 * Normaliza o valor do sexo
 */
function normalizarSexo($valor) {
    if (empty($valor)) return 'M';
    
    $valor = mb_strtoupper(trim($valor), 'UTF-8');
    
    if ($valor === 'F' || $valor === 'FEMININO' || $valor === 'MULHER') {
        return 'F';
    }
    
    return 'M';
}

/**  
 * Função para converter data de diferentes formatos para MySQL (Y-m-d)  
 * Tenta aceitar: Excel serial, DD/MM/AAAA, DD-MM-AAAA  
 */  
function formatDateToMysql($value) {  
    if (empty($value) || $value === null) {  
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
                            continue; // Pula cabeçalho
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
                        $tipo_casamento = 'CIVIL';
                        $data_registro = '0000-00-00';  
                        $conjuge1_nome = null;  
                        $conjuge1_nome_casado = null;  
                        $conjuge1_sexo = 'M';  
                        $conjuge2_nome = null;  
                        $conjuge2_nome_casado = null;  
                        $conjuge2_sexo = 'F';  
                        $regime_bens = 'COMUNHAO_PARCIAL';
                        $data_casamento = '0000-00-00';  
                        $matricula = null;  

                        if ($tipoPlanilha === 'simples') {  
                            // Estrutura: termo, conjuge1_nome, conjuge2_nome, livro, folha  
                            $termo = isset($dados[0]) ? trim($dados[0]) : null;  
                            $conjuge1_nome = isset($dados[1]) ? sanitize_text(mb_strtoupper(trim($dados[1]), 'UTF-8')) : null;  
                            $conjuge2_nome = isset($dados[2]) ? sanitize_text(mb_strtoupper(trim($dados[2]), 'UTF-8')) : null;  
                            $livro = isset($dados[3]) ? trim($dados[3]) : null;  
                            $folha = isset($dados[4]) ? trim($dados[4]) : null;  
                            
                            // Valores padrão para planilha simples
                            $tipo_casamento = 'CIVIL';
                            $conjuge1_sexo = 'M';
                            $conjuge2_sexo = 'F';
                            $regime_bens = 'COMUNHAO_PARCIAL';
                            
                        } else {  
                            // Planilha completa:  
                            // termo, livro, folha, tipo_casamento, data_registro, 
                            // conjuge1_nome, conjuge1_nome_casado, conjuge1_sexo, 
                            // conjuge2_nome, conjuge2_nome_casado, conjuge2_sexo, 
                            // regime_bens, data_casamento, matricula
                            
                            $termo = isset($dados[0]) ? trim($dados[0]) : null;  
                            $livro = isset($dados[1]) ? trim($dados[1]) : null;  
                            $folha = isset($dados[2]) ? trim($dados[2]) : null;  
                            
                            $tipo_casamento = isset($dados[3]) ? normalizarTipoCasamento($dados[3]) : 'CIVIL';
                            $data_registro = isset($dados[4]) ? formatDateToMysql($dados[4]) : '0000-00-00';  
                            
                            $conjuge1_nome = isset($dados[5]) ? sanitize_text(mb_strtoupper(trim($dados[5]), 'UTF-8')) : null;  
                            $conjuge1_nome_casado = isset($dados[6]) && trim($dados[6]) !== '' ? sanitize_text(mb_strtoupper(trim($dados[6]), 'UTF-8')) : null;  
                            $conjuge1_sexo = isset($dados[7]) ? normalizarSexo($dados[7]) : 'M';  
                            
                            $conjuge2_nome = isset($dados[8]) ? sanitize_text(mb_strtoupper(trim($dados[8]), 'UTF-8')) : null;  
                            $conjuge2_nome_casado = isset($dados[9]) && trim($dados[9]) !== '' ? sanitize_text(mb_strtoupper(trim($dados[9]), 'UTF-8')) : null;  
                            $conjuge2_sexo = isset($dados[10]) ? normalizarSexo($dados[10]) : 'F';  
                            
                            $regime_bens = isset($dados[11]) ? normalizarRegimeBens($dados[11]) : 'COMUNHAO_PARCIAL';
                            $data_casamento = isset($dados[12]) ? formatDateToMysql($dados[12]) : '0000-00-00';  
                            
                            // ========== PROCESSAMENTO DA MATRÍCULA ==========
                            // Se o campo matrícula estiver vazio, calcular automaticamente
                            $matriculaValor = isset($dados[13]) ? trim($dados[13]) : '';
                            if ($matriculaValor === '' || $matriculaValor === null) {
                                // Calcular matrícula usando a mesma lógica do cadastro manual
                                $matricula = gerarMatriculaCasamento($conn, $livro, $folha, $termo, $data_registro, $tipo_casamento);
                            } else {
                                $matricula = $matriculaValor;
                            }
                        }  

                        // Para planilha simples, também gerar matrícula se não existir
                        if ($tipoPlanilha === 'simples' && empty($matricula)) {
                            $matricula = gerarMatriculaCasamento($conn, $livro, $folha, $termo, $data_registro, $tipo_casamento);
                        }

                        // Se data_casamento não foi preenchida, usar data_registro
                        if ($data_casamento === '0000-00-00' && $data_registro !== '0000-00-00') {
                            $data_casamento = $data_registro;
                        }

                        $funcionario = $_SESSION['usuario_nome'] ?? $_SESSION['username'] ?? 'Sistema';  
                        $status = 'ativo';  

                        $sql = "INSERT INTO indexador_casamento   
                                (termo, livro, folha, tipo_casamento, data_registro, 
                                conjuge1_nome, conjuge1_nome_casado, conjuge1_sexo, 
                                conjuge2_nome, conjuge2_nome_casado, conjuge2_sexo, 
                                regime_bens, data_casamento, matricula, funcionario, status)   
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";  

                        $stmt = $conn->prepare($sql);  

                        if ($stmt === false) {  
                            throw new Exception('Erro ao preparar statement: ' . $conn->error);  
                        }  

                        $stmt->bind_param(  
                            'ssssssssssssssss',  
                            $termo,  
                            $livro,  
                            $folha,  
                            $tipo_casamento,
                            $data_registro,  
                            $conjuge1_nome,  
                            $conjuge1_nome_casado,  
                            $conjuge1_sexo,  
                            $conjuge2_nome,  
                            $conjuge2_nome_casado,  
                            $conjuge2_sexo,  
                            $regime_bens,
                            $data_casamento,
                            $matricula,
                            $funcionario,  
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
