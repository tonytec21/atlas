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

"CREATE TABLE funcionarios_backup AS SELECT * FROM funcionarios;",
"ALTER TABLE funcionarios ENGINE=InnoDB;",
"CREATE TABLE tarefas_recorrentes (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    titulo            VARCHAR(255)  NOT NULL,
    descricao         TEXT,
    funcionario_id    INT           NOT NULL,            
    recurrence_type   ENUM('diaria','semanal','quinzenal','mensal','trimestral') NOT NULL,
    dia_semana        TINYINT NULL,      
    dia_mes           TINYINT NULL,      
    hora_execucao     TIME     NOT NULL, 
    inicio_vigencia   DATE     NOT NULL,
    fim_vigencia      DATE     NULL,
    obrigatoria       TINYINT(1) DEFAULT 1,
    proxima_execucao  DATETIME NULL,    
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_funcionario_recorrente
      FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id)
);",

"CREATE TABLE tarefas_recorrentes_exec (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    tarefa_id          INT           NOT NULL,
    data_prevista      DATETIME      NOT NULL,
    status             ENUM('cumprida','nao_cumprida') NOT NULL,
    justificativa      TEXT          NULL,
    data_cumprimento   DATETIME      NULL,             
    usuario_responsavel VARCHAR(255) NOT NULL,
    created_at         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tarefa_rec_exec
      FOREIGN KEY (tarefa_id) REFERENCES tarefas_recorrentes(id)
);",

"ALTER TABLE tarefas_recorrentes MODIFY funcionario_id INT NULL;"

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
