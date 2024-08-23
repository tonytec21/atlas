<?php
// Executa o comando git pull no mesmo diretório onde o arquivo está localizado
$output = shell_exec('git pull 2>&1');

// Verifica o resultado da execução
if (strpos($output, 'Already up to date.') !== false) {
    $mensagem = "Sistema atualizado. Nenhuma atualização pendente.";
} elseif (strpos($output, 'Updating') !== false) {
    $mensagem = "Atualização do código aplicada com sucesso.";
} else {
    $mensagem = "Erro ao executar a atualização via git: " . $output;
}

// Inclui a conexão com o banco de dados
include_once 'db_connection_atualizacao.php';

// Caminho do diretório onde estão o arquivo JSON e os scripts de atualização
$updateDir = 'update_atlas/';

// Caminho do arquivo JSON
$jsonFile = $updateDir . 'atualizacao.json';

// Verifica se o arquivo JSON existe e lê os dados, ou cria um novo se não existir
if (file_exists($jsonFile)) {
    $jsonData = json_decode(file_get_contents($jsonFile), true);
} else {
    // Cria o arquivo JSON com a estrutura inicial
    $jsonData = ["atualizacao" => 0];
    file_put_contents($jsonFile, json_encode($jsonData));
}

// Identifica o número da atualização atual e calcula a próxima atualização
$atualizacaoAtual = $jsonData["atualizacao"];
$proximaAtualizacao = $atualizacaoAtual + 1;

// Nome do arquivo de execução da próxima atualização
$arquivoExecute = $updateDir . "execute{$proximaAtualizacao}.php";

// Verifica se o arquivo de execução da próxima atualização existe
if (file_exists($arquivoExecute)) {
    // Inclui e executa o arquivo de atualização correspondente
    include $arquivoExecute;

    // Atualiza o número da versão no arquivo JSON
    $jsonData["atualizacao"] = $proximaAtualizacao;
    if (file_put_contents($jsonFile, json_encode($jsonData)) === false) {
        $mensagem = "Erro ao atualizar o arquivo de versão.";
    } else {
        $mensagem = "Atualização $proximaAtualizacao aplicada com sucesso.";
    }
} else {
    $mensagem = "Sistema atualizado. Nenhuma atualização pendente.";
}

// Fecha a conexão com o banco de dados
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="style/img/favicon.png" type="image/png">
    <link rel="stylesheet" href="style/css/toastr.min.css">
    <title>Sistema de Atualização</title>
    <style>
        .toast {
            background-color: #007bff !important; /* Cor de fundo */
            color: white !important; /* Cor do texto */
            border-radius: 5px !important;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1) !important;
        }

        .toast-progress {
            background-color: #28a745 !important; /* Cor da barra de progresso */
        }
    </style>
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
