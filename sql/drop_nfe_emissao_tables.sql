-- Remove tabelas de emissão de NF-e (não utilizadas no momento).
-- Execute apenas se tiver criado nfe_emissao_cab / nfe_emissao_item anteriormente.

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS nfe_emissao_item;
DROP TABLE IF EXISTS nfe_emissao_cab;
SET FOREIGN_KEY_CHECKS = 1;
