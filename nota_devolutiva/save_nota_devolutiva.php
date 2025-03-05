<?php  
include(__DIR__ . '/session_check.php');  
checkSession();  
include(__DIR__ . '/db_connection2.php');  

if ($_SERVER['REQUEST_METHOD'] === 'POST') {  
    $numero = $_POST['numero'];  
    $tratamento = $_POST['tratamento'] ?? '';  
    $apresentante = $_POST['apresentante'];  
    $protocolo = $_POST['protocolo'];  
    $data_protocolo = $_POST['data_protocolo'] ?? null;  
    $titulo = $_POST['titulo'];  
    $origem_titulo = $_POST['origem_titulo'] ?? '';  
    $cpf_cnpj = $_POST['cpf_cnpj'] ?? '';  
    $corpo = $_POST['corpo'];  
    $prazo_cumprimento = $_POST['prazo_cumprimento'] ?? '';  
    $assinante = $_POST['assinante'];  
    $cargo_assinante = $_POST['cargo_assinante'];  
    $data = $_POST['data'];  
    $dados_complementares = $_POST['dados_complementares'];  
    $processo_referencia = $_POST['processo_referencia'] ?? '';  

    // Verificar se as colunas existem na tabela  
    $checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'prazo_cumprimento'");  
    if($checkColumns->num_rows == 0) {  
        $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN prazo_cumprimento TEXT AFTER corpo");  
    }  

    $checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'data_protocolo'");  
    if($checkColumns->num_rows == 0) {  
        $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN data_protocolo DATE AFTER protocolo");  
    }  

    $checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'origem_titulo'");  
    if($checkColumns->num_rows == 0) {  
        $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN origem_titulo VARCHAR(200) AFTER titulo");  
    }  

    $checkColumns = $conn->query("SHOW COLUMNS FROM notas_devolutivas LIKE 'cpf_cnpj'");  
    if($checkColumns->num_rows == 0) {  
        $conn->query("ALTER TABLE notas_devolutivas ADD COLUMN cpf_cnpj VARCHAR(20) AFTER apresentante");  
    }  

    $stmt = $conn->prepare("UPDATE notas_devolutivas SET   
        tratamento = ?,   
        apresentante = ?,   
        cpf_cnpj = ?,  
        protocolo = ?,   
        data_protocolo = ?,  
        titulo = ?,   
        origem_titulo = ?,  
        corpo = ?,   
        prazo_cumprimento = ?,  
        assinante = ?,   
        cargo_assinante = ?,   
        data = ?,   
        dados_complementares = ?,   
        processo_referencia = ?   
        WHERE numero = ?");  
    
    $stmt->bind_param("sssssssssssssss",   
        $tratamento,   
        $apresentante,   
        $cpf_cnpj,  
        $protocolo,   
        $data_protocolo,  
        $titulo,   
        $origem_titulo,  
        $corpo,   
        $prazo_cumprimento,  
        $assinante,   
        $cargo_assinante,   
        $data,   
        $dados_complementares,   
        $processo_referencia,   
        $numero);  

    if ($stmt->execute()) {  
        echo "<script>alert('Nota devolutiva atualizada com sucesso!'); window.location.href = 'index.php';</script>";  
    } else {  
        echo "<script>alert('Erro ao atualizar a nota devolutiva.'); window.location.href = 'index.php';</script>";  
    }  

    $stmt->close();  
    $conn->close();  
}  
?>