-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           5.7.19 - MySQL Community Server (GPL)
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Copiando estrutura do banco de dados para atlas
CREATE DATABASE IF NOT EXISTS `atlas` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `atlas`;

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
	(1, 'Serventia Extrajudicial do OfÃ­cio Ãšnico de EsperantinÃ³polis-MA', 'EsperantinÃ³polis-MA', 1, '030072');
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
	(1, 'https://selador.ma.portalselo.com.br', '443', 'cartorio', 'b81fb377ec0c');
/*!40000 ALTER TABLE `conexao_selador` ENABLE KEYS */;

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
	(1, 'ADMIN', 'MTMwNA==', 'ADMIN', 'ADMIN', 'administrador', 'ativo');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Copiando dados para a tabela atlas.selos: ~0 rows (aproximadamente)

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

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
