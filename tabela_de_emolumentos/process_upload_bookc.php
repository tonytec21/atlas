<?php
// Desabilita exibição de erros no output (para não quebrar o JSON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Inicia buffer de saída para capturar qualquer output indesejado
ob_start();

header('Content-Type: application/json; charset=utf-8');

// Função para responder com erro e sair
function responderErro($mensagem, $detalhes = null) {
    ob_end_clean();
    $response = [
        'status' => 'error',
        'message' => $mensagem,
        'insertedCount' => 0,
        'errors' => [],
        'ignoredAtos' => []
    ];
    if ($detalhes) {
        $response['detalhes'] = $detalhes;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para responder com sucesso
function responderSucesso($data) {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Inclui arquivos de sessão e conexão
try {
    if (file_exists(__DIR__ . '/session_check.php')) {
        include(__DIR__ . '/session_check.php');
        checkSession();
    }
} catch (Exception $e) {
    responderErro('Erro na sessão: ' . $e->getMessage());
}

try {
    if (file_exists(__DIR__ . '/db_connection.php')) {
        include(__DIR__ . '/db_connection.php');
    } else {
        responderErro('Arquivo de conexão com banco de dados não encontrado.');
    }
} catch (Exception $e) {
    responderErro('Erro ao conectar ao banco: ' . $e->getMessage());
}

// Procura o autoloader em diferentes caminhos possíveis
$possiveisCaminhos = [
    __DIR__ . '/../indexador/vendor/autoload.php',
];

$autoloadPath = null;
foreach ($possiveisCaminhos as $caminho) {
    if (file_exists($caminho)) {
        $autoloadPath = $caminho;
        break;
    }
}

if ($autoloadPath === null) {
    responderErro('Biblioteca PhpSpreadsheet não encontrada. Verifique se o Composer está configurado corretamente.');
}

try {
    require_once $autoloadPath;
} catch (Exception $e) {
    responderErro('Erro ao carregar bibliotecas: ' . $e->getMessage());
}

// Verifica se a classe existe
if (!class_exists('PhpOffice\PhpSpreadsheet\Reader\Xlsx')) {
    responderErro('PhpSpreadsheet não está instalado corretamente.');
}

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * Valida o formato do código de emolumentos (strCodigoLei)
 * Aceita apenas números, pontos e letras A, B, C, D (maiúsculas ou minúsculas)
 * Exemplos válidos: 13.1.15, 14.5.1, 14.a, 14.b, 14.A, 14.B
 */
function validarCodigo($codigo) {
    if (empty($codigo)) {
        return false;
    }
    
    $codigo = trim((string)$codigo);
    
    // Padrão: apenas números, pontos e letras a, b, c, d (case insensitive)
    $pattern = '/^[0-9.aAbBcCdD]+$/';
    
    return preg_match($pattern, $codigo);
}

/**
 * Formata valor monetário para decimal
 * Aceita formatos: 1.234,56 ou 1234.56 ou 1234,56
 */
function formatarValorMonetario($valor) {
    if (empty($valor) || $valor === null || $valor === '-' || $valor === 'N/A') {
        return 0.00;
    }
    
    if (is_numeric($valor)) {
        return floatval($valor);
    }
    
    $valor = trim((string)$valor);
    
    // Remove símbolo de moeda R$
    $valor = preg_replace('/R\$\s*/', '', $valor);
    
    // Se contém vírgula como decimal (formato brasileiro)
    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }
    
    // Remove caracteres não numéricos exceto ponto e sinal negativo
    $valor = preg_replace('/[^0-9.\-]/', '', $valor);
    
    if (empty($valor)) {
        return 0.00;
    }
    
    return floatval($valor);
}

/**
 * Cria a tabela ato_novo se não existir
 * Estrutura: strCodigoLei, strTipoAto, monValor, monValorFerc, monValorTotal, intTipoAtoId, FEMP, FADEP, FERRFIS, TOTAL
 */
function criarTabela($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS ato_novo (
        strCodigoLei VARCHAR(50) NOT NULL,
        strTipoAto TEXT,
        monValor DECIMAL(15,2) DEFAULT 0.00,
        monValorFerc DECIMAL(15,2) DEFAULT 0.00,
        monValorTotal DECIMAL(15,2) DEFAULT NULL,
        intTipoAtoId INT DEFAULT NULL,
        FEMP DECIMAL(15,2) DEFAULT 0.00,
        FADEP DECIMAL(15,2) DEFAULT 0.00,
        FERRFIS DECIMAL(15,2) DEFAULT 0.00,
        TOTAL DECIMAL(15,2) DEFAULT 0.00,
        UNIQUE KEY unique_codigo_lei (strCodigoLei)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        throw new Exception("Erro ao criar tabela: " . $conn->error);
    }
    
    return true;
}

// Resposta padrão
$response = [
    'status' => 'error',
    'message' => 'Erro desconhecido',
    'insertedCount' => 0,
    'errors' => [],
    'ignoredAtos' => []
];

try {
    // Verifica se o arquivo foi enviado
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor.',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido pelo formulário.',
            UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar o arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão.'
        ];
        
        $errorCode = isset($_FILES['file']) ? $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;
        responderErro($errorMessages[$errorCode] ?? 'Erro ao enviar arquivo.');
    }
    
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileTmpPath = $file['tmp_name'];
    
    // Verifica extensão do arquivo
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($fileExtension !== 'xlsx') {
        responderErro('Formato de arquivo inválido. Apenas arquivos .xlsx são aceitos.');
    }
    
    // Verifica se a variável de conexão existe
    if (!isset($conn)) {
        responderErro('Conexão com banco de dados não estabelecida.');
    }
    
    // Cria a tabela se não existir
    criarTabela($conn);
    
    // Carrega a planilha
    $reader = new Xlsx();
    $reader->setReadDataOnly(true);
    
    try {
        $spreadsheet = $reader->load($fileTmpPath);
    } catch (Exception $e) {
        responderErro('Erro ao ler a planilha: ' . $e->getMessage());
    }
    
    // Pega a primeira planilha
    $worksheet = $spreadsheet->getActiveSheet();
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    
    // Colunas esperadas na planilha -> mapeamento para o banco
    // Planilha: CÓDIGO,      ATOS,       EMOLUMENTOS, FERC,         FADEP, FEMP, FERRFIS, TOTAL
    // Banco:    strCodigoLei, strTipoAto, monValor,    monValorFerc, FADEP, FEMP, FERRFIS, TOTAL
    // Colunas monValorTotal e intTipoAtoId ficam com valor NULL
    $expectedColumns = ['CÓDIGO', 'ATOS', 'EMOLUMENTOS', 'FERC', 'FADEP', 'FEMP', 'FERRFIS', 'TOTAL'];
    $columnMap = [];
    
    // Lê o cabeçalho (primeira linha)
    $headerRow = $worksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, true, true)[1];
    
    // Mapeia as colunas pelo nome do cabeçalho
    foreach ($headerRow as $col => $value) {
        if ($value !== null) {
            $normalizedValue = mb_strtoupper(trim((string)$value), 'UTF-8');
            // Remove acentos para comparação
            $normalizedValue = preg_replace('/[ÁÀÂÃ]/u', 'A', $normalizedValue);
            $normalizedValue = preg_replace('/[ÉÈÊË]/u', 'E', $normalizedValue);
            $normalizedValue = preg_replace('/[ÍÌÎÏ]/u', 'I', $normalizedValue);
            $normalizedValue = preg_replace('/[ÓÒÔÕ]/u', 'O', $normalizedValue);
            $normalizedValue = preg_replace('/[ÚÙÛÜ]/u', 'U', $normalizedValue);
            
            foreach ($expectedColumns as $expected) {
                $normalizedExpected = preg_replace('/[ÁÀÂÃ]/u', 'A', $expected);
                $normalizedExpected = preg_replace('/[ÉÈÊË]/u', 'E', $normalizedExpected);
                $normalizedExpected = preg_replace('/[ÍÌÎÏ]/u', 'I', $normalizedExpected);
                $normalizedExpected = preg_replace('/[ÓÒÔÕ]/u', 'O', $normalizedExpected);
                $normalizedExpected = preg_replace('/[ÚÙÛÜ]/u', 'U', $normalizedExpected);
                
                if ($normalizedValue === $normalizedExpected || strpos($normalizedValue, $normalizedExpected) !== false) {
                    $columnMap[$expected] = $col;
                    break;
                }
            }
        }
    }
    
    // Verifica se as colunas obrigatórias foram encontradas
    $requiredColumns = ['CÓDIGO', 'ATOS'];
    $missingColumns = [];
    foreach ($requiredColumns as $required) {
        if (!isset($columnMap[$required])) {
            $missingColumns[] = $required;
        }
    }
    
    if (!empty($missingColumns)) {
        $foundHeaders = array_values(array_filter($headerRow, function($v) { return $v !== null; }));
        responderErro(
            "Colunas obrigatórias não encontradas: " . implode(', ', $missingColumns) . ". Verifique se o cabeçalho está correto.",
            "Colunas encontradas: " . implode(', ', $foundHeaders)
        );
    }
    
    // Prepara o statement de inserção
    // Mapeamento:
    // CÓDIGO -> strCodigoLei
    // ATOS -> strTipoAto
    // EMOLUMENTOS -> monValor
    // FERC -> monValorFerc
    // monValorTotal -> NULL
    // intTipoAtoId -> NULL
    // FEMP -> FEMP
    // FADEP -> FADEP
    // FERRFIS -> FERRFIS
    // TOTAL -> TOTAL
    $stmt = $conn->prepare("INSERT INTO ato_novo 
        (strCodigoLei, strTipoAto, monValor, monValorFerc, monValorTotal, intTipoAtoId, FEMP, FADEP, FERRFIS, TOTAL) 
        VALUES (?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        strTipoAto = VALUES(strTipoAto),
        monValor = VALUES(monValor),
        monValorFerc = VALUES(monValorFerc),
        monValorTotal = NULL,
        intTipoAtoId = NULL,
        FEMP = VALUES(FEMP),
        FADEP = VALUES(FADEP),
        FERRFIS = VALUES(FERRFIS),
        TOTAL = VALUES(TOTAL)");
    
    if (!$stmt) {
        responderErro("Erro ao preparar statement: " . $conn->error);
    }
    
    $insertedCount = 0;
    $errors = [];
    $ignoredAtos = [];
    
    // Processa as linhas de dados (começando da linha 2)
    for ($row = 2; $row <= $highestRow; $row++) {
        try {
            // Lê os valores das colunas mapeadas
            // CÓDIGO da planilha vai para strCodigoLei do banco
            $strCodigoLei = isset($columnMap['CÓDIGO']) ? 
                $worksheet->getCell($columnMap['CÓDIGO'] . $row)->getValue() : null;
            // ATOS da planilha vai para strTipoAto do banco
            $strTipoAto = isset($columnMap['ATOS']) ? 
                $worksheet->getCell($columnMap['ATOS'] . $row)->getValue() : null;
            // EMOLUMENTOS da planilha vai para monValor do banco
            $monValor = isset($columnMap['EMOLUMENTOS']) ? 
                $worksheet->getCell($columnMap['EMOLUMENTOS'] . $row)->getValue() : 0;
            // FERC da planilha vai para monValorFerc do banco
            $monValorFerc = isset($columnMap['FERC']) ? 
                $worksheet->getCell($columnMap['FERC'] . $row)->getValue() : 0;
            // FEMP da planilha vai para FEMP do banco
            $femp = isset($columnMap['FEMP']) ? 
                $worksheet->getCell($columnMap['FEMP'] . $row)->getValue() : 0;
            // FADEP da planilha vai para FADEP do banco
            $fadep = isset($columnMap['FADEP']) ? 
                $worksheet->getCell($columnMap['FADEP'] . $row)->getValue() : 0;
            // FERRFIS da planilha vai para FERRFIS do banco
            $ferrfis = isset($columnMap['FERRFIS']) ? 
                $worksheet->getCell($columnMap['FERRFIS'] . $row)->getValue() : 0;
            // TOTAL da planilha vai para TOTAL do banco
            $total = isset($columnMap['TOTAL']) ? 
                $worksheet->getCell($columnMap['TOTAL'] . $row)->getValue() : 0;
            
            // Pula linhas vazias
            if (empty($strCodigoLei) && empty($strTipoAto)) {
                continue;
            }
            
            // Limpa e valida o código (strCodigoLei)
            $strCodigoLei = trim((string)$strCodigoLei);
            
            // Valida o código
            if (!validarCodigo($strCodigoLei)) {
                $errors[] = [
                    'linha' => $row,
                    'codigo' => $strCodigoLei,
                    'erro' => "Código inválido '$strCodigoLei'. Use apenas números, pontos e letras A, B, C, D."
                ];
                continue;
            }
            
            // Formata os valores monetários
            $monValorFormatado = formatarValorMonetario($monValor);
            $monValorFercFormatado = formatarValorMonetario($monValorFerc);
            $fempFormatado = formatarValorMonetario($femp);
            $fadepFormatado = formatarValorMonetario($fadep);
            $ferrfisFormatado = formatarValorMonetario($ferrfis);
            $totalFormatado = formatarValorMonetario($total);
            
            // Se o total não foi informado, calcula
            if ($totalFormatado == 0 && ($monValorFormatado + $monValorFercFormatado + $fempFormatado + $fadepFormatado + $ferrfisFormatado) > 0) {
                $totalFormatado = $monValorFormatado + $monValorFercFormatado + $fempFormatado + $fadepFormatado + $ferrfisFormatado;
            }
            
            // Limpa o texto do tipo de ato (strTipoAto)
            $strTipoAto = trim((string)$strTipoAto);
            
            // Executa a inserção/atualização
            // Parâmetros: strCodigoLei, strTipoAto, monValor, monValorFerc, FEMP, FADEP, FERRFIS, TOTAL
            $stmt->bind_param(
                "ssdddddd",
                $strCodigoLei,
                $strTipoAto,
                $monValorFormatado,
                $monValorFercFormatado,
                $fempFormatado,
                $fadepFormatado,
                $ferrfisFormatado,
                $totalFormatado
            );
            
            if ($stmt->execute()) {
                $insertedCount++;
            } else {
                $errors[] = [
                    'linha' => $row,
                    'codigo' => $strCodigoLei,
                    'erro' => "Erro ao inserir: " . $stmt->error
                ];
            }
            
        } catch (Exception $e) {
            $errors[] = [
                'linha' => $row,
                'codigo' => isset($strCodigoLei) ? $strCodigoLei : 'N/A',
                'erro' => $e->getMessage()
            ];
        }
    }
    
    $stmt->close();
    
    // Define a resposta baseada no resultado
    if ($insertedCount > 0 && count($errors) === 0) {
        $response['status'] = 'success';
        $response['message'] = 'Planilha processada com sucesso!';
        $response['insertedCount'] = $insertedCount;
    } elseif ($insertedCount > 0 && count($errors) > 0) {
        $response['status'] = 'partial';
        $response['message'] = 'Planilha processada com alguns erros.';
        $response['insertedCount'] = $insertedCount;
        $response['errors'] = $errors;
    } elseif ($insertedCount === 0 && count($errors) > 0) {
        $response['status'] = 'error';
        $response['message'] = 'Nenhum registro foi inserido. Verifique os erros.';
        $response['errors'] = $errors;
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Nenhum dado válido encontrado na planilha.';
    }
    
    if (count($ignoredAtos) > 0) {
        $response['ignoredAtos'] = $ignoredAtos;
    }
    
    responderSucesso($response);
    
} catch (Exception $e) {
    responderErro('Erro ao processar arquivo: ' . $e->getMessage());
} catch (Error $e) {
    responderErro('Erro fatal: ' . $e->getMessage());
}