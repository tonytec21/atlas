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
    "INSERT INTO `manuais` (`id`, `titulo`, `descricao`, `categoria`, `caminho_video`, `data`, `status`, `ordem`) VALUES (14, 'Como criar ordens de serviço', 'Neste vídeo, você aprenderá como criar ordens de serviço de forma prática e eficiente. Vamos explorar o preenchimento correto de campos essenciais, como a inclusão de atos.', 'Ordens de Serviço', 'anexos/Ordens de Serviço/01. COMO CRIAR ORDENS DE SERVIÇO.mp4', '2024-10-28 15:38:07', 'ativo', 1);",
    "INSERT INTO `manuais` (`id`, `titulo`, `descricao`, `categoria`, `caminho_video`, `data`, `status`, `ordem`) VALUES (15, 'Como adicionar pagamentos nas ordens de serviço', 'Neste vídeo, você aprenderá como adicionar pagamentos nas ordens de serviço de forma simples e eficaz. O tutorial mostra como preencher corretamente as informações financeiras, vinculando os valores aos atos e serviços realizados.', 'Ordens de Serviço', 'anexos/Ordens de Serviço/02. COMO ADICIONAR PAGAMENTOS NAS ORDENS DE SERVIÇO.mp4', '2024-10-28 15:40:32', 'ativo', 2);",
    "INSERT INTO `manuais` (`id`, `titulo`, `descricao`, `categoria`, `caminho_video`, `data`, `status`, `ordem`) VALUES (16, 'Como liquidar as ordens de serviço', 'Neste vídeo, você aprenderá como liquidar os atos das ordens de serviço, seja de forma total ou parcial. O tutorial detalha o processo de conclusão dos atos registrados na ordem, atualizando o status e garantindo que cada etapa seja corretamente finalizada no sistema. Além disso, é mostrado como realizar liquidações parciais, mantendo o controle das pendências e assegurando que todas as informações estejam organizadas e atualizadas.', 'Ordens de Serviço', 'anexos/Ordens de Serviço/03. COMO LIQUIDAR AS ORDENS DE SERVIÇO.mp4', '2024-10-28 15:46:02', 'ativo', 4);",
    "INSERT INTO `manuais` (`id`, `titulo`, `descricao`, `categoria`, `caminho_video`, `data`, `status`, `ordem`) VALUES (17, 'Como editar ordens de serviço', 'Neste vídeo, você aprenderá como editar ordens de serviço de maneira prática e eficiente. O tutorial mostra como alterar informações importantes, como atos cadastrados, descrições e outros detalhes da ordem, garantindo que as atualizações sejam refletidas corretamente no sistema.', 'Ordens de Serviço', 'anexos/Ordens de Serviço/04. COMO EDITAR ORDENS DE SERVIÇO.mp4', '2024-10-28 15:48:17', 'ativo', 3);"
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
