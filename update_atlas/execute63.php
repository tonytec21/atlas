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

"CREATE TABLE IF NOT EXISTS equipes (  
    id INT AUTO_INCREMENT PRIMARY KEY,  
    nome VARCHAR(120) NOT NULL,  
    descricao VARCHAR(500) NULL,  
    ativa TINYINT(1) NOT NULL DEFAULT 1,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,  
    UNIQUE KEY uq_equipe_nome (nome),  
    INDEX idx_ativa (ativa)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');",

  "CREATE TABLE IF NOT EXISTS equipe_membros (  
    id INT AUTO_INCREMENT PRIMARY KEY,  
    equipe_id INT NOT NULL,  
    funcionario_id INT NOT NULL,  
    papel VARCHAR(60) NULL,  
    ordem INT NOT NULL DEFAULT 1,  
    ativo TINYINT(1) NOT NULL DEFAULT 1,  
    carga_maxima_diaria INT NULL,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,  
    CONSTRAINT fk_membro_equipe FOREIGN KEY (equipe_id) REFERENCES equipes(id) ON DELETE CASCADE,  
    CONSTRAINT fk_membro_func FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE RESTRICT,  
    UNIQUE KEY uq_equipe_func (equipe_id, funcionario_id),  
    INDEX idx_equipe_ativo (equipe_id, ativo),  
    INDEX idx_ordem (ordem)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');",  

  "CREATE TABLE IF NOT EXISTS equipe_regras (  
    id INT AUTO_INCREMENT PRIMARY KEY,  
    equipe_id INT NOT NULL,  
    atribuicao VARCHAR(50) NOT NULL,  
    tipo VARCHAR(80) NOT NULL,  
    prioridade INT NOT NULL DEFAULT 10,  
    ativa TINYINT(1) NOT NULL DEFAULT 1,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,  
    CONSTRAINT fk_regra_equipe FOREIGN KEY (equipe_id) REFERENCES equipes(id) ON DELETE CASCADE,  
    INDEX idx_match (atribuicao, tipo, ativa, prioridade),  
    INDEX idx_equipe (equipe_id)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');",

"CREATE TABLE IF NOT EXISTS tarefas_pedido (  
    id BIGINT AUTO_INCREMENT PRIMARY KEY,  
    pedido_id INT NOT NULL,  
    equipe_id INT NOT NULL,  
    funcionario_id INT NULL,  
    status ENUM('pendente','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'pendente',  
    observacao VARCHAR(500) NULL,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,  
    CONSTRAINT fk_tarefa_equipe FOREIGN KEY (equipe_id) REFERENCES equipes(id) ON DELETE RESTRICT,  
    CONSTRAINT fk_tarefa_func FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE SET NULL,  
    INDEX idx_pedido (pedido_id),  
    INDEX idx_func_status (funcionario_id, status),  
    INDEX idx_equipe (equipe_id),  
    INDEX idx_status (status)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');",  

"CREATE TABLE IF NOT EXISTS tarefas_pedido_log (  
    id BIGINT AUTO_INCREMENT PRIMARY KEY,  
    tarefa_id BIGINT NOT NULL,  
    acao ENUM('status','reatribuicao','observacao') NOT NULL,  
    de_valor VARCHAR(255) NULL,  
    para_valor VARCHAR(255) NULL,  
    observacao VARCHAR(500) NULL,  
    usuario VARCHAR(120) NOT NULL,  
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  
    CONSTRAINT fk_log_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas_pedido(id) ON DELETE CASCADE,  
    INDEX idx_tarefa (tarefa_id),  
    INDEX idx_acao (acao)  
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');"
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
