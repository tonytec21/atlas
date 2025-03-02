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
   "CREATE TABLE IF NOT EXISTS `indexador_obito` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `termo` varchar(50) DEFAULT NULL,
    `livro` varchar(50) DEFAULT NULL,
    `folha` varchar(20) DEFAULT NULL,
    `data_registro` date DEFAULT NULL,
    `data_nascimento` date DEFAULT NULL,
    `data_obito` date DEFAULT NULL,
    `hora_obito` time DEFAULT NULL,
    `nome_registrado` varchar(255) DEFAULT NULL,
    `nome_pai` varchar(255) DEFAULT NULL,
    `nome_mae` varchar(255) DEFAULT NULL,
    `data_cadastro` timestamp NOT NULL DEFAULT current_timestamp(),
    `funcionario` varchar(100) DEFAULT NULL,
    `status` char(1) DEFAULT 'A',
    `matricula` varchar(50) DEFAULT NULL,
    `cidade_endereco` varchar(100) DEFAULT NULL,
    `ibge_cidade_endereco` varchar(7) DEFAULT NULL,
    `cidade_obito` varchar(100) DEFAULT NULL,
    `ibge_cidade_obito` varchar(7) DEFAULT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `indexador_obito_anexos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `id_obito` int(11) NOT NULL,
    `caminho_anexo` varchar(255) NOT NULL,
    `data` timestamp NOT NULL DEFAULT current_timestamp(),
    `funcionario` varchar(100) DEFAULT NULL,
    `status` char(1) DEFAULT 'A',
    PRIMARY KEY (`id`),
    KEY `id_obito` (`id_obito`),
    CONSTRAINT `indexador_obito_anexos_ibfk_1` FOREIGN KEY (`id_obito`) REFERENCES `indexador_obito` (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
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
