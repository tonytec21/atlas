<?php  
include(__DIR__ . '/db_connection.php');  

// Busca todos os livros distintos  
$query = "SELECT DISTINCT livro FROM indexador_nascimento WHERE status = 'ativo' ORDER BY livro ASC";  
$result = $conn->query($query);  

$livros = [];  
while ($row = $result->fetch_assoc()) {  
    $livros[] = $row['livro'];  
}  

echo json_encode($livros);  

$conn->close();