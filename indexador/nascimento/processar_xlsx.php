<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection.php');  

// Ativar manipulador de erros personalizado para capturar avisos  
set_error_handler(function($errno, $errstr, $errfile, $errline) {  
    // Armazenar erros para incluir na resposta  
    global $errorMessages;  
    $errorMessages[] = "$errstr em $errfile:$errline";  
    return true; // Impede que o PHP mostre o erro  
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
 * Converte um valor vindo da planilha (string ou número Excel) para Y-m-d. 
 * Se não conseguir converter, retorna '0000-00-00'. 
 */  
function formatDateToMysql($value) {  
    if ($value === null || $value === '' || $value === '0000-00-00') {  
        return '0000-00-00';  
    }  

    // Se vier como número (data serial do Excel)  
    if (is_numeric($value)) {  
        try {  
            $timestamp = ExcelDate::excelToTimestamp($value);  
            return date('Y-m-d', $timestamp);  
        } catch (Exception $e) {  
            // Cai para tentativa de parse como string  
        }  
    }  

    // Se vier como string (por ex.: dd/mm/aaaa ou dd-mm-aaaa)  
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
                $ano = '20' . $ano;  
            }  
            return $ano . '-' . $mes . '-' . $dia;  
        }  

        // Tenta parsear com strtotime()  
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

        // Tipo de planilha: 'simples' (padrão) ou 'completa'  
        $tipoPlanilha = isset($_POST['tipo_planilha']) && $_POST['tipo_planilha'] === 'completa' ? 'completa' : 'simples';  
        
        foreach ($arquivos['tmp_name'] as $index => $tmpName) {  
            // Verificar tipos de arquivo Excel  
            $allowedMimeTypes = [  
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',   
                'application/vnd.ms-excel'  
            ];  
            
            // Verificar extensão do arquivo também, já que o MIME pode não ser confiável  
            $fileExtension = strtolower(pathinfo($arquivos['name'][$index], PATHINFO_EXTENSION));  
            $validExtension = in_array($fileExtension, ['xlsx', 'xls']);  
            
            if ($validExtension) {  
                try {  
                    // Carregar o arquivo Excel com PhpSpreadsheet  
                    $spreadsheet = IOFactory::load($tmpName);  
                    $worksheet = $spreadsheet->getActiveSheet();  
                    $linhasProcessadas = 0;  

                    // Iterar pelas linhas da planilha  
                    foreach ($worksheet->getRowIterator() as $row) {  
                        // Pular a primeira linha se for cabeçalho  
                        if ($row->getRowIndex() == 1) {  
                            continue;  
                        }  
                        
                        $cellIterator = $row->getCellIterator();  
                        $cellIterator->setIterateOnlyExistingCells(false);  
                        
                        $dados = [];  
                        foreach ($cellIterator as $cell) {  
                            $dados[] = $cell->getValue();  
                        }  

                        // Se não houver termo, ignora a linha  
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

                        // Mapeamento conforme tipo de planilha  
                        if ($tipoPlanilha === 'simples') {  
                            // Estrutura atual: termo, nome_registrado, livro, folha  
                            $termo = isset($dados[0]) ? trim($dados[0]) : null;  
                            $nome_registrado = isset($dados[1]) ? trim($dados[1]) : null;  
                            $livro = isset($dados[2]) ? trim($dados[2]) : null;  
                            $folha = isset($dados[3]) ? trim($dados[3]) : null;  

                            // Datas e demais campos permanecem padrão (0000-00-00 ou NULL)  
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
                            $matricula = isset($dados[8]) && trim($dados[8]) !== '' ? trim($dados[8]) : null;  

                            // naturalidade não vem na planilha completa, permanece NULL  
                            $ibge_naturalidade = isset($dados[9]) && trim($dados[9]) !== '' ? trim($dados[9]) : null;  
                            $sexo = isset($dados[10]) && trim($dados[10]) !== '' ? trim($dados[10]) : null;  
                        }  

                        $funcionario = $_SESSION['usuario_nome'] ?? 'Sistema';  
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

// Adicionar quaisquer mensagens de erro no resultado  
if (!empty($errorMessages)) {  
    $result['errors'] = $errorMessages;  
}  

// Restaurar o manipulador de erros padrão  
restore_error_handler();  

// Garantir que os cabeçalhos estejam corretos para JSON  
header('Content-Type: application/json');  
echo json_encode($result);  
exit;  
?>
