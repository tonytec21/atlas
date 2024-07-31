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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.anexos_os: 0 rows
/*!40000 ALTER TABLE `anexos_os` DISABLE KEYS */;
/*!40000 ALTER TABLE `anexos_os` ENABLE KEYS */;

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

-- Copiando dados para a tabela atlas.arquivamentos: 0 rows
/*!40000 ALTER TABLE `arquivamentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `arquivamentos` ENABLE KEYS */;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.atos_liquidados: 0 rows
/*!40000 ALTER TABLE `atos_liquidados` DISABLE KEYS */;
/*!40000 ALTER TABLE `atos_liquidados` ENABLE KEYS */;

-- Copiando estrutura para tabela atlas.cadastro_serventia
CREATE TABLE IF NOT EXISTS `cadastro_serventia` (
  `id` int(11) NOT NULL,
  `razao_social` text,
  `cidade` text,
  `status` int(11) DEFAULT NULL,
  `cns` tinytext
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Copiando dados para a tabela atlas.cadastro_serventia: 1 rows
/*!40000 ALTER TABLE `cadastro_serventia` DISABLE KEYS */;
INSERT IGNORE INTO `cadastro_serventia` (`id`, `razao_social`, `cidade`, `status`, `cns`) VALUES
	(1, 'Serventia Extrajudicial do Ofício Único de Esperantinópolis-MA', 'Esperantinópolis-MA', 1, '030072');
/*!40000 ALTER TABLE `cadastro_serventia` ENABLE KEYS */;

-- Copiando estrutura para tabela atlas.categorias
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET latin1 NOT NULL,
  `status` varchar(50) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.categorias: 6 rows
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT IGNORE INTO `categorias` (`id`, `titulo`, `status`) VALUES
	(1, 'Registro Civil', 'ativo'),
	(2, 'Registro de Imóveis', 'ativo'),
	(3, 'Registro de Títulos e Documentos', 'ativo'),
	(4, 'Registro Civil das Pessoas Jurídicas', 'ativo'),
	(5, 'Notas', 'ativo'),
	(6, 'Protesto', 'ativo');
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;

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

-- Copiando dados para a tabela atlas.comentarios: 0 rows
/*!40000 ALTER TABLE `comentarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `comentarios` ENABLE KEYS */;

-- Copiando estrutura para tabela atlas.conexao_selador
CREATE TABLE IF NOT EXISTS `conexao_selador` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url_base` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `porta` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `senha` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.conexao_selador: 1 rows
/*!40000 ALTER TABLE `conexao_selador` DISABLE KEYS */;
INSERT IGNORE INTO `conexao_selador` (`id`, `url_base`, `porta`, `usuario`, `senha`) VALUES
	(1, 'https://selador.ma.portalselo.com.br', '9443', 'homologacao', 'a907438c85f0');
/*!40000 ALTER TABLE `conexao_selador` ENABLE KEYS */;

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

-- Copiando dados para a tabela atlas.configuracao_os: 0 rows
/*!40000 ALTER TABLE `configuracao_os` DISABLE KEYS */;
/*!40000 ALTER TABLE `configuracao_os` ENABLE KEYS */;

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

-- Copiando dados para a tabela atlas.decisao: 0 rows
/*!40000 ALTER TABLE `decisao` DISABLE KEYS */;
/*!40000 ALTER TABLE `decisao` ENABLE KEYS */;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.devolucao_os: 0 rows
/*!40000 ALTER TABLE `devolucao_os` DISABLE KEYS */;
/*!40000 ALTER TABLE `devolucao_os` ENABLE KEYS */;

-- Copiando estrutura para tabela atlas.funcionarios
CREATE TABLE IF NOT EXISTS `funcionarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) CHARACTER SET latin1 NOT NULL,
  `senha` varchar(255) CHARACTER SET latin1 NOT NULL,
  `nome_completo` varchar(100) CHARACTER SET latin1 NOT NULL,
  `cargo` varchar(50) CHARACTER SET latin1 NOT NULL,
  `nivel_de_acesso` varchar(20) CHARACTER SET latin1 DEFAULT 'usuario',
  `status` varchar(20) CHARACTER SET latin1 DEFAULT 'ativo',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.funcionarios: 1 rows
/*!40000 ALTER TABLE `funcionarios` DISABLE KEYS */;
INSERT IGNORE INTO `funcionarios` (`id`, `usuario`, `senha`, `nome_completo`, `cargo`, `nivel_de_acesso`, `status`) VALUES
	(1, 'ADMIN', 'MTMwNA==', 'Antonio José Martins Garcia', 'Escrevente Autorizado', 'administrador', 'ativo');
/*!40000 ALTER TABLE `funcionarios` ENABLE KEYS */;

-- Copiando estrutura para tabela atlas.logs_de_acesso
CREATE TABLE IF NOT EXISTS `logs_de_acesso` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `nome_completo` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `data_hora` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Copiando dados para a tabela atlas.logs_de_acesso: 0 rows
/*!40000 ALTER TABLE `logs_de_acesso` DISABLE KEYS */;
/*!40000 ALTER TABLE `logs_de_acesso` ENABLE KEYS */;

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

-- Copiando dados para a tabela atlas.logs_oficios: 0 rows
/*!40000 ALTER TABLE `logs_oficios` DISABLE KEYS */;
/*!40000 ALTER TABLE `logs_oficios` ENABLE KEYS */;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Copiando dados para a tabela atlas.logs_ordens_de_servico: 0 rows
/*!40000 ALTER TABLE `logs_ordens_de_servico` DISABLE KEYS */;
/*!40000 ALTER TABLE `logs_ordens_de_servico` ENABLE KEYS */;

-- Copiando estrutura para tabela atlas.logs_ordens_de_servico_itens
CREATE TABLE IF NOT EXISTS `logs_ordens_de_servico_itens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordem_servico_id` int(11) NOT NULL,
  `ato` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade` int(11) NOT NULL,
  `desconto_legal` decimal(5,2) NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `emolumentos` decimal(10,2) NOT NULL,
  `ferc` decimal(10,2) NOT NULL,
  `fadep` decimal(10,2) NOT NULL,
  `femp` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `quantidade_liquidada` int(11) DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Copiando dados para a tabela atlas.logs_ordens_de_servico_itens: 0 rows
/*!40000 ALTER TABLE `logs_ordens_de_servico_itens` DISABLE KEYS */;
/*!40000 ALTER TABLE `logs_ordens_de_servico_itens` ENABLE KEYS */;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.logs_tarefas: 0 rows
/*!40000 ALTER TABLE `logs_tarefas` DISABLE KEYS */;
/*!40000 ALTER TABLE `logs_tarefas` ENABLE KEYS */;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.ordens_de_servico: 0 rows
/*!40000 ALTER TABLE `ordens_de_servico` DISABLE KEYS */;
/*!40000 ALTER TABLE `ordens_de_servico` ENABLE KEYS */;

-- Copiando estrutura para tabela atlas.ordens_de_servico_itens
CREATE TABLE IF NOT EXISTS `ordens_de_servico_itens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordem_servico_id` int(11) NOT NULL,
  `ato` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade` int(11) NOT NULL,
  `desconto_legal` decimal(5,2) NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `emolumentos` decimal(10,2) NOT NULL,
  `ferc` decimal(10,2) NOT NULL,
  `fadep` decimal(10,2) NOT NULL,
  `femp` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `quantidade_liquidada` int(11) DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ordem_servico_id` (`ordem_servico_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.ordens_de_servico_itens: 0 rows
/*!40000 ALTER TABLE `ordens_de_servico_itens` DISABLE KEYS */;
/*!40000 ALTER TABLE `ordens_de_servico_itens` ENABLE KEYS */;

-- Copiando estrutura para tabela atlas.origem
CREATE TABLE IF NOT EXISTS `origem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) CHARACTER SET latin1 NOT NULL,
  `status` varchar(50) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.origem: 7 rows
/*!40000 ALTER TABLE `origem` DISABLE KEYS */;
INSERT IGNORE INTO `origem` (`id`, `titulo`, `status`) VALUES
	(1, 'Balcão', 'ativo'),
	(2, 'Malote Digital', 'ativo'),
	(3, 'E-mail', 'ativo'),
	(4, 'WhatsApp', 'ativo'),
	(5, 'Cartórios MA', 'ativo'),
	(6, 'CRC', 'ativo'),
	(7, 'ONR', 'ativo');
/*!40000 ALTER TABLE `origem` ENABLE KEYS */;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.pagamento_os: 0 rows
/*!40000 ALTER TABLE `pagamento_os` DISABLE KEYS */;
/*!40000 ALTER TABLE `pagamento_os` ENABLE KEYS */;

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

-- Copiando dados para a tabela atlas.recibos_de_entrega: 0 rows
/*!40000 ALTER TABLE `recibos_de_entrega` DISABLE KEYS */;
/*!40000 ALTER TABLE `recibos_de_entrega` ENABLE KEYS */;

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

-- Copiando dados para a tabela atlas.registros_cedulas: 0 rows
/*!40000 ALTER TABLE `registros_cedulas` DISABLE KEYS */;
/*!40000 ALTER TABLE `registros_cedulas` ENABLE KEYS */;

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

-- Copiando dados para a tabela atlas.requerimentos: 0 rows
/*!40000 ALTER TABLE `requerimentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `requerimentos` ENABLE KEYS */;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Copiando dados para a tabela atlas.selos: 0 rows
/*!40000 ALTER TABLE `selos` DISABLE KEYS */;
/*!40000 ALTER TABLE `selos` ENABLE KEYS */;

-- Copiando estrutura para tabela atlas.selos_arquivamentos
CREATE TABLE IF NOT EXISTS `selos_arquivamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `arquivo_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `selo_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `selo_id` (`selo_id`),
  KEY `arquivo_id` (`arquivo_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.selos_arquivamentos: 0 rows
/*!40000 ALTER TABLE `selos_arquivamentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `selos_arquivamentos` ENABLE KEYS */;

-- Copiando estrutura para tabela atlas.selos_requerimentos
CREATE TABLE IF NOT EXISTS `selos_requerimentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requerimento_id` int(11) NOT NULL,
  `selo_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `requerimento_id` (`requerimento_id`),
  KEY `selo_id` (`selo_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.selos_requerimentos: 0 rows
/*!40000 ALTER TABLE `selos_requerimentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `selos_requerimentos` ENABLE KEYS */;

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

-- Copiando dados para a tabela atlas.tabela_emolumentos: 583 rows
/*!40000 ALTER TABLE `tabela_emolumentos` DISABLE KEYS */;
INSERT IGNORE INTO `tabela_emolumentos` (`ID`, `ATO`, `DESCRICAO`, `EMOLUMENTOS`, `FERC`, `FADEP`, `FEMP`, `TOTAL`) VALUES
	(1, '13.1', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato):', '', '', '', '', ''),
	(2, '13.1.1', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): Até R$ 6.772,25', '136.94', '4.10', '5.47', '5.47', '151.98'),
	(3, '13.1.2', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 6.772,26 a R$ 10.564,69', '171.24', '5.13', '6.84', '6.84', '190.05'),
	(4, '13.1.3', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 10.564,70 a R$ 13.205,87', '193.59', '5.80', '7.74', '7.74', '214.87'),
	(5, '13.1.4', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 13.205,88 a R$ 16.507,33', '242.02', '7.26', '9.68', '9.68', '268.64'),
	(6, '13.1.5', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 16.507,34 a R$ 20.634,17', '301.11', '9.03', '12.04', '12.04', '334.22'),
	(7, '13.1.6', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 20.634,18 a R$ 25.792,70', '376.01', '11.28', '15.04', '15.04', '417.37'),
	(8, '13.1.7', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 25.792,71 a R$ 32.240,88', '470.43', '14.11', '18.81', '18.81', '522.16'),
	(9, '13.1.8', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 32.240,89 a R$ 40.301,09', '589.39', '17.68', '23.57', '23.57', '654.21'),
	(10, '13.1.9', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 40.301,10 a R$ 50.376,36', '736.87', '22.10', '29.47', '29.47', '817.91'),
	(11, '13.1.10', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 50.376,37 a R$ 62.970,44', '919.55', '27.58', '36.78', '36.78', '1020.69'),
	(12, '13.1.11', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 62.970,45 a R$ 78.713,07', '1150.40', '34.51', '46.01', '46.01', '1276.93'),
	(13, '13.1.12', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 78.713,08 a R$ 98.391,33', '1438.67', '43.15', '57.54', '57.54', '1596.90'),
	(14, '13.1.13', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 98.391,34 a R$ 122.989,15', '1796.82', '53.90', '71.87', '71.87', '1994.46'),
	(15, '13.1.14', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 122.989,16 a R$ 153.736,44', '2247.99', '67.43', '89.91', '89.91', '2495.24'),
	(16, '13.1.15', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 153.736,45 a R$ 192.170,56', '2809.00', '84.26', '112.35', '112.35', '3117.96'),
	(17, '13.1.16', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 192.170,57 a R$ 240.213,20', '3510.15', '105.30', '140.40', '140.40', '3896.25'),
	(18, '13.1.17', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 240.213,21 a R$ 300.266,48', '4387.68', '131.63', '175.50', '175.50', '4870.31'),
	(19, '13.1.18', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 300.266,49 a R$ 375.333,10', '5485.54', '164.56', '219.42', '219.42', '6088.94'),
	(20, '13.1.19', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 375.333,11 a R$ 469.166,40', '6857.79', '205.73', '274.31', '274.31', '7612.14'),
	(21, '13.1.20', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 469.166,41 a R$ 586.458,01', '8571.24', '257.13', '342.84', '342.84', '9514.05'),
	(22, '13.1.21', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 586.458,02 a R$ 733.072,52', '10713.37', '321.40', '428.53', '428.53', '11891.83'),
	(23, '13.1.22', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 733.072,53 a R$ 916.340,66', '13392.36', '401.77', '535.69', '535.69', '14865.51'),
	(24, '13.1.23', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 916.340,67 a R$ 1.145.425,82', '14142.07', '424.26', '565.68', '565.68', '15697.69'),
	(25, '13.1.24', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 1.145.425,83 a R$ 1.385.965,24', '14566.27', '436.98', '582.65', '582.65', '16168.55'),
	(26, '13.1.25', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 1.385.965,25 a R$ 1.663.158,30', '15003.30', '450.09', '600.13', '600.13', '16653.65'),
	(27, '13.1.26', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 1.663.158,31 a R$ 1.995.789,96', '15453.43', '463.60', '618.13', '618.13', '17153.29'),
	(28, '13.1.27', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 1.995.789,97 a R$ 2.394.947,96', '15917.06', '477.51', '636.68', '636.68', '17667.93'),
	(29, '13.1.28', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 2.394.947,97 a R$ 2.873.937,56', '16394.56', '491.83', '655.78', '655.78', '18197.95'),
	(30, '13.1.29', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 2.873.937,57 a R$ 3.448.725,07', '16886.32', '506.58', '675.45', '675.45', '18743.80'),
	(31, '13.1.30', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 3.448.725,08 a R$ 4.138.470,07', '17392.98', '521.78', '695.71', '695.71', '19306.18'),
	(32, '13.1.31', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 4.138.470,08 a R$ 4.966.164,09', '17914.67', '537.44', '716.58', '716.58', '19885.27'),
	(33, '13.1.32', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 4.966.164,10 a R$ 5.959.396,92', '18452.17', '553.56', '738.08', '738.08', '20481.89'),
	(34, '13.1.33', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): De R$ 5.959.396,93 a R$ 7.151.276,28', '19005.72', '570.17', '760.22', '760.22', '21096.33'),
	(35, '13.1.34', 'Escritura Pública com fornecimento do primeiro traslado (com base no valor do ato): Acima De R$ 7.151.276,28', '19575.85', '587.27', '783.03', '783.03', '21729.18'),
	(36, '13.2', 'Escritura Pública com fornecimento do primeiro traslado, sem valor econômico.', '136.94', '4.10', '5.47', '5.47', '151.98'),
	(37, '13.3', 'Escritura completa de permuta de bens será cobrada de acordo com o item 13.4. ', 'Informar Valor da Transação', '', '', '', ''),
	(38, '13.4', 'Havendo, na escritura, mais de um contrato ou estipulação que, por sua autonomia, possa ser objeto de outra escritura, os valores serão cobrados separadamente. (Alterado pela Lei nº 9.490, de 04/11/11) ', 'Orientação Informativa', '', '', '', ''),
	(39, '13.5', 'Os emolumentos referidos nos itens anteriores desta tabela serão calculados com base no valor declarado pelas partes ou com base na avaliação oficial da Fazenda Pública (o que for maior) ou, ainda, pelo preço de mercado apurado pelo Titular da Serventia, podendo utilizar-se do serviço de profissional idôneo, caso o valor declarado e a avaliação não sejam exigíveis ou forem com este incompatível. Poderá ainda, em se tratando de imóvel rural, utilizar a tabela do INCRA caso atualizada e compatível com o valor de mercado.', 'Orientação Informativa', '', '', '', ''),
	(40, '13.6', 'Os emolumentos devidos aos tabelionatos de notas nos atos relacionados à aquisição imobiliária para fins residenciais, oriundas de programas e convênios com a União, Estados, Distrito Federal e Municípios, para a construção de habitações populares destinadas a famílias de baixa renda, pelo sistema de mutirão e autoconstrução orientada, serão reduzidos para vinte por cento da tabela cartorária normal, considerando o imóvel limitado a até sessenta e nove metros quadrados de área construída, em terreno de até duzentos e cinquenta metros quadrados. (§ 4º do art. 290 da Lei nº 6.015, de 31 de dezembro de 1973.', 'Orientação Informativa', '', '', '', ''),
	(41, '13.7', 'Escritura de separação, divórcio e extinção de união estável sem bens a partilhar.', '136.94', '4.10', '5.47', '5.47', '151.98'),
	(42, '13.8', 'Escritura de separação, divórcio, extinção de união estável, partilha e inventário, e divisão amigável para dissolução de condomínio sobre imóvel, os emolumentos são os mesmos do item 13.1 com base no valor dos bens.', 'Informar Valor da Transação', '', '', '', ''),
	(43, '13.9', 'Procurações, incluindo o primeiro traslado, figurando apenas uma pessoa ou casal como outorgante:', '', '', '', '', ''),
	(44, '13.9.1', 'Em causa própria, os emolumentos serão os mesmos do item 13.1, reduzidos em cinquenta por cento. ', 'Informar Valor da Transação', '', '', '', ''),
	(45, '13.9.2', 'Procuração outorgada com poderes específicos para assinatura de contrato com instituição financeira para obtenção de empréstimo junto a Programas de Agricultura Familiar, para Programas de Assistência do Governo e para fins previdenciários.', '32.89', '0.98', '1.31', '1.31', '36.49'),
	(46, '13.9.3', 'Outras procurações', '114.46', '3.43', '4.57', '4.57', '127.03'),
	(47, '13.9.4', 'No caso de procurações com mais de uma pessoa, exceto o casal que se considera como apenas um outorgante, serão acrescidos aos emolumentos finais, por pessoa,', '13.36', '0.40', '0.53', '0.53', '14.82'),
	(48, '13.9.5', 'Nos substabelecimentos de procurações os emolumentos serão os mesmos do item 13.9.3.', '', '', '', '', ''),
	(49, '13.9.6', 'Revogação de procuração e de substabelecimento ou renúncia do mandato.', '114.46', '3.43', '4.57', '4.57', '127.03'),
	(50, '13.9.7', 'As procurações a que se refere o item 13.9.2, trata de caso específico, não podendo abranger poderes não relacionados a finalidade constante deste item. No caso, para fins previdenciários, somente alcança os poderes conferidos para atuação circunscrita à Previdência Social, Nos contratos de empréstimos junto a programas de agricultura familiar, e para os programas de assistência do governo devem ser especificados no corpo da procuração para poder obter o direito a redução – Orientação Informativa.', 'Orientação Informativa', '', '', '', ''),
	(51, '13.10', 'Testamento:', '', '', '', '', ''),
	(52, '13.10.1', 'Público sem conteúdo patrimonial', '109.83', '3.29', '4.39', '4.39', '121.90'),
	(53, '13.10.2', 'Público com valor patrimonial', '714.91', '21.44', '28.59', '28.59', '793.53'),
	(54, '13.10.3', 'Cerrado, incluindo todos os atos necessários.', '142.72', '4.28', '5.70', '5.70', '158.40'),
	(55, '13.10.4', 'Revogação de testamento.', '142.72', '4.28', '5.70', '5.70', '158.40'),
	(56, '13.10.5', 'Modificação de cláusula de testamento, os emolumentos serão os mesmos dos itens 13.10.1 a 13.10.2', '', '', '', '', ''),
	(57, '13.11', 'Escritura de constituição ou de especificação de condomínio em plano horizontal e suas modificações por convenção', '242.02', '7.26', '9.68', '9.68', '268.64'),
	(58, '13.11.1', 'Por unidade autônoma, o apartamento e as vagas na garagem que o servem, será acrescido de', '26.60', '0.79', '1.06', '1.06', '29.51'),
	(59, '13.12', 'Certidões ou traslado:', '', '', '', '', ''),
	(60, '13.12.1', 'Certidões ou traslado: Com uma folha', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(61, '13.12.2', 'REVOGADO', '', '', '', '', ''),
	(62, '13.12.3', 'Certidões ou traslado: Por folha acrescida além da primeira, mais', '8.73', '0.26', '0.34', '0.34', '9.67'),
	(63, '13.12.4', 'Certidão Eletrônica com buscas e folhas excedentes incluídas', '67.16', '2.01', '2.68', '2.68', '74.53'),
	(64, '13.13', 'Das buscas:', '', '', '', '', ''),
	(65, '13.13.1', 'Das buscas: Até dois anos', '6.56', '0.20', '0.26', '0.26', '7.28'),
	(66, '13.13.2', 'Das buscas: Até cinco anos', '10.92', '0.32', '0.43', '0.43', '12.10'),
	(67, '13.13.3', 'Das buscas: Até dez anos', '17.47', '0.52', '0.69', '0.69', '19.37'),
	(68, '13.13.4', 'Das buscas: Até quinze anos', '22.23', '0.66', '0.88', '0.88', '24.65'),
	(69, '13.13.5', 'Das buscas: Até vinte anos', '28.51', '0.85', '1.14', '1.14', '31.64'),
	(70, '13.13.6', 'Das buscas: Até trinta anos', '37.26', '1.11', '1.49', '1.49', '41.35'),
	(71, '13.13.7', 'Das buscas: Até cinquenta anos', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(72, '13.13.8', 'Das buscas: Acima de cinquenta anos', '56.91', '1.70', '2.27', '2.27', '63.15'),
	(73, '13.13.9', 'Se indicados dia, mês e ano da prática do ato, ou número e livro correto do ato não serão cobradas buscas.', 'Orientação Informativa', '', '', '', ''),
	(74, '13.14', 'Atas Notariais:', '', '', '', '', ''),
	(75, '13.14.1', 'Atas Notariais: Pela primeira folha', '220.31', '6.60', '8.81', '8.81', '244.53'),
	(76, '13.14.2', 'Atas Notariais: Por folha que exceder', '109.83', '3.29', '4.39', '4.39', '121.90'),
	(77, '13.14.3', 'Atas Notariais: Para fins do procedimento do Usucapião Extrajudicial, os emolumentos serão o mesmo do item 13.1, conforme o valor do imóvel.', 'Informar Valor da Transação', '', '', '', ''),
	(78, '13.15', 'Averbação de qualquer natureza', '22.23', '0.66', '0.88', '0.88', '24.65'),
	(79, '13.16', 'Escritura de retificação/ratificação sem valor econômico.', '136.94', '4.10', '5.47', '5.47', '151.98'),
	(80, '13.16.1', 'Escritura de retificação e/ ou ratificação com valor econômico, os emolumentos serão calculados com base no valor da diferença entre o valor originário e o retificado no ato, conforme tabela 13.1.', 'Informar Valor da Transação', '', '', '', ''),
	(81, '13.16.2', 'Sendo o ato retificado/ratificado oriundo de serventia diversa, o Tabelião de Notas que lavrou a escritura de retificação/ratificação comunicará o evento, para a remissão devida, ao que realizou o ato rerratificado – orientação informativa.', 'Orientação Informativa', '', '', '', ''),
	(82, '13.17', 'Registro de firma – cadastro', '', '', '', '', ''),
	(83, '13.17.1', 'Cadastro', '10.92', '0.32', '0.43', '0.43', '12.10'),
	(84, '13.17.2', 'Reconhecimento de sinal, letra e firma ou somente de firma, por assinatura', '5.65', '0.16', '0.22', '0.22', '6.25'),
	(85, '13.17.3', 'Reconhecimento de firma, por assinatura, em documento de transferência, mandato ou quitação de veículos automotores', '32.89', '0.98', '1.31', '1.31', '36.49'),
	(86, '13.17.4', 'Tratando-se de reconhecimento em documento com conteúdo financeiro', '19.92', '0.59', '0.79', '0.79', '22.09'),
	(87, '13.17.4.1', 'Considera-se documento com conteúdo financeiro aqueles cujo o valor esteja acima de R$ 383,60', 'Orientação informativa', '', '', '', ''),
	(88, '13.18', 'Autenticação de cópias de documentos extraídas por meio reprográfico, por página', '5.65', '0.16', '0.22', '0.22', '6.25'),
	(89, '13.19', 'Escritura completa de Conciliação e Mediação sem valor econômico, compreendendo todos os atos necessários inclusive o fornecimento do primeiro traslado, os emolumentos serão:', '136.94', '4.10', '5.47', '5.47', '151.98'),
	(90, '13.20', 'Escritura completa de Conciliação e Mediação com valor econômico, compreendendo todos os atos necessários inclusive o fornecimento do primeiro traslado, os emolumentos serão os mesmos do item 13.1 com base no valor do ato. ', 'Informar Valor da Transação', '', '', '', ''),
	(91, '13.21', 'Diligência quando o ato notarial for celebrado fora da serventia, na zona urbana: serão devidos, além da condução.', '39.82', '1.19', '1.59', '1.59', '44.19'),
	(92, '13.21.1', 'Diligência quando o ato notarial for celebrado fora da serventia, na zona rural: serão devidos, além da condução,', '66.42', '1.99', '2.65', '2.65', '73.71'),
	(93, '13.21.2', 'Diligência para cientificação de parte interessada nos processos de conciliação e mediação extrajudiciais, por parte interessada: serão devidos, além da condução,', '28.51', '0.85', '1.14', '1.14', '31.64'),
	(94, '13.21.3', 'REVOGADO', '', '', '', '', ''),
	(95, '13.22', 'Comunicação eletrônica de transferência de veículo os emolumentos serão.', '6.56', '0.20', '0.26', '0.26', '7.28'),
	(96, '13.23', 'Apostila de Haia - certificação de documentos produzidos em território nacional e destinados a produzir efeitos em Países partes da Convenção – os emolumentos serão.', '114.46', '3.43', '4.57', '4.57', '127.03'),
	(97, '13.24', 'Na hipótese de reserva, instituição ou renúncia de usufruto, será considerada a terça parte do valor do imóvel, para efeito de enquadramento nesta tabela – Orientação informativa', '', '', '', '', ''),
	(98, '13.25', 'Na doação com reserva de usufruto o cálculo dos emolumentos deve considerar dois atos: (a) um ato relativo à doação, com base de cálculo equivalente a 2/3 do valor do imóvel, e (b) um ato relativo à reserva de usufruto, com base de cálculo equivalente a 1/3 do valor do imóvel.', '', '', '', '', ''),
	(99, '13.26', 'Consideram-se exemplos de escrituras com conteúdo financeiro aquelas referentes à transmissão, a qualquer título, da propriedade de bens ou direitos, ou do domínio útil – orientação informativa.', '', '', '', '', ''),
	(100, '13.27', 'Na escritura de instituição de servidão a base de cálculo dos emolumentos corresponde a 20% do valor total do imóvel serviente, independentemente da fração ideal que ocupa.', '', '', '', '', ''),
	(101, '13.28', 'REVOGADO', '', '', '', '', ''),
	(102, '13.29', 'REVOGADO', '', '', '', '', ''),
	(103, '13.30', 'Arquivamento, por folha do documento, os emolumentos serão:', '5.65', '0.16', '0.22', '0.22', '6.25'),
	(104, '14.1', 'Casamento:', '', '', '', '', ''),
	(105, '14.1.1', 'Habilitação e registro, lavratura de assento de casamento, inclusive o religioso com efeitos civis, e conversão de união estável em casamento, compreendendo todas as despesas, exceto com editais e certidão.', '200.01', '6.00', '8.00', '8.00', '222.01'),
	(106, '14.1.2', 'Afixação, publicação e arquivamento de edital de proclamas, excluídas as despesas e publicação na imprensa quando necessário', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(107, '14.1.3', 'Diligência para casamento fora do serviço registral, mas na sede do Município, excluídas as despesas com Juiz de Paz e com transporte do Oficial.', '363.04', '10.89', '14.52', '14.52', '402.97'),
	(108, '14.1.4', 'Diligência para casamento fora do serviço registral, na zona rural, excluídas as despesas com Juiz de Paz e com transporte do Oficial.', '554.32', '16.62', '22.17', '22.17', '615.28'),
	(109, '14.1.5', 'Habilitação de casamento a ser realizado em outra serventia, inclusive o preparo de papéis, excluídas as despesas com publicação na imprensa', '142.72', '4.28', '5.70', '5.70', '158.40'),
	(110, '14.1.6', 'Lavratura de assento de casamento a vista de certidão de habilitação emitida por outra serventia.', '76.82', '2.30', '3.07', '3.07', '85.26'),
	(111, '14.1.7', 'Dispensa total ou parcial de edital de proclamas', '28.51', '0.85', '1.14', '1.14', '31.64'),
	(112, '14.1.8', 'Serão isentos de quaisquer emolumentos todos os atos necessários à realização do projeto Casamentos Comunitários organizado pelo Poder Judiciário do Maranhão.', '', '', '', '', ''),
	(113, '14.1.9', 'Registro de casamento nuncupativo.', '91.86', '2.75', '3.67', '3.67', '101.95'),
	(114, '14.1.10', 'Publicação de edital de proclamas na imprensa quando necessário.', '45.99', '1.37', '1.83', '1.83', '51.02'),
	(115, '14.a', 'Registro de nascimento, bem como pela primeira certidão respectiva. Isento. (Incluído pela Lei nº 9.490, de 04/11/11)', '', '', '', '', ''),
	(116, '14.b', 'Registro de nascimento realizado pelas Centrais ou Postos de Registro mantidos pelo poder público, bem como pela primeira certidão respectiva. Isento. (Incluído pela Lei nº 9.490, de 04/11/11)', '', '', '', '', ''),
	(117, '14.c', 'Assento de óbito, bem como pela primeira certidão respectiva. Isento. (Incluído pela Lei nº 9.490, de 04/11/11)', '', '', '', '', ''),
	(118, '14.d', 'Assento de natimorto, bem como pela primeira certidão respectiva. Isento. (Incluído pela Lei nº 9.490, de 04/11/11)', '', '', '', '', ''),
	(119, '14.2', 'Registro de emancipação, tutela, interdição ou ausência. (Alterado pela Lei nº 9.490, de 04/11/11)', '72.45', '2.17', '2.89', '2.89', '80.40'),
	(120, '14.3', 'Das transcrições:', '', '', '', '', ''),
	(121, '14.3.1', 'Transcrição de assento de nascimento, casamento e óbito ocorridos no exterior', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(122, '14.3.2', 'Transcrição de termo de opção pela nacionalidade brasileira', '56.91', '1.70', '2.27', '2.27', '63.15'),
	(123, '14.3.3', 'Retificação, restauração ou cancelamento de registro, qualquer que seja a causa e alteração de patronímico familiar por determinação judicial, excluída a certidão.', '56.91', '1.70', '2.27', '2.27', '63.15'),
	(124, '14.3.4 ', 'Procedimento de adoção e reconhecimento de filho por determinação judicial, excluída a certidão.', '72.45', '2.17', '2.89', '2.89', '80.40'),
	(125, '14.4', 'Das averbações em geral:', '', '', '', '', ''),
	(126, '14.4.1', 'Quando lavrada à margem do registro', '35.45', '1.06', '1.41', '1.41', '39.33'),
	(127, '14.4.2', 'Quando houver necessidade de transporte para outra folha', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(128, '14.4.3', 'Quando for referente à anulação de casamento, separação judicial, divórcio ou restabelecimento de sociedade conjugal', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(129, '14.5', 'Das certidões:', '', '', '', '', ''),
	(130, '14.5.1', 'Das certidões: Com uma folha', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(131, '14.5.2', 'Das certidões: Por folha acrescida além da primeira, mais', '8.73', '0.26', '0.34', '0.34', '9.67'),
	(132, '14.5.3', 'REVOGADO', '', '', '', '', ''),
	(133, '14.5.4', 'REVOGADO', '', '', '', '', ''),
	(134, '14.5.5', 'Certidão de Casamento Comunitário autorizado ou realizado pelo Poder Judiciário', '', '', '', '', ''),
	(135, '14.5.6', 'Certidões de inteiro teor', '62.46', '1.87', '2.49', '2.49', '69.31'),
	(136, '14.5.6.1', 'Certidões de inteiro teor por folha acrescida além da primeira, mais', '8.30', '0.24', '0.33', '0.33', '9.20'),
	(137, '14.5.7', 'Certidão Eletrônica com buscas e folhas excedentes incluídas', '67.16', '2.01', '2.68', '2.68', '74.53'),
	(138, '14.6', 'Das buscas:', '', '', '', '', ''),
	(139, '14.6.1', 'Das buscas: Até dois anos', '6.56', '0.20', '0.26', '0.26', '7.28'),
	(140, '14.6.2', 'Das buscas: Até cinco anos', '10.92', '0.32', '0.43', '0.43', '12.10'),
	(141, '14.6.3', 'Das buscas: Até dez anos', '17.47', '0.52', '0.69', '0.69', '19.37'),
	(142, '14.6.4', 'Das buscas: Até quinze anos', '22.23', '0.66', '0.88', '0.88', '24.65'),
	(143, '14.6.5', 'Das buscas: Até vinte anos', '28.51', '0.85', '1.14', '1.14', '31.64'),
	(144, '14.6.6', 'Das buscas: Até trinta anos', '37.26', '1.11', '1.49', '1.49', '41.35'),
	(145, '14.6.7', 'Das buscas: Até cinquenta anos', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(146, '14.6.8', 'Das buscas: Acima de cinquenta anos', '56.91', '1.70', '2.27', '2.27', '63.15'),
	(147, '14.6.9', 'Se indicados dia, mês e ano da prática do ato, ou número e livro corretos do ato não serão cobradas buscas.', '', '', '', '', ''),
	(148, '14.7', 'Anotação feita no próprio cartório ou mediante comunicação, além do porte postal.', '5.14', '0.15', '0.20', '0.20', '5.69'),
	(149, '14.8', 'Registro de união estável', '91.86', '2.75', '3.67', '3.67', '101.95'),
	(150, '14.9', 'As certidões de nascimento, casamento e óbito, ainda que de inteiro teor, não podem ter valor acrescido sobre qualquer título, salvo os previstos nos itens 14.5.1, 14.5.2 e 14.6.', 'Orientação informativa', '', '', '', ''),
	(151, '14.10', 'Retificação simples', '56.91', '1.70', '2.27', '2.27', '63.15'),
	(152, '14.10.1', 'É vedado a cobrança de emolumentos em decorrência da prática de ato retificado, refeito ou renovado em razão de erro imputável aos respectivos notários e registradores.', 'Orientação informativa', '', '', '', ''),
	(153, '14.11', 'Pelos procedimentos administrativos de: reconhecimento de paternidade ou maternidade biológico ou socioafetivo, procedimento de alteração patronímico familiar, procedimento de retificação de registro civil incluindo os casos de alteração de prenome e do gênero de pessoa transgênero, procedimento de restauração de registro civil, e os demais procedimentos cujo o erro não seja do próprio oficial, incluindo todas as petições, requerimentos, tomada de depoimentos, remessa dos autos ao juízo competente, excluídas as certidões e as averbações respectivas.', '56.91', '1.70', '2.27', '2.27', '63.15'),
	(154, '14.12', 'Arquivamento, por folha do documento, os emolumentos serão:', '5.65', '0.16', '0.22', '0.22', '6.25'),
	(155, '14.13', 'Procedimento administrativo para o registro tardio - isento - para fins de compensação os emolumentos serão os do item 14.5.1.', '', '', '', '', ''),
	(156, '15.1', 'Prenotação de títulos', '34.82', '1.04', '1.39', '1.39', '38.64'),
	(157, '15.2', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado):', '', '', '', '', ''),
	(158, '15.2.1', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): Até R$ 6.772,25', '85.95', '2.57', '3.43', '3.43', '95.38'),
	(159, '15.2.2', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 6.772,26 a R$ 9.558,53', '107.78', '3.23', '4.31', '4.31', '119.63'),
	(160, '15.2.3', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 9.558,54 a R$ 11.948,15', '123.19', '3.69', '4.92', '4.92', '136.72'),
	(161, '15.2.4', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 11.948,16 a R$ 14.935,20', '154.16', '4.62', '6.16', '6.16', '171.10'),
	(162, '15.2.5', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 14.935,21 a R$ 18.669,00', '191.41', '5.74', '7.65', '7.65', '212.45'),
	(163, '15.2.6', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 18.669,01 a R$ 23.336,27', '239.71', '7.19', '9.58', '9.58', '266.06'),
	(164, '15.2.7', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 23.336,28 a R$ 29.170,32', '299.06', '8.97', '11.96', '11.96', '331.95'),
	(165, '15.2.8', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 29.170,33 a R$ 36.462,90', '373.84', '11.21', '14.95', '14.95', '414.95'),
	(166, '15.2.9', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 36.462,91 a R$ 45.578,61', '466.33', '13.98', '18.65', '18.65', '517.61'),
	(167, '15.2.10', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 45.578,62 a R$ 56.973,25', '582.84', '17.48', '23.31', '23.31', '646.94'),
	(168, '15.2.11', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 56.973,26 a R$ 71.216,58', '727.87', '21.83', '29.11', '29.11', '807.92'),
	(169, '15.2.12', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 71.216,59 a R$ 89.020,70', '910.55', '27.31', '36.42', '36.42', '1010.70'),
	(170, '15.2.13', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 89.020,71 a R$ 111.275,89', '1139.09', '34.17', '45.56', '45.56', '1264.38'),
	(171, '15.2.14', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 111.275,90 a R$ 139.094,85', '1423.00', '42.68', '56.91', '56.91', '1579.50'),
	(172, '15.2.15', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 139.094,86 a R$ 173.868,57', '1779.22', '53.37', '71.16', '71.16', '1974.91'),
	(173, '15.2.16', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 173.868,58 a R$ 217.335,72', '2223.58', '66.70', '88.94', '88.94', '2468.16'),
	(174, '15.2.17', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 217.335,73 a R$ 271.669,67', '2780.09', '83.40', '111.20', '111.20', '3085.89'),
	(175, '15.2.18', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 271.669,68 a R$ 339.587,11', '3472.89', '104.18', '138.91', '138.91', '3854.89'),
	(176, '15.2.19', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 339.587,12 a R$ 424.483,90', '4341.82', '130.25', '173.67', '173.67', '4819.41'),
	(177, '15.2.20', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 424.483,91 a R$ 530.604,89', '5428.25', '162.84', '217.12', '217.12', '6025.33'),
	(178, '15.2.21', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 530.604,90 a R$ 663.256,09', '6785.08', '203.55', '271.40', '271.40', '7531.43'),
	(179, '15.2.22', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 663.256,10 a R$ 829.070,12', '8480.80', '254.42', '339.23', '339.23', '9413.68'),
	(180, '15.2.23', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 829.070,13 a R$ 1.036.337,64', '10601.10', '318.03', '424.04', '424.04', '11767.21'),
	(181, '15.2.24', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 1.036.337,65 a R$ 1.295.422,07', '13251.30', '397.53', '530.05', '530.05', '14708.93'),
	(182, '15.2.25', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 1.295.422,08 a R$ 1.619.277,58', '14142.07', '424.26', '565.68', '565.68', '15697.69'),
	(183, '15.2.26', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 1.619.277,59 a R$ 1.910.747,55', '14566.27', '436.98', '582.65', '582.65', '16168.55'),
	(184, '15.2.27', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 1.910.747,56 a R$ 2.254.682,12', '15003.30', '450.09', '600.13', '600.13', '16653.65'),
	(185, '15.2.28', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 2.254.682,13 a R$ 2.660.524,90', '15453.43', '463.60', '618.13', '618.13', '17153.29'),
	(186, '15.2.29', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 2.660.524,91 a R$ 3.139.419,39', '15917.06', '477.51', '636.68', '636.68', '17667.93'),
	(187, '15.2.30', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 3.139.419,40 a R$ 3.704.514,87', '16394.56', '491.83', '655.78', '655.78', '18197.95'),
	(188, '15.2.31', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 3.704.514,88 a R$ 4.371.327,55', '16886.32', '506.58', '675.45', '675.45', '18743.80'),
	(189, '15.2.32', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 4.371.327,56 a R$ 5.158.166,53', '17392.98', '521.78', '695.71', '695.71', '19306.18'),
	(190, '15.2.33', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 5.158.166,54 a R$ 6.086.636,49', '17914.67', '537.44', '716.58', '716.58', '19885.27'),
	(191, '15.2.34', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 6.086.636,50 a R$ 7.182.231,05', '18452.17', '553.56', '738.08', '738.08', '20481.89'),
	(192, '15.2.35', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): De R$ 7.182.231,06 a R$ 8.475.032,67', '19005.72', '570.17', '760.22', '760.22', '21096.33'),
	(193, '15.2.36', 'Registro completo com as anotações e remissões de contrato, título ou documento com valor econômico declarado, trasladação na íntegra ou por extrato conforme requerido (sobre o valor declarado): Acima de R$ 8.475.032,67', '19575.85', '587.27', '783.03', '783.03', '21729.18'),
	(194, '15.3', 'Registro de título, contrato ou documento sem valor econômico, trasladação na íntegra ou por extrato conforme requerido:', '', '', '', '', ''),
	(195, '15.3.1', 'Registro de título, contrato ou documento sem valor econômico, trasladação na íntegra ou por extrato conforme requerido: Até uma página', '74.89', '2.24', '2.99', '2.99', '83.11'),
	(196, '15.3.2', 'Registro de título, contrato ou documento sem valor econômico, trasladação na íntegra ou por extrato conforme requerido: Por página que exceder', '19.78', '0.59', '0.79', '0.79', '21.95'),
	(197, '15.4', 'De contrato, estatuto ou qualquer outro constitutivo de sociedade, associação ou fundação com capital declarado ou fim econômico, serão sobrados os emolumentos do subitem 15.2.', 'Informar Valor da Transação', '', '', '', ''),
	(198, '15.5', 'Registro de contrato, estatuto, regimento interno ou qualquer outro ato constitutivo de sociedade, associação ou fundação sem capital declarado ou fim econômico serão de', '', '', '', '', ''),
	(199, '15.5.1', 'Até cinco páginas', '187.05', '5.61', '7.48', '7.48', '207.62'),
	(200, '15.5.2', 'Por página que exceder', '8.73', '0.26', '0.34', '0.34', '9.67'),
	(201, '15.6', 'Registro de jornais, periódicos, oficinas impressoras, empresas de radiodifusão e agências de notícias, pelo processamento e pela matrícula', '499.47', '14.98', '19.97', '19.97', '554.39'),
	(202, '15.7', 'Registro de termos de abertura e encerramento em livros de contabilidade ou ato de sociedade civil, associação ou fundação, balanço patrimonial, inclusive registro de atas', '', '', '', '', ''),
	(203, '15.7.1', 'Até cinco folhas', '81.96', '2.45', '3.27', '3.27', '90.95'),
	(204, '15.7.2', 'Por folha que exceder', '8.61', '0.25', '0.34', '0.34', '9.54'),
	(205, '15.7.3', 'Quando a inscrição for solicitada por meio de Sped, PDF ou outro formato eletrônico autorizado para escrituração contábil, por livro digital:', '81.96', '2.45', '3.27', '3.27', '90.95'),
	(206, '15.8', 'Registro para fins de notificação extrajudicial, por destinatário. (Alterado pela Lei nº 9.490, de 04/11/11)', '66.93', '2.00', '2.67', '2.67', '74.27'),
	(207, '15.8.1', 'Diligência para notificação extrajudicial em zona urbana, por destinatário, até o limite de 03.', '28.51', '0.85', '1.14', '1.14', '31.64'),
	(208, '15.8.1.1', 'Acima de 03 (três), acrescer, por diligência extra solicitada.', '26.60', '0.79', '1.06', '1.06', '29.51'),
	(209, '15.8.1.2', 'Diligência para notificação extrajudicial em zona rural será cobrado do apresentante, por Km percorrido em cada diligência', '2.70', '0.10', '0.10', '0.10', '3.00'),
	(210, '15.8.2', 'Certidão à margem do registro, por destinatário. (Incluído pela Lei nº 9.490, de 04/11/11)', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(211, '15.8.3', 'Por folha que exceder a uma no registro do item 15.8', '8.22', '0.24', '0.32', '0.32', '9.10'),
	(212, '15.9', 'Averbação de documento para integrar, modificar ou cancelar registro, sem valor patrimonial:', '', '', '', '', ''),
	(213, '15.9.1', 'Pela primeira folha', '83.50', '2.50', '3.34', '3.34', '92.68'),
	(214, '15.9.2', 'Por folha que exceder', '17.47', '0.52', '0.69', '0.69', '19.37'),
	(215, '15.10', 'Das certidões:', '', '', '', '', ''),
	(216, '15.10.1', 'Com uma folha', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(217, '15.10.2', 'Por folha acrescida além da primeira, mais', '8.73', '0.26', '0.34', '0.34', '9.67'),
	(218, '15.10.3', 'REVOGADO', '', '', '', '', ''),
	(219, '15.11', 'Das buscas:', '', '', '', '', ''),
	(220, '15.11.1', 'Das buscas: Até dois anos', '6.56', '0.20', '0.26', '0.26', '7.28'),
	(221, '15.11.2', 'Das buscas: Até cinco anos', '10.92', '0.32', '0.43', '0.43', '12.10'),
	(222, '15.11.3', 'Das buscas: Até dez anos', '17.47', '0.52', '0.69', '0.69', '19.37'),
	(223, '15.11.4', 'Das buscas: Até quinze anos', '22.23', '0.66', '0.88', '0.88', '24.65'),
	(224, '15.11.5', 'Das buscas: Até vinte anos', '28.51', '0.85', '1.14', '1.14', '31.64'),
	(225, '15.11.6', 'Das buscas: Até trinta anos', '37.26', '1.11', '1.49', '1.49', '41.35'),
	(226, '15.11.7', 'Das buscas: Até cinquenta anos', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(227, '15.11.8', 'Das buscas: Acima de cinquenta anos', '56.91', '1.70', '2.27', '2.27', '63.15'),
	(228, '15.11.9', 'Se indicados dia, mês e ano da prática do ato, ou número e livro corretos do ato não serão cobradas buscas.', '', '', '', '', ''),
	(229, '15.12', 'No registro do contrato de aluguel, arrendamento ou prestação de serviços os emolumentos serão os do item 15.2:', '', '', '', '', ''),
	(230, '15.12.1', 'Se o contrato de aluguel, arrendamento ou prestação de serviços for por período inferior a doze meses, a base de cálculo dos emolumentos será igual a soma de todas as mensalidades.', 'Informar Valor da Transação', '', '', '', ''),
	(231, '15.12.2', 'Se o contrato de aluguel, arrendamento ou prestação de serviços for por período igual ou superior a doze meses ou ainda por prazo indeterminado, a base de cálculo será a soma de doze meses de mensalidade.', 'Informar Valor da Transação', '', '', '', ''),
	(232, '15.13', 'Averbação de documento para integrar, modificar ou cancelar registro, com valor patrimonial, os emolumentos serão os mesmos do item 15.2 e subitens 15.2.1 a 15.2.47, reduzidos em cinquenta por cento, com base no valor do ato.', 'Informar Valor da Transação', '', '', '', ''),
	(233, '15.14', 'Registro do recibo de transferência de propriedade de veículo do DETRAN, os emolumentos serão.', '74.89', '2.24', '2.99', '2.99', '83.11'),
	(234, '15.15', 'No Registro de contrato de comodato os emolumentos serão:', '74.89', '2.24', '2.99', '2.99', '83.11'),
	(235, '15.16', 'Apostila de Haia - certificação de documentos produzidos em território nacional e destinados a produzir efeitos em Países partes da Convenção – os emolumentos serão.', '114.46', '3.43', '4.57', '4.57', '127.03'),
	(236, '15.16.1', 'A Apostila de Haia será cobrada em função de uma para cada documento apresentado, não podendo ser realizada em bloco. A cobrança é única, pelo valor referenciado na tabela, não se alterando em função do conteúdo econômico ou do número de páginas. - Nota informativa', '', '', '', '', ''),
	(237, '15.17', 'Registro, por folha ou imagem, de conjunto de documentos de arquivo, sem valor econômico imediato, para conservação pura, recepcionados eletronicamente, com um mínimo de 50 folhas ou imagens, objeto de um único ato e número de ordem de protocolo, registrado também sob um único número de ordem de registro.', '0.65', '0.05', '0.02', '0.02', '0.74'),
	(238, '15.18', 'Registro de conjunto de documentos de arquivo, sem valor econômico imediato, para conservação pura, recepcionados fisicamente objeto de um único ato e número de ordem de protocolo, registrado também sob um único número de ordem de registro, até o número de 25 folhas.', '199.25', '5.97', '7.96', '7.96', '221.14'),
	(239, '15.18.1', 'Por folha ou imagem que acrescer ao número de 25.', '1.28', '0.05', '0.05', '0.05', '1.43'),
	(240, '15.19', 'Registro de editais de licitações e procedimentos licitatórios promovidas pela Administração Pública Direta, Indireta ou Fundacional, em qualquer de suas modalidades, inclusive, cartas-convites, e das respectivas propostas e demais atos, os emolumentos cobrados serão os mesmos do item 15.18 e 15.18.1.', '', '', '', '', ''),
	(241, '15.20', 'Em contratos de valor econômico, no qual não se possa aferir imediatamente o montante desse conteúdo, deve ser estimado razoavelmente a expressão econômica contratual para fins de cobrança de emolumentos. Caso não haja concordância com o valor mínimo estimado pela parte, poderá ser suscitada dúvida ao juízo competente. - Nota explicativa', '', '', '', '', ''),
	(242, '15.21', 'No registro de contrato de alienação fiduciária, leasing ou reserva de domínio, os emolumentos cobrados serão os do item 15.2 (sobre o valor financiado).', 'Informar Valor da Transação', '', '', '', ''),
	(243, '15.22', 'Arquivamento, por folha do documento, os emolumentos serão:', '5.65', '0.16', '0.22', '0.22', '6.25'),
	(244, '16.1', 'Prenotações de título levado a registro', '35.45', '1.06', '1.41', '1.41', '39.33'),
	(245, '16.2', 'Matrícula de imóveis no Registro Geral.', '83.50', '2.50', '3.34', '3.34', '92.68'),
	(246, '16.2.1', 'Comunicação ao serviço registral de origem os emolumentos serão.', '5.65', '0.16', '0.22', '0.22', '6.25'),
	(247, '16.3', 'Registros de atos com valor declarado:', '', '', '', '', ''),
	(248, '16.3.1', 'Registros de atos com valor declarado: Até R$ 5.417,79', '90.82', '2.72', '3.63', '3.63', '100.80'),
	(249, '16.3.2', 'Registros de atos com valor declarado: De R$ 5.417,80 a R$ 7.043,13', '114.46', '3.43', '4.57', '4.57', '127.03'),
	(250, '16.3.3', 'Registros de atos com valor declarado: De R$ 7.043,14 a R$ 8.803,91', '129.61', '3.88', '5.18', '5.18', '143.85'),
	(251, '16.3.4', 'Registros de atos com valor declarado: De R$ 8.803,92 a R$ 11.004,89', '160.84', '4.82', '6.43', '6.43', '178.52'),
	(252, '16.3.5', 'Registros de atos com valor declarado: De R$ 11.004,90 a R$ 13.756,12', '200.01', '6.00', '8.00', '8.00', '222.01'),
	(253, '16.3.6', 'Registros de atos com valor declarado: De R$ 13.756,13 a R$ 17.195,14', '250.77', '7.52', '10.03', '10.03', '278.35'),
	(254, '16.3.7', 'Registros de atos com valor declarado: De R$ 17.195,15 a R$ 21.493,91', '314.61', '9.43', '12.58', '12.58', '349.20'),
	(255, '16.3.8', 'Registros de atos com valor declarado: De R$ 21.493,92 a R$ 26.867,38', '393.74', '11.81', '15.74', '15.74', '437.03'),
	(256, '16.3.9', 'Registros de atos com valor declarado: De R$ 26.867,39 a R$ 33.584,23', '490.35', '14.71', '19.61', '19.61', '544.28'),
	(257, '16.3.10', 'Registros de atos com valor declarado: De R$ 33.584,24 a R$ 41.980,29', '613.67', '18.41', '24.54', '24.54', '681.16'),
	(258, '16.3.11', 'Registros de atos com valor declarado: De R$ 41.980,30 a R$ 52.475,34', '767.83', '23.03', '30.71', '30.71', '852.28'),
	(259, '16.3.12', 'Registros de atos com valor declarado: De R$ 52.475,35 a R$ 65.594,16', '958.98', '28.76', '38.35', '38.35', '1064.44'),
	(260, '16.3.13', 'Registros de atos com valor declarado: De R$ 65.594,17 a R$ 81.992,73', '1198.69', '35.96', '47.94', '47.94', '1330.53'),
	(261, '16.3.14', 'Registros de atos com valor declarado: De R$ 81.992,74 a R$ 102.490,90', '1497.89', '44.93', '59.91', '59.91', '1662.64'),
	(262, '16.3.15', 'Registros de atos com valor declarado: De R$ 102.490,91 a R$ 128.113,62', '1871.85', '56.15', '74.87', '74.87', '2077.74'),
	(263, '16.3.16', 'Registros de atos com valor declarado: De R$ 128.113,63 a R$ 160.142,01', '2340.23', '70.20', '93.60', '93.60', '2597.63'),
	(264, '16.3.17', 'Registros de atos com valor declarado: De R$ 160.142,02 a R$ 200.177,52', '2925.38', '87.76', '117.01', '117.01', '3247.16'),
	(265, '16.3.18', 'Registros de atos com valor declarado: De R$ 200.177,53 a R$ 250.221,91', '3657.62', '109.72', '146.30', '146.30', '4059.94'),
	(266, '16.3.19', 'Registros de atos com valor declarado: De R$ 250.221,92 a R$ 312.777,38', '4570.37', '137.11', '182.81', '182.81', '5073.10'),
	(267, '16.3.20', 'Registros de atos com valor declarado: De R$ 312.777,39 a R$ 390.971,73', '5713.95', '171.41', '228.55', '228.55', '6342.46'),
	(268, '16.3.21', 'Registros de atos com valor declarado: De R$ 390.971,74 a R$ 488.714,66', '7141.70', '214.25', '285.66', '285.66', '7927.27'),
	(269, '16.3.22', 'Registros de atos com valor declarado: De R$ 488.714,67 a R$ 610.893,32', '8927.21', '267.81', '357.08', '357.08', '9909.18'),
	(270, '16.3.23', 'Registros de atos com valor declarado: De R$ 610.893,33 a R$ 763.616,66', '11159.66', '334.78', '446.38', '446.38', '12387.20'),
	(271, '16.3.24', 'Registros de atos com valor declarado: De R$ 763.616,67 a R$ 954.520,82', '13251.30', '397.53', '530.05', '530.05', '14708.93'),
	(272, '16.3.25', 'Registros de atos com valor declarado: De R$ 954.520,83 a R$ 1.193.151,05', '14142.07', '424.26', '565.68', '565.68', '15697.69'),
	(273, '16.3.26', 'Registros de atos com valor declarado: De R$ 1.193.151,06 a R$ 1.431.781,26', '14566.27', '436.98', '582.65', '582.65', '16168.55'),
	(274, '16.3.27', 'Registros de atos com valor declarado: De R$ 1.431.781,27 a R$ 1.718.137,50', '15003.30', '450.09', '600.13', '600.13', '16653.65'),
	(275, '16.3.28', 'Registros de atos com valor declarado: De R$ 1.718.137,51 a R$ 2.061.765,00', '15453.43', '463.60', '618.13', '618.13', '17153.29'),
	(276, '16.3.29', 'Registros de atos com valor declarado: De R$ 2.061.765,01 a R$ 2.474.118,03', '15917.06', '477.51', '636.68', '636.68', '17667.93'),
	(277, '16.3.30', 'Registros de atos com valor declarado: De R$ 2.474.118,04 a R$ 2.968.941,63', '16394.56', '491.83', '655.78', '655.78', '18197.95'),
	(278, '16.3.31', 'Registros de atos com valor declarado: De R$ 2.968.941,64 a R$ 3.562.729,96', '16886.32', '506.58', '675.45', '675.45', '18743.80'),
	(279, '16.3.32', 'Registros de atos com valor declarado: De R$ 3.562.729,97 a R$ 4.275.275,95', '17392.98', '521.78', '695.71', '695.71', '19306.18'),
	(280, '16.3.33', 'Registros de atos com valor declarado: De R$ 4.275.275,96 a R$ 5.130.331,15', '17914.67', '537.44', '716.58', '716.58', '19885.27'),
	(281, '16.3.34', 'Registros de atos com valor declarado: De R$ 5.130.331,16 a R$ 6.156.397,36', '18452.17', '553.56', '738.08', '738.08', '20481.89'),
	(282, '16.3.35', 'Registros de atos com valor declarado: De R$ 6.156.397,37 a R$ 7.387.676,85', '19005.72', '570.17', '760.22', '760.22', '21096.33'),
	(283, '16.3.36', 'Registros de atos com valor declarado: Acima De R$ 7.387.676,85', '19575.85', '587.27', '783.03', '783.03', '21729.18'),
	(284, '16.3.37', 'Os emolumentos do registro do contrato de promessa de compra e venda serão os mesmos do item 16.3, reduzidos em cinquenta por cento.', 'Informar Valor da Transação', '', '', '', ''),
	(285, '16.4', 'Registro de atos sem valor declarado.', '183.17', '5.49', '7.32', '7.32', '203.30'),
	(286, '16.5', 'Registro de loteamento ou desmembramento urbano ou rural, pelo processamento, registro na matrícula de origem – emolumentos por unidade, limitado ao valor máximo do art. 37 desta Lei.', '132.06', '3.96', '5.28', '5.28', '146.58'),
	(287, '16.6', 'Registro de incorporação imobiliária, pelo processamento, registro na matrícula de origem - emolumentos por unidade, limitado ao valor máximo do art. 37 desta Lei.', '132.06', '3.96', '5.28', '5.28', '146.58'),
	(288, '16.7', 'Registro de convenção de condomínio, qualquer que seja o número de unidades, incluído o valor das averbações necessárias. (Alterado pela Lei nº 9.490, de 04/11/11)', '263.99', '7.91', '10.55', '10.55', '293.00'),
	(289, '16.7.1', 'Registro de especificação e instituição de condomínio, independente do número de unidades. (Incluído pela Lei nº 9.490, de 04/11/11)', '132.06', '3.96', '5.28', '5.28', '146.58'),
	(290, '16.8', 'Pelo registro de pacto antenupcial', '92.37', '2.77', '3.69', '3.69', '102.52'),
	(291, '16.9', 'Pelos registros torrens com valor declarado:', '', '', '', '', ''),
	(292, '16.9.1', 'Pelos registros torrens com valor declarado: Até R$ 5.417,79', '45.61', '1.36', '1.82', '1.82', '50.61'),
	(293, '16.9.2', 'Pelos registros torrens com valor declarado: De R$ 5.417,80 a R$ 7.043,13', '56.91', '1.70', '2.27', '2.27', '63.15'),
	(294, '16.9.3', 'Pelos registros torrens com valor declarado: De R$ 7.043,14 a R$ 8.803,91', '64.75', '1.94', '2.58', '2.58', '71.85'),
	(295, '16.9.4', 'Pelos registros torrens com valor declarado: De R$ 8.803,92 a R$ 11.004,89', '80.04', '2.40', '3.20', '3.20', '88.84'),
	(296, '16.9.5', 'Pelos registros torrens com valor declarado: De R$ 11.004,90 a R$ 13.756,12', '100.07', '3.00', '4.00', '4.00', '111.07'),
	(297, '16.9.6', 'Pelos registros torrens com valor declarado: De R$ 13.756,13 a R$ 17.195,14', '125.12', '3.75', '5.00', '5.00', '138.87'),
	(298, '16.9.7', 'Pelos registros torrens com valor declarado: De R$ 17.195,15 a R$ 21.493,91', '157.24', '4.71', '6.28', '6.28', '174.51'),
	(299, '16.9.8', 'Pelos registros torrens com valor declarado: De R$ 21.493,92 a R$ 26.867,38', '196.94', '5.90', '7.87', '7.87', '218.58'),
	(300, '16.9.9', 'Pelos registros torrens com valor declarado: De R$ 26.867,39 a R$ 33.584,23', '245.37', '7.36', '9.81', '9.81', '272.35'),
	(301, '16.9.10', 'Pelos registros torrens com valor declarado: De R$ 33.584,24 a R$ 41.980,29', '306.65', '9.19', '12.26', '12.26', '340.36'),
	(302, '16.9.11', 'Pelos registros torrens com valor declarado: De R$ 41.980,30 a R$ 52.475,34', '383.85', '11.51', '15.35', '15.35', '426.06'),
	(303, '16.9.12', 'Pelos registros torrens com valor declarado: De R$ 52.475,35 a R$ 65.594,16', '479.69', '14.39', '19.18', '19.18', '532.44'),
	(304, '16.9.13', 'Pelos registros torrens com valor declarado: De R$ 65.594,17 a R$ 81.992,73', '599.41', '17.98', '23.97', '23.97', '665.33'),
	(305, '16.9.14', 'Pelos registros torrens com valor declarado: De R$ 81.992,74 a R$ 102.490,90', '748.82', '22.46', '29.95', '29.95', '831.18'),
	(306, '16.9.15', 'Pelos registros torrens com valor declarado: De R$ 102.490,91 a R$ 128.113,62', '935.86', '28.07', '37.43', '37.43', '1038.79'),
	(307, '16.9.16', 'Pelos registros torrens com valor declarado: De R$ 128.113,63 a R$ 160.142,01', '1170.30', '35.10', '46.81', '46.81', '1299.02'),
	(308, '16.9.17', 'Pelos registros torrens com valor declarado: De R$ 160.142,02 a R$ 200.177,52', '1462.56', '43.87', '58.50', '58.50', '1623.43'),
	(309, '16.9.18', 'Pelos registros torrens com valor declarado: De R$ 200.177,53 a R$ 250.221,91', '1828.82', '54.86', '73.15', '73.15', '2029.98'),
	(310, '16.9.19', 'Pelos registros torrens com valor declarado: De R$ 250.221,92 a R$ 312.777,38', '2285.25', '68.55', '91.41', '91.41', '2536.62'),
	(311, '16.9.20', 'Pelos registros torrens com valor declarado: De R$ 312.777,39 a R$ 390.971,73', '2857.04', '85.71', '114.28', '114.28', '3171.31'),
	(312, '16.9.21', 'Pelos registros torrens com valor declarado: De R$ 390.971,74 a R$ 488.714,66', '3570.53', '107.11', '142.82', '142.82', '3963.28'),
	(313, '16.9.22', 'Pelos registros torrens com valor declarado: De R$ 488.714,67 a R$ 610.893,32', '4463.73', '133.91', '178.54', '178.54', '4954.72'),
	(314, '16.9.23', 'Pelos registros torrens com valor declarado: De R$ 610.893,33 a R$ 763.616,66', '5580.09', '167.40', '223.20', '223.20', '6193.89'),
	(315, '16.9.24', 'Pelos registros torrens com valor declarado: De R$ 763.616,67 a R$ 954.520,82', '6747.57', '202.42', '269.90', '269.90', '7489.79'),
	(316, '16.9.25', 'Pelos registros torrens com valor declarado: De R$ 954.520,83 a R$ 1.193.151,05', '7073.35', '212.20', '282.93', '282.93', '7851.41'),
	(317, '16.9.26', 'Pelos registros torrens com valor declarado: De R$ 1.193.151,06 a R$ 1.431.781,26', '7285.70', '218.57', '291.42', '291.42', '8087.11'),
	(318, '16.9.27', 'Pelos registros torrens com valor declarado: De R$ 1.431.781,27 a R$ 1.718.137,50', '7504.35', '225.13', '300.17', '300.17', '8329.82'),
	(319, '16.9.28', 'Pelos registros torrens com valor declarado: De R$ 1.718.137,51 a R$ 2.061.765,00', '7729.42', '231.88', '309.17', '309.17', '8579.64'),
	(320, '16.9.29', 'Pelos registros torrens com valor declarado: De R$ 2.061.765,01 a R$ 2.474.118,03', '7961.29', '238.83', '318.45', '318.45', '8837.02'),
	(321, '16.9.30', 'Pelos registros torrens com valor declarado: De R$ 2.474.118,04 a R$ 2.968.941,63', '8200.11', '246.00', '328.00', '328.00', '9102.11'),
	(322, '16.9.31', 'Pelos registros torrens com valor declarado: De R$ 2.968.941,64 a R$ 3.562.729,96', '8445.99', '253.37', '337.83', '337.83', '9375.02'),
	(323, '16.9.32', 'Pelos registros torrens com valor declarado: De R$ 3.562.729,97 a R$ 4.275.275,95', '8699.57', '260.98', '347.98', '347.98', '9656.51'),
	(324, '16.9.33', 'Pelos registros torrens com valor declarado: De R$ 4.275.275,96 a R$ 5.130.331,15', '8960.49', '268.81', '358.41', '358.41', '9946.12'),
	(325, '16.9.34', 'Pelos registros torrens com valor declarado: De R$ 5.130.331,16 a R$ 6.156.397,36', '9229.23', '276.87', '369.16', '369.16', '10244.42'),
	(326, '16.9.35', 'Pelos registros torrens com valor declarado: De R$ 6.156.397,37 a R$ 7.387.676,85', '9506.07', '285.18', '380.24', '380.24', '10551.73'),
	(327, '16.9.36', 'Pelos registros torrens com valor declarado: Acima De R$ 7.387.676,85', '9791.39', '293.74', '391.65', '391.65', '10868.43'),
	(328, '16.10', 'Pelo registro completo de emissão de debêntures, serão cobrados os mesmos emolumentos do item 16.3 e de seus subitens.', 'Informar Valor da Transação', '', '', '', ''),
	(329, '16.11', 'Pelo registro completo de bens de família (sobre o valor do bem):', '', '', '', '', ''),
	(330, '16.11.1', 'Pelo registro completo de bens de família (sobre o valor do bem): Até R$ 5.417,79', '18.24', '0.54', '0.72', '0.72', '20.22'),
	(331, '16.11.2', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 5.417,80 a R$ 7.043,13', '22.74', '0.68', '0.90', '0.90', '25.22'),
	(332, '16.11.3', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 7.043,14 a R$ 8.803,91', '26.08', '0.78', '1.04', '1.04', '28.94'),
	(333, '16.11.4', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 8.803,92 a R$ 11.004,89', '32.37', '0.97', '1.29', '1.29', '35.92'),
	(334, '16.11.5', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 11.004,90 a R$ 13.756,12', '40.21', '1.20', '1.60', '1.60', '44.61'),
	(335, '16.11.6', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 13.756,13 a R$ 17.195,14', '49.97', '1.49', '1.99', '1.99', '55.44'),
	(336, '16.11.7', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 17.195,15 a R$ 21.493,91', '62.82', '1.88', '2.51', '2.51', '69.72'),
	(337, '16.11.8', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 21.493,92 a R$ 26.867,38', '78.87', '2.36', '3.15', '3.15', '87.53'),
	(338, '16.11.9', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 26.867,39 a R$ 33.584,23', '98.28', '2.94', '3.93', '3.93', '109.08'),
	(339, '16.11.10', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 33.584,24 a R$ 41.980,29', '122.56', '3.67', '4.90', '4.90', '136.03'),
	(340, '16.11.11', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 41.980,30 a R$ 52.475,34', '153.51', '4.60', '6.14', '6.14', '170.39'),
	(341, '16.11.12', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 52.475,35 a R$ 65.594,16', '191.80', '5.75', '7.67', '7.67', '212.89'),
	(342, '16.11.13', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 65.594,17 a R$ 81.992,73', '239.71', '7.19', '9.58', '9.58', '266.06'),
	(343, '16.11.14', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 81.992,74 a R$ 102.490,90', '299.58', '8.98', '11.98', '11.98', '332.52'),
	(344, '16.11.15', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 102.490,91 a R$ 128.113,62', '374.60', '11.23', '14.98', '14.98', '415.79'),
	(345, '16.11.16', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 128.113,63 a R$ 160.142,01', '467.86', '14.03', '18.71', '18.71', '519.31'),
	(346, '16.11.17', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 160.142,02 a R$ 200.177,52', '585.02', '17.55', '23.40', '23.40', '649.37'),
	(347, '16.11.18', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 200.177,53 a R$ 250.221,91', '731.73', '21.95', '29.26', '29.26', '812.20'),
	(348, '16.11.19', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 250.221,92 a R$ 312.777,38', '914.02', '27.42', '36.56', '36.56', '1014.56'),
	(349, '16.11.20', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 312.777,39 a R$ 390.971,73', '1142.95', '34.28', '45.71', '45.71', '1268.65'),
	(350, '16.11.21', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 390.971,74 a R$ 488.714,66', '1428.40', '42.85', '57.13', '57.13', '1585.51'),
	(351, '16.11.22', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 488.714,67 a R$ 610.893,32', '1785.53', '53.56', '71.42', '71.42', '1981.93'),
	(352, '16.11.23', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 610.893,33 a R$ 763.616,66', '2232.06', '66.96', '89.28', '89.28', '2477.58'),
	(353, '16.11.24', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 763.616,67 a R$ 954.520,82', '2699.03', '80.97', '107.96', '107.96', '2995.92'),
	(354, '16.11.25', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 954.520,83 a R$ 1.193.151,05', '2829.16', '84.87', '113.16', '113.16', '3140.35'),
	(355, '16.11.26', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 1.193.151,06 a R$ 1.431.781,26', '2914.08', '87.42', '116.56', '116.56', '3234.62'),
	(356, '16.11.27', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 1.431.781,27 a R$ 1.718.137,50', '3001.43', '90.04', '120.05', '120.05', '3331.57'),
	(357, '16.11.28', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 1.718.137,51 a R$ 2.061.765,00', '3091.49', '92.74', '123.65', '123.65', '3431.53'),
	(358, '16.11.29', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 2.061.765,01 a R$ 2.474.118,03', '3184.23', '95.52', '127.36', '127.36', '3534.47'),
	(359, '16.11.30', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 2.474.118,04 a R$ 2.968.941,63', '3279.82', '98.39', '131.19', '131.19', '3640.59'),
	(360, '16.11.31', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 2.968.941,64 a R$ 3.562.729,96', '3378.22', '101.34', '135.12', '135.12', '3749.80'),
	(361, '16.11.32', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 3.562.729,97 a R$ 4.275.275,95', '3479.44', '104.38', '139.17', '139.17', '3862.16'),
	(362, '16.11.33', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 4.275.275,96 a R$ 5.130.331,15', '3583.88', '107.51', '143.35', '143.35', '3978.09'),
	(363, '16.11.34', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 5.130.331,16 a R$ 6.156.397,36', '3691.41', '110.74', '147.65', '147.65', '4097.45'),
	(364, '16.11.35', 'Pelo registro completo de bens de família (sobre o valor do bem): De R$ 6.156.397,37 a R$ 7.387.676,85', '3802.14', '114.06', '152.08', '152.08', '4220.36'),
	(365, '16.11.36', 'Pelo registro completo de bens de família (sobre o valor do bem): Acima De R$ 7.387.676,85', '3916.22', '117.48', '156.64', '156.64', '4346.98'),
	(366, '16.12', 'Inscrição, registro ou averbação de penhora (sobre o valor do bem ou da execução se for menor e, não constando, sobre o valor da causa), os emolumentos serão os do item 16.11, aplicando-se a regra do item 16.31', 'Informar Valor da Transação', '', '', '', ''),
	(367, '16.13', 'Pelo registro de cédula de crédito rural, do produto rural e demais nominadas rurais no livro 3 do Registro de Imóveis, conforme Lei de Registros Públicos, os emolumentos serão os mesmos do item 16.9.', 'Informar Valor da Transação', '', '', '', ''),
	(368, '16.13.1', 'Por cada registro das garantias reais ou gravames decorrentes de cédula de crédito rural, do produto rural e demais nominadas rurais no registro de imóveis, os emolumentos serão os mesmos do item 16.45', 'Informar Valor da Transação', '', '', '', ''),
	(369, '16.13.2', 'As averbações com valor declarado das cédulas rurais e de produto rural, e as demais nominadas rurais, os emolumentos serão os mesmos do item 16.9', 'Informar Valor da Transação', '', '', '', ''),
	(370, '16.14', 'Pelo registro de cédula de crédito industrial e de crédito à exportação que não sejam nominadas rurais, no livro 3 de Registro de Imóveis, conforme Lei de Registros Públicos, os emolumentos serão os mesmos do item 16.3.', 'Informar Valor da Transação', '', '', '', ''),
	(371, '16.14.1', 'Por cada registro das garantias reais ou gravames decorrentes de cédula de crédito industrial e de crédito a exportação, que não sejam de natureza rural, no Registro de Imóveis, conforme Lei de Registros Públicos, os emolumentos serão os mesmos do item 16.3.', 'Informar Valor da Transação', '', '', '', ''),
	(372, '16.14.2', 'Pelo registro de cédula de crédito comercial, que não seja de natureza rural, no livro 3 de Registro de Imóveis, conforme Lei de Registros Públicos, os emolumentos serão os mesmos do item 16.9.', 'Informar Valor da Transação', '', '', '', ''),
	(373, '16.14.3', 'Averbação com valor declarado de cédula de crédito industrial e de crédito à exportação e respectivos gravames os emolumentos serão os mesmos do item 16.9.', 'Informar Valor da Transação', '', '', '', ''),
	(374, '16.14.4', 'Averbação com valor declarado de cédula de crédito comercial e de crédito bancário, e respectivos gravames, os emolumentos serão os mesmos do item 16.11.', 'Informar Valor da Transação ', '', '', '', ''),
	(375, '16.15', 'Revogado pela Lei nº 9.490, de 04/11/11, pub.D.O. 04/11/11', '', '', '', '', ''),
	(376, '16.15.1', 'Revogado pela Lei nº 9.490, de 04/11/11, pub.D.O. 04/11/11', '', '', '', '', ''),
	(377, '16.15.2', 'Por cada registro das garantias reais ou gravames decorrentes de cédula de crédito comercial e de crédito bancário, que não sejam de natureza rural, no Registro de Imóveis, conforme Lei de Registros Públicos, os emolumentos serão os mesmos do item 16.9.', '', '', '', '', ''),
	(378, '16.15.3', 'Para averbação de endosso de cédulas, os emolumentos serão cobrados com base no item 16.11 da tabela, tomando-se como base para apuração dos emolumentos, o mesmo valor do título endossado, mesmo que no endosso não conste expressamente tal informação, deduzindo-se o valor de quitação parcial, se for o caso (desde que averbada).', '', '', '', '', ''),
	(379, '16.15.4', 'Averbação de cédulas sem valor declarado, os emolumentos serão.', '109.90', '3.29', '4.39', '4.39', '121.97'),
	(380, '16.16', 'Ao registro e à averbação referentes à aquisição da casa própria, em que seja parte cooperativa habitacional ou entidade assemelhada, serão considerados, para efeito de cálculo, de emolumentos, como um ato apenas, de acordo com o disposto no § 1º do art. 290, da Lei nº 6.015, de 31 de dezembro de 1973:', '', '', '', '', ''),
	(381, '16.16.1', 'Até R$ 13.544,47', '22.23', '0.66', '0.88', '0.88', '24.65'),
	(382, '16.16.2', 'De R$ 13.544,48 a R$ 27.088,96', '32.89', '0.98', '1.31', '1.31', '36.49'),
	(383, '16.16.3', 'De R$ 27.088,97 a R$ 54.177,92', '66.03', '1.98', '2.64', '2.64', '73.29'),
	(384, '16.16.4', 'De R$ 54.177,93 a R$ 108.355,83', '132.06', '3.96', '5.28', '5.28', '146.58'),
	(385, '16.16.5', 'De R$ 108.355,84 a R$ 216.711,66', '263.99', '7.91', '10.55', '10.55', '293.00'),
	(386, '16.16.6', 'Acima De R$ 216.711,66', '307.67', '9.23', '12.30', '12.30', '341.50'),
	(387, '16.17', 'Nos demais programas de interesse social, executados pelas Companhias de Habitação Popular - COHABs ou entidades assemelhadas, o valor dos emolumentos e das custas devidos por atos de aquisição de imóveis e de averbação de construção conforme § 2º do art. 290, da Lei nº 6.015, de 31 de dezembro de 1973, serão de', '76.82', '2.30', '3.07', '3.07', '85.26'),
	(388, '16.18', 'Os emolumentos devidos ao Registro de Imóveis, nos atos relacionados com à aquisição imobiliária para fins residenciais, oriunda de programas e convênios com a União, Estados, Distrito Federal e Municípios, para a construção de habitações populares destinadas a famílias de baixa renda, pelo sistema de mutirão e autoconstrução orientada, serão reduzidos a vinte por cento da tabela cartorária normal, considerando o imóvel será limitado a até sessenta e nove metros quadrados de área construída, em terreno de até duzentos e cinquenta metros quadrados. (§ 4º do art. 290 da Lei nº 6.015, de 31 de dezembro de 1973).', '', '', '', '', ''),
	(389, '16.19', 'Serão aplicadas as isenções e reduções de emolumentos previstas na Lei n.º 11.977, de 7 de julho de 2009 (redação alterada pela Lei n.º 9.755/2013)', '', '', '', '', ''),
	(390, '16.19.1', 'Revogado pela Lei n.º 9.755/2013', '', '', '', '', ''),
	(391, '16.19.2', 'Revogado pela Lei n.º 9.755/2013', '', '', '', '', ''),
	(392, '16.19.3', 'Revogado pela Lei n.º 9.755/2013', '', '', '', '', ''),
	(393, '16.20', 'Revogado pela Lei n.º 9.755/2013', '', '', '', '', ''),
	(394, '16.20.1', 'Revogado pela Lei n.º 9.755/2013', '', '', '', '', ''),
	(395, '16.20.2', 'Revogado pela Lei n.º 9.755/2013', '', '', '', '', ''),
	(396, '16.21', 'Revogado pela Lei n.º 9.755/2013', '', '', '', '', ''),
	(397, '16.22', 'Averbação:', '', '', '', '', ''),
	(398, '16.22.1', 'De ato de qualquer natureza com valor declarado, os emolumentos serão os do item 16.9', 'Informar Valor da Transação', '', '', '', ''),
	(399, '16.22.2', 'De ato sem valor declarado', '109.90', '3.29', '4.39', '4.39', '121.97'),
	(400, '16.22.3', 'Das unidades integrantes do condomínio, os emolumentos serão os mesmos do item 16.9', 'Informar Valor da Transação', '', '', '', ''),
	(401, '16.22.4', 'Averbação da certificação de georreferenciamento atestada pelo INCRA, mediante o Sistema SIGEF', '456.10', '13.68', '18.24', '18.24', '506.26'),
	(402, '16.22.4.1', 'Averbação de Retificação de memorial descritivo decorrente de certificação de georreferenciamento junto ao sistema SIGEF/INCRA, os emolumentos serão calculados na tabela 16.9, com redução de ½ (um meio) na base de cálculo, aferida no valor da área total do imóvel, observado o item 16.27', '', '', '', '', ''),
	(403, '16.22.4.2', 'Averbação com fins de retificação quanto à solicitação de correção de algum dado no memorial descritivo georreferenciado já averbado na matrícula, sem inserção ou alteração de medida perimetral ou alteração de quantidade de área', '109.90', '3.29', '4.39', '4.39', '121.97'),
	(404, '16.22.5', 'Cancelamento de averbação', '109.90', '3.29', '4.39', '4.39', '121.97'),
	(405, '16.22.6', 'De desdobro ou unificação de imóveis, os emolumentos serão:', '132.06', '3.96', '5.28', '5.28', '146.58'),
	(406, '16.22.7', 'Após a averbação do procedimento de retificação com georreferenciamento (16.22.4.1), devidamente certificado junto ao sistema SIGEFINCRA (16.22.4), havendo alteração no memorial descritivo e mapa, deve ser encerrada a matrícula de origem (16.22.2), conforme art. 9º, § 5º do Decreto Federal nº 4.449/2002. Em seguida, aberta uma nova matrícula com a nova descrição (16.2) e providenciando-se a averbação de transporte de ônus (16.22.2) caso existente na matrícula primitiva, bem como a averbação (16.22.2) da confirmação do deferimento do SIGEF- INCRA, quanto ao envio da matrícula georreferenciada pelo Cartório de Registro de Imóveis competente, conforme artigo 16, da Instrução Normativa nº 77/2013 do INCRA- Orientação informativa', 'Orientação informativa', '', '', '', ''),
	(407, '16.22.8', 'Procedimento Administrativo de Retificação de imóvel rural sem georreferenciamento certificado pelo sistema SIGEF- INCRA, desde que dentro do prazo carencial permitido pela legislação competente ou de retificação de imóvel urbano,', '', '', '', '', ''),
	(408, '16.22.8.1', 'Retificação de imóvel rural para inserção ou alteração de medida perimetral ou de quantidade de área, sem georreferenciamento certificado pelo SIGEF-INCRA desde que dentro do prazo carencial permitido pela legislação ou retificação de imóvel urbano, os emolumentos serão calculados na tabela 16.9, com redução de 1/2 (um meio) da base de cálculo, aferida no valor da área total do imóvel, observado o item 16.27', '', '', '', '', ''),
	(409, '16.22.8.2', 'Averbação para fins de retificação de imóvel rural ou urbano, quanto à solicitação de correção de algum dado na medida perimetral ou mapa já retificado, sem georreferenciamento certificado pelo SIGEF-INCRA, desde que dentro do prazo carencial permitido pela legislação e que não contenha inserção, alteração de medida perimetral ou de quantidade de área', '109.90', '3.29', '4.39', '4.39', '121.97'),
	(410, '16.23', 'Pela intimação de promissório comprador de imóvel ou qualquer outra intimação em cumprimento de lei ou de determinação judicial inclusive edital', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(411, '16.24', 'Das certidões:', '', '', '', '', ''),
	(412, '16.24.1', 'Com uma folha', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(413, '16.24.2', 'Por folha acrescida além da primeira, mais', '8.73', '0.26', '0.34', '0.34', '9.67'),
	(414, '16.24.3', 'REVOGADO', '', '', '', '', ''),
	(415, '16.24.4', 'Certidões de inteiro teor, ônus e de ações reais e pessoais reipersecutórias e de cadeia dominial, com uma folha', '83.28', '2.49', '3.33', '3.33', '92.43'),
	(416, '16.24.4.1', 'Por folha acrescida além da primeira, mais', '8.30', '0.24', '0.33', '0.33', '9.20'),
	(417, '16.25', 'Das buscas:', '', '', '', '', ''),
	(418, '16.25.1', 'Das buscas: Até dois anos', '6.56', '0.20', '0.26', '0.26', '7.28'),
	(419, '16.25.2', 'Das buscas: Até cinco anos', '10.92', '0.32', '0.43', '0.43', '12.10'),
	(420, '16.25.3', 'Das buscas: Até dez anos', '17.47', '0.52', '0.69', '0.69', '19.37'),
	(421, '16.25.4', 'Das buscas: Até quinze anos', '22.23', '0.66', '0.88', '0.88', '24.65'),
	(422, '16.25.5', 'Das buscas: Até vinte anos', '28.51', '0.85', '1.14', '1.14', '31.64'),
	(423, '16.25.6', 'Das buscas: Até trinta anos', '37.26', '1.11', '1.49', '1.49', '41.35'),
	(424, '16.25.7', 'Das buscas: Até cinquenta anos', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(425, '16.25.8', 'Das buscas: Acima de cinquenta anos', '56.91', '1.70', '2.27', '2.27', '63.15'),
	(426, '16.25.9', 'Se indicados dia, mês e ano da prática do ato, ou número da matrícula, ou número de ordem corretos não serão cobradas buscas.', '', '', '', '', ''),
	(427, '16.26', 'Considera-se sem valor declarado, entre outros, as averbações referentes a separação judicial e divórcio, casamento, quitação de débito, e demolição.', '', '', '', '', ''),
	(428, '16.26.1', 'Considera-se com valor declarado a averbação de aditamento que implique alteração no valor da dívida ou da coisa. Sendo os emolumentos cobrados com base no valor da diferença entre o valor originário e o aditado no ato.', 'Orientação Informativa', '', '', '', ''),
	(429, '16.27', 'O registro de ato será calculado com base no valor declarado pelas partes ou com base na avaliação oficial da Fazenda Pública (o que for maior) ou, ainda, pelo preço de mercado apurado pelo Titular da Serventia, podendo utilizar-se do serviço de profissional idôneo, caso o valor declarado e a avaliação não sejam exigíveis ou forem com este incompatível. Poderá ainda, em se tratando de imóvel rural, utilizar a tabela do INCRA caso atualizada e compatível com o valor de mercado.', '', '', '', '', ''),
	(430, '16.27.1', 'O valor de mercado do imóvel rural ou urbano compreende o valor da terra nua atualizado, acrescido das benfeitorias, acessões e pertenças, ainda que não averbadas - Orientação Informativa.', '', '', '', '', ''),
	(431, '16.28', 'Nos condomínios de plano horizontal, considerase uma só unidade autônoma o apartamento e as garagens que o servem.', '', '', '', '', ''),
	(432, '16.29', 'Realizando-se mais de um registro ou averbação em razão do mesmo título apresentado, os emolumentos serão cobrados separadamente,  salvo disposição desta lei em contrário.', '', '', '', '', ''),
	(433, '16.30', 'Revogado pela Lei nº 9.490, de 04/11/11, pub. D.O.04/11/11', '', '', '', '', ''),
	(434, '16.31', 'No registro de gravames como hipoteca, penhor e alienação fiduciária, quando dois ou mais imóveis forem dados em garantia, ou no caso de penhor, quando a garantia esteja estipulada em mais de um imóvel, na mesma circunscrição imobiliária ou não, tenham ou não igual valor, a base de cálculo para cobrança, em relação a cada um dos registros, será o valor do mútuo dividido pelo número de imóveis dados em garantia, ou pelo número de imóveis de situação, conforme o caso, desde que decorrentes do mesmo título, limitados os emolumentos ao valor máximo do art. 37 desta Lei, por circunscrição. (Alterado pela Lei nº 9.490, de 04/11/11)', '', '', '', '', ''),
	(435, '16.32', 'REVOGADO', '', '', '', '', ''),
	(436, '16.33', 'Quando do registro de loteamento, desmembramento ou incorporação imobiliária, o Oficial deverá, desde logo, abrir matrícula específica para cada unidade, indicando como proprietário o próprio titular da área loteada, desmembrada ou incorporada, fazendo-se as remissões recíprocas. (Incluído pela Lei nº 9.490, de 04/11/11)', '', '', '', '', ''),
	(437, '16.34', 'Diligência e condução para prática de serviço externo', '41.50', '1.24', '1.65', '1.65', '46.04'),
	(438, '16.35', 'Hipoteca Judiciária, os emolumentos serão os mesmos do item 16.9 de acordo com o valor da condenação, em conformidade com art. 495 do NCPC', 'Informar Valor da Transação', '', '', '', ''),
	(439, '16.36', 'No registro de imóveis, pelo processamento da usucapião, serão devidos emolumentos equivalentes a 50% do valor previsto na tabela de emolumentos para o registro (item 16.3) e, caso o pedido seja deferido, também serão devidos emolumentos pela aquisição da propriedade equivalente a 50% do valor previsto na tabela de emolumentos para o registro (item 16.3), tomando-se por base o valor venal do imóvel relativo ao último lançamento do imposto predial e territorial urbano ou ao imposto territorial rural ou, quando não estipulado, o valor de mercado aproximado.', 'Informar Valor da Transação', '', '', '', ''),
	(440, '16.37', 'Na hipótese de usufruto, será considerada a terça parte do valor do imóvel que será enquadrado na tabela 16.3.', 'Informar Valor da Transação', '', '', '', ''),
	(441, '16.38', 'Serão gratuitos os emolumentos dos atos registrais relacionados à Ruerb de interesse social (Reurb-S) – regularização fundiária aplicável aos núcleos urbanos informais ocupados predominantemente por população de baixa renda, assim declarados em ato do Poder Executivo Municipal, nos termos da lei 13.465/2017', 'Orientação informativa.', '', '', '', ''),
	(442, '16.39', 'Arquivamento, por folha do documento, os emolumentos serão:', '5.65', '0.16', '0.22', '0.22', '6.25'),
	(443, '16.40', 'Os emolumentos devidos pelos atos relacionados com a primeira aquisição imobiliária para fins residenciais, financiada pelo Sistema Financeiro da Habitação, serão reduzidos em 50% (cinqüenta por cento), nos termos do art. 290 da Lei 6.015, de 31 de dezembro de 1973 – Orientação informativa.', '', '', '', '', ''),
	(444, '16.41', 'A redução do item 16.40 não se aplica aos contratos no âmbito do Sistema Financeiro Imobiliário - Orientação informativa.', '', '', '', '', ''),
	(445, '16.42', 'Conferência de documentos públicos, via internet, por documento, os emolumentos serão:', '5.65', '0.16', '0.22', '0.22', '6.25'),
	(446, '16.43', 'Averbação de consolidação da propriedade fiduciária, os emolumentos serão cobrados na tabela 16.9.', '', '', '', '', ''),
	(447, '16.44', 'Pelo ato de registro de constituição do Patrimônio Rural de Afetação, os emolumentos serão os mesmos do item 16.3, tendo como base de cálculo o valor do imóvel rural afetado, conforme itens 16.27', '', '', '', '', ''),
	(448, '16.45', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural:', '', '', '', '', ''),
	(449, '16.45.1', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: Até R$ 5.150,10', '86.34', '2.59', '3.45', '3.45', '95.83'),
	(450, '16.45.2', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 5.150,11 a R$ 6.695,12', '108.81', '3.26', '4.35', '4.35', '120.77'),
	(451, '16.45.3', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 6.695,13 a R$ 8.368,91', '123.22', '3.69', '4.92', '4.92', '136.75'),
	(452, '16.45.4', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 8.368,92 a R$ 10.461,13', '152.89', '4.58', '6.11', '6.11', '169.69'),
	(453, '16.45.5', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 10.461,14 a R$ 13.076,42', '190.14', '5.70', '7.60', '7.60', '211.04'),
	(454, '16.45.6', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 13.076,43 a R$ 16.345,52', '238.37', '7.15', '9.53', '9.53', '264.58'),
	(455, '16.45.7', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 16.345,53 a R$ 20.431,88', '299.06', '8.97', '11.96', '11.96', '331.95'),
	(456, '16.45.8', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 20.431,89 a R$ 25.539,86', '374.29', '11.22', '14.97', '14.97', '415.45'),
	(457, '16.45.9', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 25.539,87 a R$ 31.924,81', '466.12', '13.98', '18.64', '18.64', '517.38'),
	(458, '16.45.10', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 31.924,82 a R$ 39.906,02', '583.35', '17.50', '23.33', '23.33', '647.51'),
	(459, '16.45.11', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 39.906,03 a R$ 49.882,51', '729.89', '21.89', '29.19', '29.19', '810.16'),
	(460, '16.45.12', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 49.882,52 a R$ 62.353,12', '911.60', '27.34', '36.46', '36.46', '1011.86'),
	(461, '16.45.13', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 62.353,13 a R$ 77.941,42', '1139.47', '34.18', '45.57', '45.57', '1264.79'),
	(462, '16.45.14', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 77.941,43 a R$ 97.426,76', '1423.88', '42.71', '56.95', '56.95', '1580.49'),
	(463, '16.45.15', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 97.426,77 a R$ 121.783,45', '1779.36', '53.38', '71.17', '71.17', '1975.08'),
	(464, '16.45.16', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 121.783,46 a R$ 152.229,29', '2224.59', '66.73', '88.98', '88.98', '2469.28'),
	(465, '16.45.17', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 152.229,30 a R$ 190.286,63', '2780.84', '83.42', '111.23', '111.23', '3086.72'),
	(466, '16.45.18', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 190.286,64 a R$ 237.858,29', '3476.90', '104.30', '139.07', '139.07', '3859.34'),
	(467, '16.45.19', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 237.858,30 a R$ 297.322,86', '4344.54', '130.33', '173.78', '173.78', '4822.43'),
	(468, '16.45.20', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 297.322,87 a R$ 371.653,59', '5431.62', '162.94', '217.26', '217.26', '6029.08'),
	(469, '16.45.21', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 371.653,60 a R$ 464.566,98', '6788.82', '203.66', '271.55', '271.55', '7535.58'),
	(470, '16.45.22', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 464.566,99 a R$ 580.708,72', '8486.11', '254.58', '339.44', '339.44', '9419.57'),
	(471, '16.45.23', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 580.708,73 a R$ 725.885,91', '10608.25', '318.24', '424.33', '424.33', '11775.15'),
	(472, '16.45.24', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 725.885,92 a R$ 907.357,39', '12596.55', '377.89', '503.86', '503.86', '13982.16'),
	(473, '16.45.25', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 907.357,40 a R$ 1.134.196,75', '13443.31', '403.29', '537.73', '537.73', '14922.06'),
	(474, '16.45.26', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 1.134.196,76 a R$ 1.361.036,10', '13846.54', '415.39', '553.86', '553.86', '15369.65'),
	(475, '16.45.27', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 1.361.036,11 a R$ 1.633.243,31', '14261.98', '427.85', '570.47', '570.47', '15830.77'),
	(476, '16.45.28', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 1.633.243,32 a R$ 1.959.891,98', '14689.87', '440.69', '587.59', '587.59', '16305.74'),
	(477, '16.45.29', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 1.959.891,99 a R$ 2.351.870,39', '15130.59', '453.91', '605.22', '605.22', '16794.94'),
	(478, '16.45.30', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 2.351.870,40 a R$ 2.822.244,47', '15584.50', '467.53', '623.37', '623.37', '17298.77'),
	(479, '16.45.31', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 2.822.244,48 a R$ 3.386.693,36', '16051.96', '481.55', '642.07', '642.07', '17817.65'),
	(480, '16.45.32', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 3.386.693,37 a R$ 4.064.032,04', '16533.59', '496.00', '661.34', '661.34', '18352.27'),
	(481, '16.45.33', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 4.064.032,05 a R$ 4.876.838,45', '17029.50', '510.88', '681.18', '681.18', '18902.74'),
	(482, '16.45.34', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 4.876.838,46 a R$ 5.852.206,12', '17540.44', '526.21', '701.61', '701.61', '19469.87'),
	(483, '16.45.35', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: De R$ 5.852.206,13 a R$ 7.022.647,37', '18066.64', '541.99', '722.66', '722.66', '20053.95'),
	(484, '16.45.36', 'Pelo Registro de constituição de direitos reais de garantia mobiliária e imobiliária destinados ao crédito rural: Acima de R$ 7.022.647,37', '18608.59', '558.25', '744.34', '744.34', '20655.52'),
	(485, '17.1', 'Protesto de título de crédito (sobre o valor do título):', '', '', '', '', ''),
	(486, '17.1.1', 'Protesto de título de crédito (sobre o valor do título): Até R$ 62,28', '12.58', '0.37', '0.50', '0.50', '13.95'),
	(487, '17.1.2', 'Protesto de título de crédito (sobre o valor do título): De R$ 62,29 a R$ 201,49', '19.29', '0.57', '0.77', '0.77', '21.40'),
	(488, '17.1.3', 'Protesto de título de crédito (sobre o valor do título): De R$ 201,50 a R$ 378,56', '25.28', '0.75', '1.01', '1.01', '28.05'),
	(489, '17.1.4', 'Protesto de título de crédito (sobre o valor do título): De R$ 378,57 a R$ 757,12', '50.07', '1.50', '2.00', '2.00', '55.57'),
	(490, '17.1.5', 'Protesto de título de crédito (sobre o valor do título): De R$ 757,13 a R$ 1.514,24', '77.54', '2.32', '3.10', '3.10', '86.06'),
	(491, '17.1.6', 'Protesto de título de crédito (sobre o valor do título): De R$ 1.514,25 a R$ 2.902,71', '115.16', '3.45', '4.60', '4.60', '127.81'),
	(492, '17.1.7', 'Protesto de título de crédito (sobre o valor do título): De R$ 2.902,72 a R$ 4.291,17', '149.59', '4.48', '5.98', '5.98', '166.03'),
	(493, '17.1.8', 'Protesto de título de crédito (sobre o valor do título): De R$ 4.291,18 a R$ 5.679,63', '194.53', '5.83', '7.78', '7.78', '215.92'),
	(494, '17.1.9', 'Protesto de título de crédito (sobre o valor do título): De R$ 5.679,64 a R$ 7.068,10', '252.78', '7.58', '10.11', '10.11', '280.58'),
	(495, '17.1.10', 'Protesto de título de crédito (sobre o valor do título): De R$ 7.068,11 a R$ 8.456,56', '290.88', '8.72', '11.63', '11.63', '322.86'),
	(496, '17.1.11', 'Protesto de título de crédito (sobre o valor do título): De R$ 8.456,57 a R$ 9.845,03', '334.35', '10.03', '13.37', '13.37', '371.12'),
	(497, '17.1.12', 'Protesto de título de crédito (sobre o valor do título): De R$ 9.845,04 a R$ 11.233,49', '384.67', '11.54', '15.38', '15.38', '426.97'),
	(498, '17.1.13', 'Protesto de título de crédito (sobre o valor do título): De R$ 11.233,50 a R$ 12.621,95', '442.31', '13.26', '17.69', '17.69', '490.95'),
	(499, '17.1.14', 'Protesto de título de crédito (sobre o valor do título): De R$ 12.621,96 a R$ 16.787,34', '594.34', '17.83', '23.77', '23.77', '659.71'),
	(500, '17.1.15', 'Protesto de título de crédito (sobre o valor do título): De R$ 16.787,35 a R$ 20.952,74', '658.82', '19.76', '26.35', '26.35', '731.28'),
	(501, '17.1.16', 'Protesto de título de crédito (sobre o valor do título): De R$ 20.952,75 a R$ 25.118,13', '725.01', '21.75', '29.00', '29.00', '804.76'),
	(502, '17.1.17', 'Protesto de título de crédito (sobre o valor do título): De R$ 25.118,14 a R$ 33.448,91', '781.18', '23.43', '31.24', '31.24', '867.09'),
	(503, '17.1.18', 'Protesto de título de crédito (sobre o valor do título): De R$ 33.448,92 a R$ 41.779,69', '845.66', '25.36', '33.82', '33.82', '938.66'),
	(504, '17.1.19', 'Protesto de título de crédito (sobre o valor do título): De R$ 41.779,70 a R$ 54.275,86', '929.67', '27.89', '37.18', '37.18', '1031.92'),
	(505, '17.1.20', 'Protesto de título de crédito (sobre o valor do título): De R$ 54.275,87 a R$ 66.772,04', '990.12', '29.70', '39.60', '39.60', '1099.02'),
	(506, '17.1.21', 'Protesto de título de crédito (sobre o valor do título): De R$ 66.772,05 a R$ 83.433,60', '1048.37', '31.45', '41.93', '41.93', '1163.68'),
	(507, '17.1.22', 'Protesto de título de crédito (sobre o valor do título): De R$ 83.433,61 a R$ 100.095,17', '1102.10', '33.06', '44.08', '44.08', '1223.32'),
	(508, '17.1.23', 'Protesto de título de crédito (sobre o valor do título): De R$ 100.095,18 a R$ 116.756,73', '1162.79', '34.88', '46.51', '46.51', '1290.69'),
	(509, '17.1.24', 'Protesto de título de crédito (sobre o valor do título): De R$ 116.756,74 a R$ 133.418,30', '1237.41', '37.12', '49.49', '49.49', '1373.51'),
	(510, '17.1.25', 'Protesto de título de crédito (sobre o valor do título): Acima de R$ 133.418,30', '1313.61', '39.40', '52.54', '52.54', '1458.09'),
	(511, '17.2', 'Intimação ou edital por título, não incluídos os custos da publicação pela imprensa e postal, se houver. (Alterado pela Lei nº 9.490, de 04/11/11)', '8.73', '0.26', '0.34', '0.34', '9.67'),
	(512, '17.3', 'Averbação de documento que determine alteração ou cancelamento de protestos ou de quitação, com ou sem valor econômico', '28.51', '0.85', '1.14', '1.14', '31.64'),
	(513, '17.4', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de:', '', '', '', '', ''),
	(514, '17.4.1', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: Até R$ 62,28', '7.57', '0.22', '0.30', '0.30', '8.39'),
	(515, '17.4.2', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 62,29 a R$ 201,49', '11.36', '0.34', '0.45', '0.45', '12.60'),
	(516, '17.4.3', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 201,50 a R$ 378,56', '14.78', '0.44', '0.59', '0.59', '16.40'),
	(517, '17.4.4', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 378,57 a R$ 757,12', '29.31', '0.87', '1.17', '1.17', '32.52'),
	(518, '17.4.5', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 757,13 a R$ 1.514,24', '46.04', '1.38', '1.84', '1.84', '51.10'),
	(519, '17.4.6', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 1.514,25 a R$ 2.902,71', '68.87', '2.06', '2.75', '2.75', '76.43'),
	(520, '17.4.7', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 2.902,72 a R$ 4.291,17', '89.51', '2.68', '3.58', '3.58', '99.35'),
	(521, '17.4.8', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 4.291,18 a R$ 5.679,63', '116.38', '3.49', '4.65', '4.65', '129.17'),
	(522, '17.4.9', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 5.679,64 a R$ 7.068,10', '151.30', '4.53', '6.05', '6.05', '167.93'),
	(523, '17.4.10', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 7.068,11 a R$ 8.456,56', '174.02', '5.22', '6.96', '6.96', '193.16'),
	(524, '17.4.11', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 8.456,57 a R$ 9.845,03', '200.15', '6.00', '8.00', '8.00', '222.15'),
	(525, '17.4.12', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 9.845,04 a R$ 11.233,49', '230.07', '6.90', '9.20', '9.20', '255.37'),
	(526, '17.4.13', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 11.233,50 a R$ 12.621,95', '264.75', '7.94', '10.58', '10.58', '293.85'),
	(527, '17.4.14', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 12.621,96 a R$ 16.787,34', '356.58', '10.69', '14.26', '14.26', '395.79'),
	(528, '17.4.15', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 16.787,35 a R$ 20.952,74', '395.29', '11.85', '15.81', '15.81', '438.76'),
	(529, '17.4.16', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 20.952,75 a R$ 25.118,13', '434.98', '13.04', '17.39', '17.39', '482.80'),
	(530, '17.4.17', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 25.118,14 a R$ 33.448,91', '468.68', '14.06', '18.74', '18.74', '520.22'),
	(531, '17.4.18', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 33.448,92 a R$ 41.779,69', '507.39', '15.22', '20.29', '20.29', '563.19'),
	(532, '17.4.19', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 41.779,70 a R$ 54.275,86', '557.83', '16.73', '22.31', '22.31', '619.18'),
	(533, '17.4.20', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 54.275,87 a R$ 66.772,04', '593.97', '17.81', '23.75', '23.75', '659.28'),
	(534, '17.4.21', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 66.772,05 a R$ 83.433,60', '629.02', '18.87', '25.16', '25.16', '698.21'),
	(535, '17.4.22', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 83.433,61 a R$ 100.095,17', '661.26', '19.83', '26.45', '26.45', '733.99'),
	(536, '17.4.23', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 100.095,18 a R$ 116.756,73', '697.65', '20.92', '27.90', '27.90', '774.37'),
	(537, '17.4.24', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: De R$ 116.756,74 a R$ 133.418,30', '742.47', '22.27', '29.69', '29.69', '824.12'),
	(538, '17.4.25', 'Quando, após o apontamento e antes ou depois da intimação, ocorrer a liquidação do título ou a desistência do protesto, os emolumentos serão de: Acima de R$ 133.418,30', '788.14', '23.64', '31.52', '31.52', '874.82'),
	(539, '17.5', 'Das certidões:', '', '', '', '', ''),
	(540, '17.5.1', 'Com uma folha', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(541, '17.5.2', 'Por folha acrescida além da primeira, mais', '8.73', '0.26', '0.34', '0.34', '9.67'),
	(542, '17.5.3', 'REVOGADO', '', '', '', '', ''),
	(543, '17.5.4', 'Certidão em forma de relação, destinada aos órgãos restritivos de crédito ou entidades de classe e similares incluídas buscas e folhas excedentes - por registro.', '9.16', '0.27', '0.36', '0.36', '10.15'),
	(544, '17.5.5', 'Certidão Eletrônica de Protesto incluídas buscas de 05 (cinco) anos e folhas excedentes', '67.16', '2.01', '2.68', '2.68', '74.53'),
	(545, '17.6', 'Das buscas:', '', '', '', '', ''),
	(546, '17.6.1', 'REVOGADO', '', '', '', '', ''),
	(547, '17.6.2', 'Das buscas: Até cinco anos', '10.92', '0.32', '0.43', '0.43', '12.10'),
	(548, '17.6.3', 'Das buscas: Até dez anos', '17.47', '0.52', '0.69', '0.69', '19.37'),
	(549, '17.6.4', 'Das buscas: Até quinze anos', '22.23', '0.66', '0.88', '0.88', '24.65'),
	(550, '17.6.5', 'Das buscas: Até vinte anos', '28.51', '0.85', '1.14', '1.14', '31.64'),
	(551, '17.6.6', 'Das buscas: Até trinta anos', '37.26', '1.11', '1.49', '1.49', '41.35'),
	(552, '17.6.7', 'Das buscas: Até cinquenta anos', '43.80', '1.31', '1.75', '1.75', '48.61'),
	(553, '17.6.8', 'Das buscas: Acima de cinquenta anos', '56.91', '1.70', '2.27', '2.27', '63.15'),
	(554, '17.6.9', 'Se indicados dia, mês e ano da prática do ato, não serão cobradas buscas.', '', '', '', '', ''),
	(555, '17.7', 'Distribuição extrajudicial de títulos para protesto. (Alterado pela Lei nº 9.490, de 04/11/11)', '9.77', '0.29', '0.39', '0.39', '10.84'),
	(556, '17.7.1', 'Não estão sujeitos à distribuição os títulos rurais.', '', '', '', '', ''),
	(557, '17.7.2', 'Não estão sujeitos à nova distribuição os títulos cujos protestos tenham sido sustados por ordem judicial ou os evitados pelo devedor por motivo legal ou, ainda, os devolvidos ao apresentador por falta de requisito formal.', '', '', '', '', ''),
	(558, '17.7.3', 'Efetuada a distribuição, será entregue ao apresentante recibo com as características do título e a indicação do tabelionato para o qual foi distribuído, bem como dos emolumentos recebidos.', '', '', '', '', ''),
	(559, '17.7.4', 'O serviço de distribuição deverá efetuar as baixas das distribuições e expedir as certidões correspondentes no prazo de dois dias úteis, sendo os emolumentos os dos itens 17.5 e 17.6', '', '', '', '', ''),
	(560, '17.7.5', 'O serviço de distribuição não fornecerá certidão de ocorrência de distribuição, na qual conste averbação de baixa, salvo se a pedido escrito do próprio devedor ou por determinação judicial.', '', '', '', '', ''),
	(561, '17.8', 'Serão isentos de emolumentos os atos praticados em cumprimento de mandado judicial expedido em favor da parte beneficiária de assistência judiciária e sempre que assim for expressamente determinado pelo juiz.', '', '', '', '', ''),
	(562, '17.9', 'Arquivamento, por folha do documento, os emolumentos serão:', '5.52', '0.16', '0.22', '0.22', '6.12'),
	(563, '17.10', 'Da despesa de condução pela entrega da intimação procedida diretamente pelo tabelionato.', '', '', '', '', ''),
	(564, '17.10.1', 'Diligência para entrega de intimação na zona urbana.', '21.46', '0.64', '0.85', '0.85', '23.80'),
	(565, '17.10.2', 'Diligência para entrega de intimação na zona rural ou termo, distância de até 40 KM.', '55.88', '1.67', '2.23', '2.23', '62.01'),
	(566, '17.10.3', 'Diligências para entrega de intimação na zona rural ou termo, que ultrapasse à distância de 40 KM, será cobrado por KM percorrido', '1.28', '0.05', '0.05', '0.05', '1.43'),
	(567, '17.10.4', 'Na zona urbana, rural ou termo, Optando o Tabelionato pela intimação através dos Correios (EBCT) com Aviso de Recebimento (AR), a despesa de condução corresponderá ao custo total da postagem.', 'Orientação informativa', '', '', '', ''),
	(568, '17.11', 'Quando o apresentantes optar por receber os valores a Ele destinado através de cheque, será permitido ao tabelião repassar os valores correspondentes a compensação junto a rede bancária.', 'Orientação informativa', '', '', '', ''),
	(569, '17.12', 'Quando o devedor optar por pagar o título através de boleto bancário ou cartão de débito, será permitido ao tabelião repassar os valores correspondentes a operação do serviço praticado pela rede bancária.', 'Orientação informativa', '', '', '', ''),
	(570, '17.13', 'Nos protestos de Certidão da Dívida Ativa da Fazenda Pública, os emolumentos serão pagos exclusivamente pelo devedor no ato elisivo do protesto ou na data do pedido de cancelamento do protesto, observados os valores vigentes à época do ato elisivo ou do pedido de cancelamento.', 'Orientação informativa', '', '', '', ''),
	(571, '17.14', 'Os emolumentos referentes a títulos ou documentos de dívidas vencidos até um ano, a contar da data de sua apresentação, serão pagos exclusivamente pelo devedor no ato elisivo do protesto ou na data do pedido de cancelamento do protesto, observados os valores vigentes da tabela na data da prática do ato elisivo pelo tabelião.', 'Orientação informativa', '', '', '', ''),
	(572, '17.15', 'Quando se tratar de cheque vencido até três meses, os emolumentos serão pagos exclusivamente pelo devedor no ato elisivo do protesto ou na data do pedido de cancelamento do protesto, observados os valores vigentes da tabela na data da prática do ato elisivo pelo tabelião.', 'Orientação informativa', '', '', '', ''),
	(573, '18.1', 'Pela prenotação relativa a transações de embarcações, os emolumentos serão:', '35.45', '1.06', '1.41', '1.41', '39.33'),
	(574, '18.2', 'Pela lavratura de atos, contratos e instrumentos relativos a transações de embarcações a que as partes devam ou queiram dar forma legal de escritura pública, com valor declarado, os emolumentos serão:', '', '', '', '', ''),
	(575, '18.2.1', 'Até R$ 6.772,25', '136.94', '4.10', '5.47', '5.47', '151.98'),
	(576, '18.2.2', 'De R$ 6.772,26 a R$ 10.564,69', '171.24', '5.13', '6.84', '6.84', '190.05'),
	(577, '18.2.3', 'De R$ 10.564,70 a R$ 13.205,87', '193.59', '5.80', '7.74', '7.74', '214.87'),
	(578, '18.2.4', 'De R$ 13.205,88 a R$ 16.507,33', '242.02', '7.26', '9.68', '9.68', '268.64'),
	(579, '18.2.5', 'De R$ 16.507,34 a R$ 20.634,17', '301.11', '9.03', '12.04', '12.04', '334.22'),
	(580, '18.2.6', 'De R$ 20.634,18 a R$ 25.792,70', '376.01', '11.28', '15.04', '15.04', '417.37'),
	(581, '18.2.7', 'De R$ 25.792,71 a R$ 32.240,88', '470.43', '14.11', '18.81', '18.81', '522.16'),
	(582, '18.2.8', 'De R$ 32.240,89 a R$ 40.301,09', '589.39', '17.68', '23.57', '23.57', '654.21'),
	(583, '18.2.9', 'De R$ 40.301,10 a R$ 50.376,36', '736.87', '22.10', '29.47', '29.47', '817.91');
/*!40000 ALTER TABLE `tabela_emolumentos` ENABLE KEYS */;

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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Copiando dados para a tabela atlas.tarefas: 0 rows
/*!40000 ALTER TABLE `tarefas` DISABLE KEYS */;
/*!40000 ALTER TABLE `tarefas` ENABLE KEYS */;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
