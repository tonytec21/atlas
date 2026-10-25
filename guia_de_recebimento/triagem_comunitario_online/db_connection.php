<?php
/* atlas-auditoria */ if (is_file(__DIR__ . '/../../auditoria/hook.php')) { include_once __DIR__ . '/../../auditoria/hook.php'; }
$servername = "auth-db1079.hstgr.io";
$username = "u913401716_bcloud";
$password = "@Rr6rh3264f9";
$dbname = "u913401716_atlas_online";

// Criar conexão
$conn = new mysqli($servername, $username, $password, $dbname); /* atlas-auditoria */ if (is_file(__DIR__ . '/../../auditoria/hook.php')) { include_once __DIR__ . '/../../auditoria/hook.php'; atlas_aud_conexao($conn); }

// Verificar conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Configurar a conexão para usar UTF-8
if (!$conn->set_charset("utf8")) {
    echo "Erro ao configurar charset: " . $conn->error;
}
?>
