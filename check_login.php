<?php
session_start();
include(__DIR__ . '/db_connection.php');

function getUserIpAddr() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        // IP de proxy compartilhado
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // IP passado por proxies
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        // IP remoto
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // Corrige o caso onde o IP retornado é "::1" (IPv6 para localhost)
    if ($ip == '::1') {
        $ip = '127.0.0.1';
    }
    return $ip;
}

function saveAccessLog($username, $nomeCompleto) {
    $ip = getUserIpAddr();
    
    // Define o fuso horário para o local do servidor
    date_default_timezone_set('America/Sao_Paulo');
    
    $dataHora = date('Y-m-d H:i:s');

    // Conexão com o banco de dados "atlas"
    $conn = new mysqli("localhost", "root", "", "atlas");
    if ($conn->connect_error) {
        die("Falha na conexão com o banco atlas: " . $conn->connect_error);
    }

    // Preparar a consulta para inserir o log de acesso
    $stmt = $conn->prepare("INSERT INTO logs_de_acesso (usuario, nome_completo, ip, data_hora) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        die("Erro na preparação da consulta: " . $conn->error);
    }
    $stmt->bind_param("ssss", $username, $nomeCompleto, $ip, $dataHora);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Conexão com o banco de dados "atlas"
    $conn = new mysqli("localhost", "root", "", "atlas");
    if ($conn->connect_error) {
        die("Falha na conexão com o banco atlas: " . $conn->connect_error);
    }

    // Preparar a consulta para verificar as credenciais
    $stmt = $conn->prepare("SELECT * FROM funcionarios WHERE usuario = ?");
    if (!$stmt) {
        die("Erro na preparação da consulta: " . $conn->error);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (base64_decode($user['senha']) === $password) {
            // Verificar se o usuário está ativo
            if ($user['status'] !== 'ativo') {
                header('Location: login.php?error=2'); // Usuário inativo
                exit;
            }
            
            // Login bem-sucedido
            $_SESSION['username'] = $username;
            $_SESSION['nome_completo'] = $user['nome_completo'];
            $_SESSION['cargo'] = $user['cargo'];
            $_SESSION['nivel_de_acesso'] = $user['nivel_de_acesso'];
            $_SESSION['status'] = $user['status'];

            // Salvar log de acesso
            saveAccessLog($username, $user['nome_completo']);

            header('Location: index.php');
            exit;
        }
    }

    // Login falhou
    header('Location: login.php?error=1');
    exit;
}
?>
