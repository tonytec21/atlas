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
   "INSERT INTO `manuais` (`id`, `titulo`, `descricao`, `categoria`, `caminho_video`, `data`, `status`, `ordem`) VALUES (19, 'Como Criar Modelos de Ordens de Serviço (O.S) de Forma Rápida e Eficiente', 'Aprenda como criar modelos de ordens de serviço (OS) de maneira prática e organizada! Neste vídeo, você verá como configurar e personalizar modelos de OS para agilizar o processo de criação de ordens de serviço. Descubra como adicionar atos, definir valores e facilitar o seu fluxo de trabalho. Otimize seu tempo e padronize seus orçamentos!', 'Ordens de Serviço', 'anexos/Ordens de Serviço/COMO CRIAR MODELOS DE O.S - 720p.mp4', '2025-03-01 13:28:35', 'ativo', 5);",
   "INSERT INTO `manuais` (`id`, `titulo`, `descricao`, `categoria`, `caminho_video`, `data`, `status`, `ordem`) VALUES (20, 'Como Criar uma Ordem de Serviço (O.S) Usando Modelos Cadastrados', 'Descubra como criar uma Ordem de Serviço (OS) de maneira rápida e sem complicações, utilizando modelos previamente cadastrados! Neste vídeo, você verá como selecionar um modelo pronto e gerar automaticamente uma OS com todos os atos e valores já configurados. Acelere seu processo e padronize suas Ordens de Serviço!', 'Ordens de Serviço', 'anexos/Ordens de Serviço/COMO CRIAR UMA O.S USANDO MODELOS CADASTRADOS - 720p.mp4', '2025-03-01 13:29:32', 'ativo', 6);"
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
