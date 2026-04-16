-- =====================================================
-- Adicionar RNTRC na configuração fiscal da empresa
-- (obrigatório para emitir MDF-e rodoviário)
--
-- Execute no banco.
-- Se der "Duplicate column name", pule a linha.
-- =====================================================

ALTER TABLE fiscal_config_empresa
    ADD COLUMN rntrc VARCHAR(20) NULL AFTER inscricao_estadual;

