<?php  
error_reporting(E_ALL);  
ini_set('display_errors', 1);  

include(__DIR__ . '/session_check.php');  
checkSession();  

header('Content-Type: application/json');  

$servername = "localhost";  
$username = "root";  
$password = "";  
$dbname = "oficios_db";  

$conn = new mysqli($servername, $username, $password, $dbname);  

if ($conn->connect_error) {  
    die(json_encode(['error' => "Falha na conexão: " . $conn->connect_error]));  
}  

$sql = "SELECT id, destinatario, assunto, assinante,   
        DATE_FORMAT(data, '%d/%m/%Y') as data, numero   
        FROM oficios   
        ORDER BY STR_TO_DATE(data, '%Y-%m-%d') DESC, id DESC";  

$result = $conn->query($sql);  

if (!$result) {  
    die(json_encode(['error' => "Erro na query: " . $conn->error]));  
}  

$oficios = [];  
while($row = $result->fetch_assoc()) {  
    $oficios[] = $row;  
}  

$conn->close();  

echo json_encode(['status' => 'success', 'count' => count($oficios), 'data' => $oficios]);