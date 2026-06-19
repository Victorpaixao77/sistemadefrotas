-- MDF-e Fase 2: vínculo PEF CIOT <-> MDF-e
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/mdfe_fase2_pef_link.sql

ALTER TABLE fiscal_mdfe_ciot
    ADD COLUMN IF NOT EXISTS pef_operacao_id INT UNSIGNED NULL AFTER ipef;

ALTER TABLE pef_ciot_operacoes
    ADD COLUMN IF NOT EXISTS mdfe_id INT NULL AFTER rota_id;

CREATE INDEX IF NOT EXISTS idx_pef_mdfe ON pef_ciot_operacoes (empresa_id, mdfe_id);
CREATE INDEX IF NOT EXISTS idx_mdfe_ciot_pef ON fiscal_mdfe_ciot (pef_operacao_id);
