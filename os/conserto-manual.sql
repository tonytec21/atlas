-- Conserto imediato, caso queira resolver antes de subir o arquivo.
-- A migração do módulo faz isto sozinha ao abrir a página; este script
-- só existe para quem preferir aplicar direto no banco.
--
-- Rodar com o banco "atlas" selecionado.

ALTER TABLE nfse_notas
  ADD COLUMN IF NOT EXISTS valor_reducao DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER valor_servico,
  ADD COLUMN IF NOT EXISTS base_calculo  DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER valor_reducao,
  ADD COLUMN IF NOT EXISTS aliquota      DECIMAL(5,2)  NOT NULL DEFAULT 0.00 AFTER base_calculo,
  ADD COLUMN IF NOT EXISTS valor_iss     DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER aliquota;

-- "IF NOT EXISTS" em ADD COLUMN exige MariaDB (o XAMPP usa MariaDB).
-- No MySQL puro, remova o IF NOT EXISTS e rode só as linhas que faltarem.
