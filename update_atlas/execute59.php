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

"INSERT INTO `tarefas_recorrentes` (`id`, `titulo`, `descricao`, `funcionario_id`, `recurrence_type`, `dia_semana`, `dia_mes`, `hora_execucao`, `inicio_vigencia`, `fim_vigencia`, `obrigatoria`, `proxima_execucao`, `created_at`) VALUES (1, 'Carga CRC', 'Envio de carga diária para a CRC', NULL, 'diaria', NULL, NULL, '17:30:00', '2025-07-28', NULL, 1, NULL, '2025-07-26 12:27:54');",
"INSERT INTO `tarefas_recorrentes` (`id`, `titulo`, `descricao`, `funcionario_id`, `recurrence_type`, `dia_semana`, `dia_mes`, `hora_execucao`, `inicio_vigencia`, `fim_vigencia`, `obrigatoria`, `proxima_execucao`, `created_at`) VALUES (2, 'Carga SIRC', 'Envio de carga diária para o SIRC', NULL, 'diaria', NULL, NULL, '17:30:00', '2025-07-28', NULL, 1, NULL, '2025-07-26 12:28:38');",
"INSERT INTO `tarefas_recorrentes` (`id`, `titulo`, `descricao`, `funcionario_id`, `recurrence_type`, `dia_semana`, `dia_mes`, `hora_execucao`, `inicio_vigencia`, `fim_vigencia`, `obrigatoria`, `proxima_execucao`, `created_at`) VALUES (3, 'Carga IBGE', 'Envio de carga do último trimestre para o IBGE', NULL, 'trimestral', NULL, NULL, '08:00:00', '2025-10-01', NULL, 1, NULL, '2025-07-26 12:30:45');",
"INSERT INTO `tarefas_recorrentes` (`id`, `titulo`, `descricao`, `funcionario_id`, `recurrence_type`, `dia_semana`, `dia_mes`, `hora_execucao`, `inicio_vigencia`, `fim_vigencia`, `obrigatoria`, `proxima_execucao`, `created_at`) VALUES (4, 'Carga CENSEC', 'Envio de carga da última quinzena para a CENSEC', NULL, 'quinzenal', NULL, NULL, '08:00:00', '2025-08-01', NULL, 1, NULL, '2025-07-26 12:31:23');",
"INSERT INTO `tarefas_recorrentes` (`id`, `titulo`, `descricao`, `funcionario_id`, `recurrence_type`, `dia_semana`, `dia_mes`, `hora_execucao`, `inicio_vigencia`, `fim_vigencia`, `obrigatoria`, `proxima_execucao`, `created_at`) VALUES (5, 'Carga INFODIP', 'Envio de carga para o INFODIP', NULL, 'mensal', NULL, 1, '08:00:00', '2025-08-01', NULL, 1, NULL, '2025-07-26 12:32:18');",
"INSERT INTO `tarefas_recorrentes` (`id`, `titulo`, `descricao`, `funcionario_id`, `recurrence_type`, `dia_semana`, `dia_mes`, `hora_execucao`, `inicio_vigencia`, `fim_vigencia`, `obrigatoria`, `proxima_execucao`, `created_at`) VALUES (6, 'Carga CTP - CENSEC', 'Envio da carga CTP da CENSEC', NULL, 'mensal', NULL, 1, '08:30:00', '2025-08-01', NULL, 1, NULL, '2025-07-26 12:33:19');",
"INSERT INTO `tarefas_recorrentes` (`id`, `titulo`, `descricao`, `funcionario_id`, `recurrence_type`, `dia_semana`, `dia_mes`, `hora_execucao`, `inicio_vigencia`, `fim_vigencia`, `obrigatoria`, `proxima_execucao`, `created_at`) VALUES (7, 'Boleto FERJ', 'Pagamento das remessas do FERJ e FERC', NULL, 'semanal', 1, NULL, '08:00:00', '2025-07-28', NULL, 1, NULL, '2025-07-26 12:34:20');"
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
