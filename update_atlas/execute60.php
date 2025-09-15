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

"CREATE TABLE IF NOT EXISTS indexador_casamento (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  termo             VARCHAR(20) NOT NULL,
  livro             VARCHAR(20) NOT NULL,
  folha             VARCHAR(20) NOT NULL,
  tipo_casamento    ENUM('CIVIL','RELIGIOSO') NOT NULL,
  data_registro     DATE NOT NULL,
  conjuge1_nome     VARCHAR(255) NOT NULL,
  conjuge1_nome_casado VARCHAR(255) NULL,
  conjuge1_sexo     CHAR(1) NOT NULL,
  conjuge2_nome     VARCHAR(255) NOT NULL,
  conjuge2_nome_casado VARCHAR(255) NULL,
  conjuge2_sexo     CHAR(1) NOT NULL,
  regime_bens       ENUM('COMUNHAO_PARCIAL','COMUNHAO_UNIVERSAL','PARTICIPACAO_FINAL_AQUESTOS','SEPARACAO_BENS','SEPARACAO_LEGAL_BENS','OUTROS','IGNORADO') NOT NULL,
  data_casamento    DATE NOT NULL,
  matricula         VARCHAR(32) DEFAULT NULL,
  funcionario       VARCHAR(100) DEFAULT NULL,
  status            ENUM('ativo','inativo') DEFAULT 'ativo',
  criado_em         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_busca (livro, folha, termo, data_registro),
  INDEX idx_conjuge1 (conjuge1_nome),
  INDEX idx_conjuge1_casado (conjuge1_nome_casado),
  INDEX idx_conjuge2 (conjuge2_nome),
  INDEX idx_conjuge2_casado (conjuge2_nome_casado),
  INDEX idx_tipo (tipo_casamento),
  INDEX idx_regime (regime_bens),
  UNIQUE KEY uq_indexador_casamento_matricula (matricula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

"CREATE TABLE IF NOT EXISTS indexador_casamento_anexos (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  id_casamento   INT NOT NULL,
  caminho_anexo  VARCHAR(500) NOT NULL,
  funcionario    VARCHAR(100) DEFAULT NULL,
  status         ENUM('ativo','inativo') DEFAULT 'ativo',
  data           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_casamento_anexo FOREIGN KEY (id_casamento) REFERENCES indexador_casamento(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
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
