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

"CREATE TABLE IF NOT EXISTS `avaliacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manual_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `classificacao` int(11) NOT NULL,
  `comentario` text DEFAULT NULL,
  `data_criacao` datetime NOT NULL,
  `data_atualizacao` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `manual_id` (`manual_id`,`usuario_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `avaliacoes_ibfk_1` FOREIGN KEY (`manual_id`) REFERENCES `manuais` (`id`) ON DELETE CASCADE,
  CONSTRAINT `avaliacoes_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `cor` varchar(20) DEFAULT NULL,
  `icone` varchar(50) DEFAULT NULL,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"CREATE TABLE IF NOT EXISTS `comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manual_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `passo_id` int(11) DEFAULT NULL,
  `texto` text NOT NULL,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('aprovado','pendente','rejeitado') DEFAULT 'pendente',
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `passo_id` (`passo_id`),
  KEY `idx_manual` (`manual_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `comentarios_ibfk_1` FOREIGN KEY (`manual_id`) REFERENCES `manuais` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comentarios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `comentarios_ibfk_3` FOREIGN KEY (`passo_id`) REFERENCES `passos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_site` varchar(100) DEFAULT 'Sistema de Manuais',
  `logo_url` varchar(255) DEFAULT NULL,
  `cor_primaria` varchar(20) DEFAULT '#0d6efd',
  `cor_secundaria` varchar(20) DEFAULT '#6c757d',
  `permitir_comentarios` tinyint(1) DEFAULT 1,
  `requer_aprovacao_comentarios` tinyint(1) DEFAULT 1,
  `mensagem_boas_vindas` text DEFAULT NULL,
  `ultima_atualizacao` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"CREATE TABLE IF NOT EXISTS `historico_versoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manual_id` int(11) NOT NULL,
  `versao` varchar(20) NOT NULL,
  `mudancas` text DEFAULT NULL,
  `data_alteracao` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_manual_versao` (`manual_id`,`versao`),
  CONSTRAINT `historico_versoes_ibfk_1` FOREIGN KEY (`manual_id`) REFERENCES `manuais` (`id`) ON DELETE CASCADE,
  CONSTRAINT `historico_versoes_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"CREATE TABLE IF NOT EXISTS `logs_acesso` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `manual_id` int(11) DEFAULT NULL,
  `acao` enum('visualizar','editar','criar','excluir','download') NOT NULL,
  `data_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_data` (`data_hora`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_manual` (`manual_id`),
  CONSTRAINT `logs_acesso_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `logs_acesso_ibfk_2` FOREIGN KEY (`manual_id`) REFERENCES `manuais` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"CREATE TABLE IF NOT EXISTS `manuais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `autor_id` int(11) DEFAULT NULL,
  `versao` varchar(20) DEFAULT '1.0',
  `imagem_capa` longtext DEFAULT NULL,
  `status` enum('ativo','rascunho','arquivado') DEFAULT 'ativo',
  `visualizacoes` int(11) DEFAULT 0,
  `downloads` int(11) DEFAULT 0,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `data_publicacao` datetime DEFAULT NULL,
  `total_passos` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `autor_id` (`autor_id`),
  KEY `idx_titulo` (`titulo`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `manuais_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `manuais_ibfk_2` FOREIGN KEY (`autor_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"CREATE TABLE IF NOT EXISTS `manual_tags` (
  `manual_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`manual_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `manual_tags_ibfk_1` FOREIGN KEY (`manual_id`) REFERENCES `manuais` (`id`) ON DELETE CASCADE,
  CONSTRAINT `manual_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"CREATE TABLE IF NOT EXISTS `passos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manual_id` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `texto` longtext DEFAULT NULL,
  `imagem` longtext DEFAULT NULL,
  `legenda` text DEFAULT NULL,
  `status` enum('ativo','inativo') DEFAULT 'ativo',
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_step_per_manual` (`manual_id`,`numero`),
  KEY `idx_manual_numero` (`manual_id`,`numero`),
  CONSTRAINT `passos_ibfk_1` FOREIGN KEY (`manual_id`) REFERENCES `manuais` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tag_name` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

"CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `cargo` varchar(50) DEFAULT NULL,
  `departamento` varchar(50) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `nivel_acesso` enum('admin','editor','visualizador') NOT NULL DEFAULT 'visualizador',
  `status` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  `ultimo_acesso` datetime DEFAULT NULL,
  `token_reset` varchar(100) DEFAULT NULL,
  `token_expiracao` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

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
