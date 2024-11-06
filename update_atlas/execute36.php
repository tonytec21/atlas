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
    "INSERT INTO `manuais` (`id`, `titulo`, `descricao`, `categoria`, `caminho_video`, `data`, `status`, `ordem`) VALUES (18, 'Como visualizar o caixa, cadastrar saídas, depósitos e fazer o fechamento diário do caixa', 'Neste tutorial, você aprenderá a acessar e gerenciar o caixa no sistema Atlas. Vamos mostrar como visualizar o saldo, registrar saídas e depósitos de forma prática e segura, além de realizar o fechamento diário do caixa.', 'Controle de Caixa', 'anexos/Controle de Caixa/5. COMO VISUALIZAR O CAIXA, CADASTRAR SAÍDAS, DEPÓSITOS E FAZER O FECHAMENTO DO CAIXA.mp4', '2024-11-06 07:25:12', 'ativo', 1);"
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
