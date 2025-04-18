<?php
// Inicia o buffer de saída
ob_start();

// Função para criar a tabela se ela não existir
function criarTabelaSeNecessario($conn, $queryCriarTabela) {
    if ($conn->query($queryCriarTabela) === TRUE) {
        echo "Tabela criada ou verificada com sucesso.<br>";
    } else {
        echo "Erro ao criar/verificar tabela: " . $conn->error . "<br>";
    }
}

// Tabelas da primeira atualização
$tabelas = [
    "CREATE TABLE IF NOT EXISTS anexos_os (
            id INT(11) NOT NULL AUTO_INCREMENT,
            ordem_servico_id INT(11) NOT NULL,
            caminho_anexo VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            data DATETIME NOT NULL,
            funcionario VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            status ENUM('ativo','removido') COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS arquivamentos (
            id INT(11) NOT NULL AUTO_INCREMENT,
            hash_arquivamento VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            atribuicao VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            categoria VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            data_ato DATE NOT NULL,
            livro VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            folha VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            termo VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            protocolo VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            matricula VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            descricao TEXT COLLATE utf8mb4_unicode_ci,
            cadastrado_por VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            data_cadastro DATETIME NOT NULL,
            modificacoes JSON DEFAULT NULL,
            partes_envolvidas JSON DEFAULT NULL,
            anexos JSON DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS atos_liquidados (
            id INT(11) NOT NULL AUTO_INCREMENT,
            ordem_servico_id INT(11) NOT NULL,
            ato VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            quantidade_liquidada INT(11) NOT NULL,
            desconto_legal DECIMAL(10,2) NOT NULL,
            descricao TEXT COLLATE utf8mb4_unicode_ci,
            emolumentos DECIMAL(10,2) NOT NULL,
            ferc DECIMAL(10,2) NOT NULL,
            fadep DECIMAL(10,2) NOT NULL,
            femp DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            funcionario VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            status VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            data TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS cadastro_serventia (
            id INT(11) NOT NULL,
            razao_social MEDIUMTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci,
            cidade TEXT,
            status INT(11) DEFAULT NULL,
            cns TINYTEXT
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;",

        "CREATE TABLE IF NOT EXISTS caixa (
            id INT(11) NOT NULL AUTO_INCREMENT,
            id_transporte_caixa INT(11) DEFAULT NULL,
            saldo_inicial DECIMAL(10,2) NOT NULL,
            funcionario VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            data_caixa DATE NOT NULL,
            status VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (id),
            KEY id_transporte_caixa (id_transporte_caixa)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS categorias (
            id INT(11) NOT NULL AUTO_INCREMENT,
            titulo VARCHAR(255) CHARACTER SET latin1 NOT NULL,
            status VARCHAR(50) CHARACTER SET latin1 NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS comentarios (
            id INT(11) NOT NULL AUTO_INCREMENT,
            hash_tarefa VARCHAR(64) NOT NULL,
            comentario TEXT NOT NULL,
            caminho_anexo TEXT,
            data_comentario DATETIME NOT NULL,
            funcionario VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL,
            data_atualizacao DATETIME DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;",

        "CREATE TABLE IF NOT EXISTS conexao_selador (
            id INT(11) NOT NULL AUTO_INCREMENT,
            url_base VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            porta VARCHAR(10) COLLATE utf8mb4_unicode_ci NOT NULL,
            usuario VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            senha VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS configuracao_os (
            id INT(11) NOT NULL AUTO_INCREMENT,
            banco VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            agencia VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            tipo_conta VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            numero_conta VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            titular_conta VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            cpf_cnpj_titular VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            chave_pix VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            qr_code_pix LONGBLOB,
            status VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT 'ativa',
            PRIMARY KEY (id)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS decisao (
            id INT(11) NOT NULL AUTO_INCREMENT,
            requerente VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            qualificacao VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            origem VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            numero_pedido VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            motivo TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
            analise_dos_fatos TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
            decisao TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
            data DATE NOT NULL,
            criado_por VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            funcionario_responsavel VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            numero_decisao VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            selo_decisao VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            id_selo_decisao INT(11) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY id_selo_decisao (id_selo_decisao)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS deposito_caixa (
            id INT(11) NOT NULL AUTO_INCREMENT,
            funcionario VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            data_caixa DATE NOT NULL,
            data_cadastro TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            valor_do_deposito DECIMAL(10,2) NOT NULL,
            tipo_deposito VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            caminho_anexo VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            status ENUM('ativo','removido') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
            PRIMARY KEY (id)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `devolucao_os` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ordem_de_servico_id` int(11) NOT NULL,
            `cliente` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `total_os` decimal(10,2) NOT NULL,
            `total_devolucao` decimal(10,2) NOT NULL,
            `forma_devolucao` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `data_devolucao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `funcionario` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `funcionarios` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `usuario` varchar(50) CHARACTER SET latin1 NOT NULL,
            `senha` varchar(255) CHARACTER SET latin1 NOT NULL,
            `nome_completo` varchar(100) CHARACTER SET latin1 NOT NULL,
            `cargo` varchar(50) CHARACTER SET latin1 NOT NULL,
            `nivel_de_acesso` varchar(20) CHARACTER SET latin1 DEFAULT 'usuario',
            `status` varchar(20) CHARACTER SET latin1 DEFAULT 'ativo',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `indexador_nascimento` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `termo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `livro` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `folha` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `data_registro` date NOT NULL,
            `data_nascimento` date NOT NULL,
            `nome_registrado` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `nome_pai` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `nome_mae` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `data_cadastro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `funcionario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `status` enum('ativo','removido') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `indexador_nascimento_anexos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_nascimento` int(11) NOT NULL,
            `caminho_anexo` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `data` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `funcionario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `status` enum('ativo','removido') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
            PRIMARY KEY (`id`),
            KEY `id_nascimento` (`id_nascimento`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `logs_de_acesso` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `usuario` varchar(50) NOT NULL,
            `nome_completo` varchar(100) NOT NULL,
            `ip` varchar(45) NOT NULL,
            `data_hora` datetime NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;",

        "CREATE TABLE IF NOT EXISTS `logs_oficios` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `numero` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
            `destinatario` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
            `assunto` text CHARACTER SET latin1,
            `corpo` text CHARACTER SET latin1,
            `assinante` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
            `data` date DEFAULT NULL,
            `tratamento` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
            `cargo` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
            `cargo_assinante` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
            `data_edicao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `atualizado_por` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `logs_ordens_de_servico` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ordem_de_servico_id` int(11) DEFAULT NULL,
            `cliente` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `cpf_cliente` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `total_os` decimal(10,2) NOT NULL,
            `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `descricao_os` mediumtext COLLATE utf8mb4_unicode_ci,
            `observacoes` mediumtext COLLATE utf8mb4_unicode_ci,
            `criado_por` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `editado_por` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `data_edicao` datetime DEFAULT NULL,
            `base_de_calculo` decimal(10,2) DEFAULT NULL,
            PRIMARY KEY (`id`) USING BTREE
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;",

        "CREATE TABLE IF NOT EXISTS `logs_ordens_de_servico_itens` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ordem_servico_id` int(11) NOT NULL,
            `ato` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
            `quantidade` int(11) NOT NULL,
            `desconto_legal` decimal(5,2) NOT NULL,
            `descricao` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `emolumentos` decimal(10,2) NOT NULL,
            `ferc` decimal(10,2) NOT NULL,
            `fadep` decimal(10,2) NOT NULL,
            `femp` decimal(10,2) NOT NULL,
            `total` decimal(10,2) NOT NULL,
            `quantidade_liquidada` int(11) DEFAULT NULL,
            `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;",

        "CREATE TABLE IF NOT EXISTS `logs_tarefas` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `task_id` int(11) NOT NULL,
            `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `categoria` int(11) NOT NULL,
            `origem` int(11) NOT NULL,
            `data_limite` datetime NOT NULL,
            `funcionario_responsavel` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `descricao` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `caminho_anexo` text COLLATE utf8mb4_unicode_ci,
            `data_criacao` datetime DEFAULT CURRENT_TIMESTAMP,
            `data_edicao` datetime NOT NULL,
            `atualizado_por` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `modo_usuario` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `usuario` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `modo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `usuario` (`usuario`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `ordens_de_servico` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `cliente` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `cpf_cliente` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `total_os` decimal(10,2) NOT NULL,
            `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `descricao_os` text COLLATE utf8mb4_unicode_ci,
            `observacoes` text COLLATE utf8mb4_unicode_ci,
            `criado_por` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `editado_por` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `data_edicao` datetime DEFAULT NULL,
            `base_de_calculo` decimal(10,2) NOT NULL,
        PRIMARY KEY (`id`)
            ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `ordens_de_servico_itens` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ordem_servico_id` int(11) NOT NULL,
            `ato` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
            `quantidade` int(11) NOT NULL,
            `desconto_legal` decimal(5,2) NOT NULL,
            `descricao` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `emolumentos` decimal(10,2) NOT NULL,
            `ferc` decimal(10,2) NOT NULL,
            `fadep` decimal(10,2) NOT NULL,
            `femp` decimal(10,2) NOT NULL,
            `total` decimal(10,2) NOT NULL,
            `quantidade_liquidada` int(11) DEFAULT NULL,
            `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `ordem_servico_id` (`ordem_servico_id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `origem` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `titulo` varchar(255) CHARACTER SET latin1 NOT NULL,
            `status` varchar(50) CHARACTER SET latin1 NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `pagamento_os` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ordem_de_servico_id` int(11) NOT NULL,
            `cliente` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `total_os` decimal(10,2) NOT NULL,
            `total_pagamento` decimal(10,2) NOT NULL,
            `forma_de_pagamento` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `data_pagamento` datetime NOT NULL,
            `funcionario` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            KEY `ordem_de_servico_id` (`ordem_de_servico_id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `provimentos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `numero_provimento` varchar(50) NOT NULL,
            `origem` varchar(50) NOT NULL,
            `descricao` text NOT NULL,
            `data_provimento` date NOT NULL,
            `caminho_anexo` varchar(255) NOT NULL,
            `funcionario` varchar(100) NOT NULL,
            `data_cadastro` datetime NOT NULL,
            `status` enum('Ativo','Inativo') NOT NULL DEFAULT 'Ativo',
            `tipo` enum('Provimento','Resolução') NOT NULL DEFAULT 'Provimento',
            `conteudo_anexo` longtext,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;",

        "CREATE TABLE IF NOT EXISTS `recibos_de_entrega` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `task_id` int(11) NOT NULL,
            `receptor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `entregador` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `data_entrega` datetime NOT NULL,
            `documentos` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `observacoes` text COLLATE utf8mb4_unicode_ci,
            PRIMARY KEY (`id`),
            KEY `task_id` (`task_id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `registros_cedulas` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `titulo_cedula` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `n_cedula` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `emissao_cedula` date NOT NULL,
            `vencimento_cedula` date NOT NULL,
            `valor_cedula` decimal(10,2) NOT NULL,
            `credor` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `emitente` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `registro_garantia` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `forma_de_pagamento` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `vencimento_antecipado` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `juros` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `matricula` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `data` date NOT NULL,
            `funcionario` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `avalista` text COLLATE utf8mb4_unicode_ci,
            `imovel_localizacao` text COLLATE utf8mb4_unicode_ci,
            `tipo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `repasse_credor` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ordem_de_servico_id` int(11) NOT NULL,
            `cliente` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `total_os` decimal(10,2) NOT NULL,
            `total_repasse` decimal(10,2) NOT NULL,
            `forma_repasse` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `data_repasse` datetime NOT NULL,
            `data_os` date NOT NULL,
            `funcionario` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `requerimentos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `requerente` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `qualificacao` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `motivo` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `peticao` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `data` date NOT NULL,
            `criado_por` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `numero_decisao` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `selo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `id_selo` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `id_selo` (`id_selo`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `saidas_despesas` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `valor_saida` decimal(10,2) NOT NULL,
            `forma_de_saida` enum('PIX','Transferência Bancária','Espécie') COLLATE utf8mb4_unicode_ci NOT NULL,
            `data` date NOT NULL,
            `data_caixa` date NOT NULL,
            `funcionario` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `caminho_anexo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `status` enum('ativo','removido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ativo',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `selos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ato` varchar(255) NOT NULL,
            `escrevente` varchar(255) NOT NULL,
            `isento` varchar(255) NOT NULL,
            `partes` text NOT NULL,
            `quantidade` int(11) NOT NULL,
            `numero_selo` varchar(255) NOT NULL,
            `texto_selo` text NOT NULL,
            `qr_code` text,
            `data_geracao` datetime NOT NULL,
            `valor_qr_code` text,
            `retorno_selo` text NOT NULL,
            `numero_controle` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;",

        "CREATE TABLE IF NOT EXISTS `selos_arquivamentos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `arquivo_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `selo_id` int(11) NOT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `selo_id` (`selo_id`),
            KEY `arquivo_id` (`arquivo_id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `selos_requerimentos` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `requerimento_id` int(11) NOT NULL,
            `selo_id` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `requerimento_id` (`requerimento_id`),
            KEY `selo_id` (`selo_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `tabela_emolumentos` (
            `ID` int(11) NOT NULL AUTO_INCREMENT,
            `ATO` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `DESCRICAO` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `EMOLUMENTOS` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `FERC` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `FADEP` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `FEMP` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `TOTAL` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            PRIMARY KEY (`ID`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "CREATE TABLE IF NOT EXISTS `tarefas` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `token` varchar(255) NOT NULL,
            `titulo` varchar(255) NOT NULL,
            `categoria` varchar(255) NOT NULL,
            `origem` varchar(255) DEFAULT NULL,
            `descricao` text,
            `data_limite` datetime NOT NULL,
            `funcionario_responsavel` varchar(255) NOT NULL,
            `criado_por` varchar(255) NOT NULL,
            `data_conclusao` datetime DEFAULT NULL,
            `status` varchar(50) DEFAULT 'pendente',
            `data_criacao` datetime DEFAULT NULL,
            `atualizado_por` varchar(255) DEFAULT NULL,
            `data_atualizacao` datetime DEFAULT NULL,
            `caminho_anexo` text,
            `numero_oficio` varchar(50) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `token` (`token`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;",

        "CREATE TABLE IF NOT EXISTS transporte_saldo_caixa (
            id INT(11) NOT NULL AUTO_INCREMENT,
            data_caixa DATE NOT NULL,
            data_transporte DATE NOT NULL,
            valor_transportado DECIMAL(10,2) NOT NULL,
            funcionario VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            status VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            data_caixa_uso DATE DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
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
    <title>Sistema de Atualização</title>
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
