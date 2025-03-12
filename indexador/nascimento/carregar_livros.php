<?php  
include(__DIR__ . '/db_connection.php');  

// Busca todos os livros distintos, convertendo para número para remover zeros à esquerda  
$query = "SELECT DISTINCT CAST(livro AS UNSIGNED) AS livro_num FROM indexador_nascimento WHERE status = 'ativo' ORDER BY livro_num ASC";  
$result = $conn->query($query);  

$livros = [];  
while ($row = $result->fetch_assoc()) {  
    $livros[] = $row['livro_num'];  
}  

echo json_encode($livros);  

$conn->close();