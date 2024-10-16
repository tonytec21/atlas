<?php
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

// Identifica o número da atualização atual a partir do JSON
$atualizacaoAtual = $jsonData["atualizacao"];
$mensagem = '';

// Loop para executar todas as atualizações em sequência
while (true) {
    // Define a próxima atualização como a sequência do número atual
    $proximaAtualizacao = $atualizacaoAtual + 1;
    $arquivoExecute = $updateDir . "execute{$proximaAtualizacao}.php";

    // Verifica se o arquivo de execução da próxima atualização existe
    if (file_exists($arquivoExecute)) {
        try {
            include $arquivoExecute; // Executa a atualização

            // Atualiza o número da versão no JSON
            $atualizacaoAtual = $proximaAtualizacao;
            $jsonData["atualizacao"] = $atualizacaoAtual;

            if (file_put_contents($jsonFile, json_encode($jsonData)) === false) {
                $mensagem .= "Erro ao atualizar o arquivo de versão para a atualização {$proximaAtualizacao}.<br>";
                break; // Encerra o loop em caso de erro ao salvar o JSON
            } else {
                $mensagem .= "Atualização {$proximaAtualizacao} aplicada com sucesso.<br>";
            }
        } catch (Exception $e) {
            $mensagem .= "Erro durante a execução da atualização {$proximaAtualizacao}: " . $e->getMessage() . "<br>";
            break; // Encerra o loop em caso de erro durante a execução
        }
    } else {
        // Se o próximo arquivo de atualização não existir, encerra o loop
        $mensagem .= "Sistema atualizado. Nenhuma atualização pendente.<br>";
        break;
    }
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
    <style>
        .toast {
            background-color: #007bff !important;
            color: white !important;
            border-radius: 5px !important;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1) !important;
        }

        .toast-progress {
            background-color: #28a745 !important;
        }
    </style>
</head>
<body>

    <script src="script/jquery-3.6.0.min.js"></script>
    <script src="script/toastr.min.js"></script>

    <script>
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

        function verificarAtualizacoes() {
            toastr.info('Verificando atualizações...');

            setTimeout(() => {
                const mensagem = `<?php echo nl2br($mensagem); ?>`;
                if (mensagem.includes('sucesso')) {
                    toastr.success(mensagem);
                } else if (mensagem.includes('Erro')) {
                    toastr.error(mensagem);
                } else {
                    toastr.info(mensagem);
                }
            }, 2000);
        }

        window.onload = verificarAtualizacoes;
    </script>
</body>
</html>
