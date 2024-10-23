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
    "CREATE TABLE IF NOT EXISTS `triagem_comunitario` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `cidade` varchar(100) NOT NULL,
    `n_protocolo` varchar(50) NOT NULL,
    `nome_do_noivo` varchar(100) NOT NULL,
    `novo_nome_do_noivo` varchar(100) DEFAULT NULL,
    `noivo_menor` tinyint(1) DEFAULT 0,
    `nome_da_noiva` varchar(100) NOT NULL,
    `novo_nome_da_noiva` varchar(100) DEFAULT NULL,
    `noiva_menor` tinyint(1) DEFAULT 0,
    `pedido_deferido` tinyint(1) DEFAULT 0,
    `cadastro_efetivado` tinyint(1) DEFAULT 0,
    `processo_concluido` tinyint(1) DEFAULT 0,
    `habilitacao_concluida` tinyint(1) DEFAULT 0,
    `numero_proclamas` int(11) DEFAULT 0,
    `caminho_anexo` text DEFAULT NULL,
    `funcionario` varchar(100) NOT NULL,
    `data` datetime DEFAULT current_timestamp(),
    `status` varchar(50) DEFAULT 'ativo',
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"    
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
