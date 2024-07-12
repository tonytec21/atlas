-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           8.0.30 - MySQL Community Server - GPL
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
CREATE DATABASE IF NOT EXISTS `atlas` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `atlas`;

-- Copiando estrutura para tabela atlas.registros_cedulas
CREATE TABLE IF NOT EXISTS `registros_cedulas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo_cedula` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `n_cedula` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `emissao_cedula` date NOT NULL,
  `vencimento_cedula` date NOT NULL,
  `valor_cedula` decimal(10,2) NOT NULL,
  `credor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `emitente` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `registro_garantia` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `forma_de_pagamento` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `vencimento_antecipado` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `juros` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `matricula` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` date NOT NULL,
  `funcionario` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avalista` text COLLATE utf8mb4_unicode_ci,
  `imovel_localizacao` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela atlas.registros_cedulas: ~0 rows (aproximadamente)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
