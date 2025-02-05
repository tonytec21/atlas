<?php  
declare(strict_types=1);  

require_once 'db_connection_kml.php';  

header('Content-Type: application/json; charset=UTF-8');  

/**  
 * Processa coordenadas do texto do memorial descritivo  
 */  
function extractCoordinates(string $text): array {  
    // Padrão para capturar coordenadas no formato N X.XXX.XXX,XXXX E XXX.XXX,XXXX  
    $pattern = '/N\s*([\d.,]+)m?\s*e?\s*E\s*([\d.,]+)m?/i';  
    preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);  
    
    $coordinates = [];  
    foreach ($matches as $match) {  
        // Remove os pontos das coordenadas  
        $north = str_replace('.', '', $match[1]);  
        $east = str_replace('.', '', $match[2]);  
        
        // Substitui a vírgula por ponto  
        $north = str_replace(',', '.', $north);  
        $east = str_replace(',', '.', $east);  
        
        // Verifica se são números válidos  
        if (is_numeric($north) && is_numeric($east)) {  
            // Formata as coordenadas mantendo o formato original  
            $formattedEast = number_format((float)$east, 2, '.', '');  
            $formattedNorth = number_format((float)$north, 2, '.', '');  
            
            $coordinates[] = "$formattedEast,$formattedNorth";  
        }  
    }  
    
    return $coordinates;  
}  

try {  
    $query = "SELECT proprietario_nome, memorial_descritivo   
              FROM cadastro_de_imoveis   
              WHERE memorial_descritivo IS NOT NULL";  
    
    $stmt = $pdo->query($query);  
    $imoveis = $stmt->fetchAll(PDO::FETCH_ASSOC);  
    
    $result = [];  
    
    foreach ($imoveis as $imovel) {  
        if (!empty($imovel['memorial_descritivo'])) {  
            $coordinates = extractCoordinates($imovel['memorial_descritivo']);  
            
            if (!empty($coordinates)) {  
                $result[] = [  
                    'proprietario_nome' => $imovel['proprietario_nome'],  
                    'coordinates' => implode(' ', $coordinates)  
                ];  
            }  
        }  
    }  
    
    echo json_encode($result);  

} catch (Exception $e) {  
    http_response_code(500);  
    echo json_encode([  
        'error' => true,  
        'message' => 'Erro ao processar dados'  
    ]);  
    error_log($e->getMessage());  
}