<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dataFile = 'data.json';
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (file_exists($dataFile)) {
        $data = json_decode(file_get_contents($dataFile), true);
        
        foreach ($data as $user) {
            if ($user['username'] === $username && base64_decode($user['password']) === $password) {
                // Login bem-sucedido
                $_SESSION['username'] = $username;
                header('Location: index.php');
                exit;
            }
        }
    }
    // Login falhou
    header('Location: login.php?error=1');
    exit;
}
?>
