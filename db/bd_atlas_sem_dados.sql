-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           5.7.19 - MySQL Community Server (GPL)
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              11.0.0.5919
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;


-- Copiando estrutura do banco de dados para atlas
CREATE DATABASE IF NOT EXISTS `atlas` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `atlas`;

-- Copiando estrutura para tabela atlas.anexos_os
CREATE TABLE IF NOT EXISTS `anexos_os` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordem_servico_id` int(11) NOT NULL,
  `caminho_anexo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` datetime NOT NULL,
  `funcionario` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('ativo','removido') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.arquivamentos
CREATE TABLE IF NOT EXISTS `arquivamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hash_arquivamento` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `atribuicao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_ato` date NOT NULL,
  `livro` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `folha` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `termo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `protocolo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `matricula` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `cadastrado_por` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_cadastro` datetime NOT NULL,
  `modificacoes` json DEFAULT NULL,
  `partes_envolvidas` json DEFAULT NULL,
  `anexos` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.atos_liquidados
CREATE TABLE IF NOT EXISTS `atos_liquidados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordem_servico_id` int(11) NOT NULL,
  `ato` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade_liquidada` int(11) NOT NULL,
  `desconto_legal` decimal(10,2) NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `emolumentos` decimal(10,2) NOT NULL,
  `ferc` decimal(10,2) NOT NULL,
  `fadep` decimal(10,2) NOT NULL,
  `femp` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `funcionario` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.categorias
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET latin1 NOT NULL,
  `status` varchar(50) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.comentarios
CREATE TABLE IF NOT EXISTS `comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hash_tarefa` varchar(64) NOT NULL,
  `comentario` text NOT NULL,
  `caminho_anexo` text,
  `data_comentario` datetime NOT NULL,
  `funcionario` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL,
  `data_atualizacao` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.configuracao_os
CREATE TABLE IF NOT EXISTS `configuracao_os` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `banco` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agencia` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_conta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_conta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titular_conta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cpf_cnpj_titular` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chave_pix` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qr_code_pix` longblob,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'ativa',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.decisao
CREATE TABLE IF NOT EXISTS `decisao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requerente` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qualificacao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origem` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_pedido` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `analise_dos_fatos` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `decisao` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` date NOT NULL,
  `criado_por` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `funcionario_responsavel` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_decisao` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `selo_decisao` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_selo_decisao` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_selo_decisao` (`id_selo_decisao`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.deposito_caixa
CREATE TABLE IF NOT EXISTS `deposito_caixa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `funcionario` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_caixa` date NOT NULL,
  `data_cadastro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `valor_do_deposito` decimal(10,2) NOT NULL,
  `tipo_deposito` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caminho_anexo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('ativo','removido') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.devolucao_os
CREATE TABLE IF NOT EXISTS `devolucao_os` (
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
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.logs_de_acesso
CREATE TABLE IF NOT EXISTS `logs_de_acesso` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `nome_completo` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `data_hora` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.logs_oficios
CREATE TABLE IF NOT EXISTS `logs_oficios` (
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.logs_ordens_de_servico
CREATE TABLE IF NOT EXISTS `logs_ordens_de_servico` (
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
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.logs_ordens_de_servico_itens
CREATE TABLE IF NOT EXISTS `logs_ordens_de_servico_itens` (
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
) ENGINE=MyISAM AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.logs_tarefas
CREATE TABLE IF NOT EXISTS `logs_tarefas` (
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
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.modo_usuario
CREATE TABLE IF NOT EXISTS `modo_usuario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.ordens_de_servico
CREATE TABLE IF NOT EXISTS `ordens_de_servico` (
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
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.ordens_de_servico_itens
CREATE TABLE IF NOT EXISTS `ordens_de_servico_itens` (
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
) ENGINE=MyISAM AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.origem
CREATE TABLE IF NOT EXISTS `origem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET latin1 NOT NULL,
  `status` varchar(50) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.pagamento_os
CREATE TABLE IF NOT EXISTS `pagamento_os` (
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
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.recibos_de_entrega
CREATE TABLE IF NOT EXISTS `recibos_de_entrega` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `receptor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entregador` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_entrega` datetime NOT NULL,
  `documentos` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.registros_cedulas
CREATE TABLE IF NOT EXISTS `registros_cedulas` (
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.requerimentos
CREATE TABLE IF NOT EXISTS `requerimentos` (
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.saidas_despesas
CREATE TABLE IF NOT EXISTS `saidas_despesas` (
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
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.selos
CREATE TABLE IF NOT EXISTS `selos` (
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
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.selos_arquivamentos
CREATE TABLE IF NOT EXISTS `selos_arquivamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `arquivo_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `selo_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `selo_id` (`selo_id`),
  KEY `arquivo_id` (`arquivo_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.selos_requerimentos
CREATE TABLE IF NOT EXISTS `selos_requerimentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requerimento_id` int(11) NOT NULL,
  `selo_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `requerimento_id` (`requerimento_id`),
  KEY `selo_id` (`selo_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.tabela_emolumentos
CREATE TABLE IF NOT EXISTS `tabela_emolumentos` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ATO` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `DESCRICAO` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `EMOLUMENTOS` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `FERC` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `FADEP` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `FEMP` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `TOTAL` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`)
) ENGINE=MyISAM AUTO_INCREMENT=584 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.tarefas
CREATE TABLE IF NOT EXISTS `tarefas` (
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
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

-- Exportação de dados foi desmarcado.

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
