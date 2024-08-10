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

-- Copiando estrutura para tabela atlas.indexador_nascimento
CREATE TABLE IF NOT EXISTS `indexador_nascimento` (
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela atlas.indexador_nascimento_anexos
CREATE TABLE IF NOT EXISTS `indexador_nascimento_anexos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_nascimento` int(11) NOT NULL,
  `caminho_anexo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `funcionario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('ativo','removido') COLLATE utf8mb4_unicode_ci DEFAULT 'ativo',
  PRIMARY KEY (`id`),
  KEY `id_nascimento` (`id_nascimento`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
