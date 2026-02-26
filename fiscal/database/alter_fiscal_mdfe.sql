-- =====================================================
-- Ajustes na tabela fiscal_mdfe (e fiscal_cte se precisar)
-- Execute no banco. Se der "Duplicate column name", pule a linha.
-- =====================================================

-- Colunas usadas pelo Criar MDF-e (uf, municípios, total de CT-e)
ALTER TABLE fiscal_mdfe ADD COLUMN uf_inicio VARCHAR(2) NULL AFTER veiculo_id;
ALTER TABLE fiscal_mdfe ADD COLUMN uf_fim VARCHAR(2) NULL AFTER uf_inicio;
ALTER TABLE fiscal_mdfe ADD COLUMN municipio_carregamento VARCHAR(100) NULL AFTER uf_fim;
ALTER TABLE fiscal_mdfe ADD COLUMN municipio_descarregamento VARCHAR(100) NULL AFTER municipio_carregamento;
ALTER TABLE fiscal_mdfe ADD COLUMN tipo_viagem VARCHAR(2) NULL DEFAULT '1' AFTER municipio_descarregamento;
ALTER TABLE fiscal_mdfe ADD COLUMN total_cte INT NULL DEFAULT 0 AFTER tipo_viagem;

-- Se fiscal_cte não tiver mdfe_id, descomente:
-- ALTER TABLE fiscal_cte ADD COLUMN mdfe_id INT NULL DEFAULT NULL, ADD INDEX idx_mdfe_id (mdfe_id);

-- Opcional: preencher total_cte em registros antigos a partir de fiscal_mdfe_cte
-- UPDATE fiscal_mdfe m SET m.total_cte = (SELECT COUNT(*) FROM fiscal_mdfe_cte c WHERE c.mdfe_id = m.id) WHERE m.total_cte IS NULL OR m.total_cte = 0;

-- =====================================================
-- Validação e encerramento MDF-e (prefixo fiscal_)
-- Execute se ainda não tiver as colunas.
-- =====================================================

-- Data de autorização (preenchida ao enviar para SEFAZ; usada para prazo de 24h para cancelar)
ALTER TABLE fiscal_mdfe ADD COLUMN data_autorizacao DATETIME NULL AFTER protocolo_autorizacao;

-- Data de encerramento do MDF-e (para histórico/auditoria)
ALTER TABLE fiscal_mdfe ADD COLUMN data_encerramento DATETIME NULL AFTER status;

-- RNTRC na configuração fiscal da empresa (obrigatório para emitir MDF-e rodoviário)
-- ALTER TABLE fiscal_config_empresa ADD COLUMN rntrc VARCHAR(20) NULL AFTER inscricao_estadual;
