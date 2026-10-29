<?php  
/* atlas-guarda */ if (is_file(__DIR__ . '/../../seguranca/guarda.php')) { include_once __DIR__ . '/../../seguranca/guarda.php'; }
header('Content-Type: application/json');  
echo json_encode([  
    'status' => 'ok',  
    'php_version' => phpversion(),  
    'extensions' => [  
        'curl' => extension_loaded('curl'),  
        'json' => extension_loaded('json')  
    ],  
    'time' => date('Y-m-d H:i:s')  
]);  
?>