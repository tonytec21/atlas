<?php
// Garante sessão ativa
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Usuário logado
$username = $_SESSION['username'] ?? '';

// Conexão
$connAtlas = @new mysqli("localhost", "root", "", "atlas");
if ($connAtlas && !$connAtlas->connect_error) {
    $connAtlas->set_charset("utf8mb4");

    // Consulta permissões
    $sql  = "SELECT nivel_de_acesso, acesso_adicional FROM funcionarios WHERE usuario = ?";
    $stmt = $connAtlas->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res  = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $connAtlas->close();

    $nivel_de_acesso  = $user['nivel_de_acesso']  ?? '';
    $acesso_adicional = $user['acesso_adicional'] ?? '';
} else {
    // Se não conseguir consultar, trata como sem permissão (seguro por padrão)
    $nivel_de_acesso  = '';
    $acesso_adicional = '';
}

// Normaliza CSV de acessos adicionais
$extras = array_filter(array_map('trim', explode(',', (string)$acesso_adicional)));

// Regra: administrador OU possui "Fluxo de Caixa"
$temControleContas = in_array('Fluxo de Caixa', $extras, true);
$temPermissao      = ($nivel_de_acesso === 'administrador') || $temControleContas;

if (!$temPermissao) {
    // Página de negação com visual moderno + SweetAlert2
    $redirect = 'os/index.php';
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Acesso negado</title>

        <!-- Tipografia moderna -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">

        <!-- SweetAlert2 (CDN com estilos prontos) -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <style>
            :root{
                --brand:#4F46E5; --brand-2:#6366F1;
                --bg:#f6f7fb; --text:#1f2937; --muted:#6b7280; --border:#e5e7eb;
            }
            @media (prefers-color-scheme: dark){
                :root{ --bg:#0f141a; --text:#e5e7eb; --muted:#9aa6b2; --border:#2a3440; }
            }
            html,body{height:100%;margin:0;background:var(--bg);color:var(--text);
                font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;}
            .screen{
                min-height:100%; display:flex; align-items:center; justify-content:center; padding:24px;
                background:
                  radial-gradient(1200px 600px at 10% -10%, rgba(79,70,229,.10), transparent 60%),
                  radial-gradient(1200px 600px at 110% 110%, rgba(99,102,241,.10), transparent 60%);
            }
            /* Fallback quando JS estiver desativado */
            .fallback{
                max-width:520px; width:100%; background:#fff; border:1px solid var(--border);
                border-radius:16px; padding:24px; box-shadow:0 18px 40px rgba(16,24,40,.12);
            }
            @media (prefers-color-scheme: dark){ .fallback{ background:#1a2129; } }
            .fallback h1{ margin:0 0 8px; font-weight:800; font-size:1.35rem; }
            .fallback p{ margin:0 0 16px; color:var(--muted); }
            .btn{
                display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:10px; text-decoration:none;
                background:linear-gradient(135deg, var(--brand), var(--brand-2)); color:#fff;
                box-shadow:0 12px 26px rgba(79,70,229,.28);
            }

            /* Ajuste visual do SweetAlert2 */
            .swal2-popup.swal2-modern{ border-radius:20px; padding:24px; }
            .swal2-title{ font-weight:800; letter-spacing:.2px; }
            .swal2-html-container{ color:var(--muted); font-size:.98rem; }
            .swal2-styled.swal2-confirm{
                background:linear-gradient(135deg, var(--brand), var(--brand-2))!important;
                border-radius:10px!important; box-shadow:0 8px 18px rgba(79,70,229,.28);
            }
        </style>
    </head>
    <body>
        <div class="screen">
            <noscript>
                <div class="fallback" role="alert" aria-live="assertive">
                    <h1>Acesso negado</h1>
                    <p>Você não tem permissão para acessar esta página.</p>
                    <a href="<?php echo htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>" class="btn">Ir para pesquisa</a>
                </div>
            </noscript>
        </div>

        <script>
            // Alerta elegante e responsivo
            Swal.fire({
                icon: 'error',
                title: 'Acesso negado',
                html: 'Você não tem permissão para acessar esta página.',
                confirmButtonText: 'Ir para pesquisa',
                allowOutsideClick: false,
                customClass: { popup: 'swal2-modern' }
            }).then(() => {
                window.location.href = <?php echo json_encode($redirect, JSON_UNESCAPED_SLASHES); ?>;
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>
