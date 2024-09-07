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
    "INSERT INTO `manuais` (`id`, `titulo`, `descricao`, `categoria`, `caminho_video`, `data`, `status`, `ordem`) VALUES (8, 'Conhecendo a ferramenta de provimentos e resoluções', 'Neste vídeo, você será apresentado à ferramenta de Provimentos e Resoluções no Sistema Atlas. Aprenda como acessar, consultar e gerenciar provimentos e resoluções de maneira simples e eficiente. Exploraremos as principais funcionalidades da ferramenta, como a busca por número, descrição, data e origem dos provimentos, além de como visualizar os documentos associados. Este vídeo é ideal para quem está começando a utilizar a ferramenta e deseja entender como ela pode facilitar a consulta e o controle de provimentos e resoluções no dia a dia.', 'Provimentos e Resoluções', 'anexos/Provimentos e Resoluções/CONHECENDO A FERRAMENTA DE PROVIMENTOS E RESOLUÇÕES.mp4', '2024-09-07 14:10:55', 'ativo', 1);"
    "INSERT INTO `manuais` (`id`, `titulo`, `descricao`, `categoria`, `caminho_video`, `data`, `status`, `ordem`) VALUES (9, 'Como localizar provimentos e resoluções pelo conteúdo', 'Neste vídeo, você aprenderá como localizar provimentos e resoluções pelo conteúdo no Sistema Atlas. Descubra como realizar buscas avançadas com base em termos específicos dentro dos documentos, facilitando a localização de informações importantes e pertinentes ao seu trabalho. Este tutorial é ideal para quem deseja otimizar a pesquisa por meio de palavras-chave e obter resultados precisos diretamente do conteúdo dos provimentos e resoluções. Assista e veja como essa funcionalidade pode transformar suas consultas e agilizar seu fluxo de trabalho.', 'Provimentos e Resoluções', 'anexos/Provimentos e Resoluções/COMO LOCALIZAR PROVIMENTOS E RESOLUÇÕES PELO CONTEÚDO.mp4', '2024-09-07 14:39:51', 'ativo', 2);"
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
