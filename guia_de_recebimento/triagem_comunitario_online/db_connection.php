<?php
// Dados de conexão para o banco remoto na Hostinger
$servername = "auth-db1079.hstgr.io"; // Host correto
$username = "u913401716_bcloud";     // Usuário do banco
$password = "@Rr6rh3264f9";               // Senha do banco
$dbname = "u913401716_atlas_online";   // Nome do banco

// Criar conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Configurar a conexão para usar UTF-8
if (!$conn->set_charset("utf8")) {
    echo "Erro ao configurar charset: " . $conn->error;
}
?>
