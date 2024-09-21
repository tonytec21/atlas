<?php
// Verificar o nível de acesso do usuário logado
$username = $_SESSION['username'];
$connAtlas = new mysqli("localhost", "root", "", "atlas");

// Consulta para verificar o nível de acesso do usuário
$sql = "SELECT nivel_de_acesso FROM funcionarios WHERE usuario = ?";
$stmt = $connAtlas->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$nivel_de_acesso = $user['nivel_de_acesso'];

// Se o usuário não for administrador, redireciona para a página de erro ou outra página
if ($nivel_de_acesso !== 'administrador') {
    // Exibir mensagem de erro ou redirecionar
    echo "<script>alert('Você não tem permissão para acessar esta página!'); window.location.href = '../index.php';</script>";
    exit;
}