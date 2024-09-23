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
    "INSERT INTO `manuais` (`id`, `titulo`, `descricao`, `categoria`, `caminho_video`, `data`, `status`, `ordem`) VALUES (10, 'Como gerar guia de recebimento', 'Neste vídeo tutorial, você aprenderá o passo a passo para gerar uma Guia de Recebimento no sistema Atlas. Vamos mostrar como preencher os campos necessários, selecionar as opções corretas e salvar os dados de forma segura. Além disso, você verá como visualizar e imprimir a guia para formalizar o recebimento dos documentos. Acompanhe o vídeo para entender o processo completo e simplificar suas atividades diárias no sistema Atlas!', 'Guia de Recebimento', 'anexos/Guia de Recebimento/COMO GERAR GUIA DE RECEBIMENTO.mp4', '2024-09-21 19:28:18', 'ativo', 1);",
    "INSERT INTO `manuais` (`id`, `titulo`, `descricao`, `categoria`, `caminho_video`, `data`, `status`, `ordem`) VALUES (11, 'Como criar tarefa a partir da guia de recebimento', 'Neste tutorial, você aprenderá como criar tarefas no sistema Atlas a partir das Guias de Recebimento. O vídeo mostra o processo completo de vinculação de uma guia a uma nova tarefa, permitindo o acompanhamento de ações necessárias relacionadas ao recebimento. Você verá como preencher os campos da tarefa, definir prazos e atribuir responsáveis, garantindo que o fluxo de trabalho seja bem gerenciado.', 'Guia de Recebimento', 'anexos/Guia de Recebimento/COMO CRIAR TAREFAS A PARTIR DAS GUIAS DE RECEBIMENTO.mp4', '2024-09-21 20:22:32', 'ativo', 2);"
];

// Executa a criação de todas as tabelas
foreach ($tabelas as $query) {
    criarTabelaSeNecessario($conn, $query);
}

echo "Execute1 concluído com sucesso.<br>";

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
