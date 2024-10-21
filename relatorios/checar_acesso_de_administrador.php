<?php
// Verificar o nível de acesso do usuário logado
$username = $_SESSION['username'];
$connAtlas = new mysqli("localhost", "root", "", "atlas");

// Consulta para verificar o nível de acesso e acesso adicional do usuário
$sql = "SELECT nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ?";
$stmt = $connAtlas->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$nivel_de_acesso = $user['nivel_de_acesso'];
$acesso_adicional = $user['acesso_adicional'];

// Verifica se o usuário tem "Controle de Contas a Pagar" no campo acesso_adicional
$temControleContas = in_array('Fluxo de Caixa', explode(',', $acesso_adicional));

// Se o usuário não for administrador e não tiver o acesso adicional "Controle de Contas a Pagar", redireciona
if ($nivel_de_acesso !== 'administrador' && !$temControleContas) {
    // Exibir mensagem de erro usando SweetAlert2
    echo "
    <script src='../script/sweetalert2.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Acesso negado!',
                text: 'Você não tem permissão para acessar esta página.',
                confirmButtonText: 'Ok'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'index.php';
                }
            });
        });
    </script>";
    exit;
}
?>
