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

-- Copiando dados para a tabela oficios_db.cadastro_serventia: 1 rows
/*!40000 ALTER TABLE `cadastro_serventia` DISABLE KEYS */;
INSERT IGNORE INTO `cadastro_serventia` (`id`, `razao_social`, `cidade`, `status`, `cns`) VALUES
	(1, 'Serventia Extrajudicial do OfÃ­cio Ãšnico de EsperantinÃ³polis-MA', 'EsperantinÃ³polis-MA', 1, '030072');
/*!40000 ALTER TABLE `cadastro_serventia` ENABLE KEYS */;

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
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;

-- Copiando dados para a tabela oficios_db.funcionarios: 2 rows
/*!40000 ALTER TABLE `funcionarios` DISABLE KEYS */;
INSERT IGNORE INTO `funcionarios` (`id`, `usuario`, `senha`, `nome_completo`, `cargo`, `nivel_de_acesso`, `status`) VALUES
	(1, 'TONY', 'MTMwNA==', 'Antonio José Martins Garcia', 'Escrevente Substituto', 'usuario', 'ativo'),
	(2, 'JEFFERSON', 'MTIzNA==', 'Jefferson Pereira de Freitas', 'Tabelião e Registrador', 'usuario', 'ativo');
/*!40000 ALTER TABLE `funcionarios` ENABLE KEYS */;

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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Copiando dados para a tabela oficios_db.oficios: 0 rows
/*!40000 ALTER TABLE `oficios` DISABLE KEYS */;
/*!40000 ALTER TABLE `oficios` ENABLE KEYS */;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
