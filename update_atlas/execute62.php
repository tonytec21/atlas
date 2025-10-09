<?php
// Inicia o buffer de saída
ob_start();

// Define o max_allowed_packet para 2GB
$conn->query("SET GLOBAL max_allowed_packet=2147483648");

// Função para criar a tabela se ela não existir
function criarTabelaSeNecessario($conn, $queryCriarTabela) {
    if ($conn->query($queryCriarTabela) === TRUE) {
        echo "Tabela criada ou verificada com sucesso.<br>";
    } else {
        echo "Erro ao criar/verificar tabela: " . $conn->error . "<br>";
    }
}

$tabelas = [

"CREATE TABLE IF NOT EXISTS relatorios_analiticos (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                seq_linha        INT NULL,
                cartorio         VARCHAR(255) NULL,
                numero_selo      VARCHAR(80) NOT NULL,
                ato              VARCHAR(255) NULL,
                usuario          VARCHAR(255) NULL,
                isento           TINYINT(1) NOT NULL DEFAULT 0,
                cancelado        TINYINT(1) NOT NULL DEFAULT 0,
                diferido         TINYINT(1) NOT NULL DEFAULT 0,
                selagem          DATE NULL,                 
                operacao         DATETIME NULL,             
                tipo             VARCHAR(120) NULL,
                emolumentos      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                ferj             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                fadep            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                ferc             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                femp             DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                selo_valor       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                total            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                arquivo_origem   VARCHAR(255) NULL,
                uploaded_by      VARCHAR(120) NULL,
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME NULL,
                UNIQUE KEY uq_numero_selo (numero_selo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",   

            "ALTER TABLE relatorios_analiticos MODIFY COLUMN selagem DATE NULL;",
            "ALTER TABLE relatorios_analiticos MODIFY COLUMN operacao DATETIME NULL;",
            "ALTER TABLE `relatorios_analiticos` COLLATE='utf8mb4_unicode_ci';"
];

// Executa a criação de todas as tabelas
foreach ($tabelas as $query) {
    criarTabelaSeNecessario($conn, $query);
}

echo "Execute concluído com sucesso.<br>";

// Captura e armazena a saída gerada
$output = ob_get_clean();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="style/css/toastr.min.css">
</head>
<body>

    <script src="script/jquery-3.6.0.min.js"></script>
    <script src="script/toastr.min.js"></script>

    <script>
        // Configuração básica do Toastr
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": true,
            "progressBar": true,
            "positionClass": "toast-bottom-left",
            "preventDuplicates": true,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };

        // Função para verificar as atualizações
        function verificarAtualizacoes() {
            // Exibe a mensagem inicial de verificação
            toastr.info('Verificando atualizações...');

            // Simula o retorno da mensagem de verificação após 2 segundos
            setTimeout(() => {
                const mensagem = "<?php echo $mensagem; ?>";
                if (mensagem.includes('sucesso')) {
                    toastr.success(mensagem);
                } else if (mensagem.includes('Erro')) {
                    toastr.error(mensagem);
                } else {
                    toastr.info(mensagem);
                }
            }, 2000);
        }

        // Chama a função de verificação ao carregar a página
        window.onload = verificarAtualizacoes;
    </script>
</body>
</html>
