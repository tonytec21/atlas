<?php  
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