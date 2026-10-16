-- =====================================================================
-- Migração 2FA (verificação em duas etapas) — banco "atlas"
-- Execute UMA vez no phpMyAdmin/console do banco "atlas".
-- (O login lê a tabela atlas.funcionarios; só este banco precisa das colunas.)
-- =====================================================================

ALTER TABLE `funcionarios`
  ADD COLUMN `tfa_secret`  VARCHAR(64) DEFAULT NULL,
  ADD COLUMN `tfa_enabled` TINYINT(1)  NOT NULL DEFAULT 0;

-- Caso seu servidor reclame que as colunas já existem, ignore este script:
-- significa que a migração já foi aplicada.
