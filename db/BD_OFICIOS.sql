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


-- Copiando estrutura do banco de dados para oficios_db
CREATE DATABASE IF NOT EXISTS `oficios_db` /*!40100 DEFAULT CHARACTER SET latin1 */;
USE `oficios_db`;

-- Copiando estrutura para tabela oficios_db.cadastro_serventia
CREATE TABLE IF NOT EXISTS `cadastro_serventia` (
  `id` int(11) NOT NULL,
  `razao_social` text,
  `cidade` text,
  `status` int(11) DEFAULT NULL,
  `cns` tinytext
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela oficios_db.funcionarios
CREATE TABLE IF NOT EXISTS `funcionarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nome_completo` varchar(100) NOT NULL,
  `cargo` varchar(50) NOT NULL,
  `nivel_de_acesso` varchar(20) DEFAULT 'usuario',
  `status` varchar(20) DEFAULT 'ativo',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;

-- Exportação de dados foi desmarcado.

-- Copiando estrutura para tabela oficios_db.oficios
CREATE TABLE IF NOT EXISTS `oficios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `destinatario` varchar(255) NOT NULL,
  `tratamento` varchar(255) DEFAULT NULL,
  `cargo` varchar(255) DEFAULT NULL,
  `assunto` varchar(255) NOT NULL,
  `corpo` text NOT NULL,
  `assinante` varchar(255) NOT NULL,
  `cargo_assinante` varchar(255) NOT NULL,
  `data` date NOT NULL,
  `numero` varchar(50) NOT NULL DEFAULT '',
  `status` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

-- Exportação de dados foi desmarcado.

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
