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
   "CREATE TABLE IF NOT EXISTS `notas_devolutivas` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `numero` varchar(20) NOT NULL,
        `apresentante` varchar(255) NOT NULL,
        `cpf_cnpj` varchar(20) DEFAULT NULL,
        `titulo` varchar(255) NOT NULL,
        `origem_titulo` varchar(200) NOT NULL,
        `corpo` text NOT NULL,
        `prazo_cumprimento` text DEFAULT NULL,
        `assinante` varchar(255) NOT NULL,
        `data` date NOT NULL,
        `tratamento` varchar(100) DEFAULT NULL,
        `protocolo` varchar(255) DEFAULT NULL,
        `data_protocolo` date DEFAULT NULL,
        `cargo_assinante` varchar(255) DEFAULT NULL,
        `dados_complementares` text DEFAULT NULL,
        `status` varchar(50) DEFAULT 'Pendente',
        `processo_referencia` varchar(100) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `data_atualizacao` timestamp NULL DEFAULT NULL COMMENT 'Data e hora da última atualização',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS logs_notas_devolutivas (  
        log_id INT AUTO_INCREMENT PRIMARY KEY,  
        nota_id INT NOT NULL,  
        numero VARCHAR(20) NOT NULL,  
        apresentante VARCHAR(255) DEFAULT NULL,  
        cpf_cnpj VARCHAR(20) DEFAULT NULL,  
        titulo VARCHAR(255) DEFAULT NULL,  
        origem_titulo VARCHAR(255) DEFAULT NULL,  
        corpo TEXT DEFAULT NULL,  
        prazo_cumprimento TEXT DEFAULT NULL,  
        assinante VARCHAR(100) DEFAULT NULL,  
        data DATE DEFAULT NULL,  
        tratamento VARCHAR(100) DEFAULT NULL,  
        protocolo VARCHAR(50) DEFAULT NULL,  
        data_protocolo DATE DEFAULT NULL,  
        cargo_assinante VARCHAR(100) DEFAULT NULL,  
        dados_complementares TEXT DEFAULT NULL,  
        status VARCHAR(20) DEFAULT NULL,  
        processo_referencia VARCHAR(50) DEFAULT NULL,  
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  
        usuario_log VARCHAR(100) DEFAULT NULL,  
        acao_log VARCHAR(50) NOT NULL,  
        data_log TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  
        INDEX (numero),  
        INDEX (nota_id)  
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

    "CREATE TABLE `logs_sistema` (  
        `id` int(11) NOT NULL AUTO_INCREMENT,  
        `usuario` varchar(100) NOT NULL,  
        `acao` text NOT NULL,  
        `tabela_afetada` varchar(100) NOT NULL,  
        `id_registro` varchar(50) NOT NULL,  
        `data_hora` datetime NOT NULL,  
        PRIMARY KEY (`id`),  
        KEY `idx_usuario` (`usuario`),  
        KEY `idx_tabela` (`tabela_afetada`),  
        KEY `idx_data` (`data_hora`)  
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
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
