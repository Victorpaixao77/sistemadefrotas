-- =============================================================================
-- fiscal_nfe_itens: estrutura MÍNIMA e CORRETA para NF-e modelo 55
-- Serve para qualquer tipo (combustível, peças, pneus, material) sem retrabalho.
-- Idempotente: pode rodar mais de uma vez.
-- =============================================================================

DELIMITER //

DROP PROCEDURE IF EXISTS alter_fiscal_nfe_itens_add_columns//

CREATE PROCEDURE alter_fiscal_nfe_itens_add_columns()
BEGIN
    DECLARE col_count INT;

    -- 1) Identificação fiscal do item
    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'numero_item_nfe';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN numero_item_nfe INT NULL COMMENT 'nItem do XML' AFTER nfe_id;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'gtin';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN gtin VARCHAR(20) NULL COMMENT 'cEAN/cEANTrib' AFTER numero_item_nfe;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'cest';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN cest VARCHAR(10) NULL AFTER ncm;
    END IF;

    -- 2) Impostos (genérico)
    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'cst_icms';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN cst_icms VARCHAR(3) NULL AFTER peso_liquido;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'cst_pis';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN cst_pis VARCHAR(3) NULL AFTER cst_icms;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'cst_cofins';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN cst_cofins VARCHAR(3) NULL AFTER cst_pis;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'cst_ipi';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN cst_ipi VARCHAR(3) NULL AFTER cst_cofins;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'valor_icms';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN valor_icms DECIMAL(12,2) NULL AFTER cst_ipi;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'valor_icms_st';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN valor_icms_st DECIMAL(12,2) NULL AFTER valor_icms;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'valor_ipi';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN valor_ipi DECIMAL(12,2) NULL AFTER valor_icms_st;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'valor_pis';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN valor_pis DECIMAL(12,2) NULL AFTER valor_ipi;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'valor_cofins';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN valor_cofins DECIMAL(12,2) NULL AFTER valor_pis;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'valor_total_tributos';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN valor_total_tributos DECIMAL(12,2) NULL AFTER valor_cofins;
    END IF;

    -- 3) Valores complementares
    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'valor_desconto';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN valor_desconto DECIMAL(12,2) NULL AFTER valor_total_tributos;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'valor_frete';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN valor_frete DECIMAL(12,2) NULL AFTER valor_desconto;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'valor_seguro';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN valor_seguro DECIMAL(12,2) NULL AFTER valor_frete;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'valor_outros';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN valor_outros DECIMAL(12,2) NULL AFTER valor_seguro;
    END IF;

    -- 4) Informações adicionais
    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'informacao_adicional_item';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN informacao_adicional_item TEXT NULL AFTER valor_outros;
    END IF;

    -- 5) Combustível (ANP) – opcional
    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'anp_codigo';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN anp_codigo VARCHAR(15) NULL AFTER informacao_adicional_item;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'anp_descricao';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN anp_descricao VARCHAR(100) NULL AFTER anp_codigo;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'percentual_biodiesel';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN percentual_biodiesel DECIMAL(5,2) NULL AFTER anp_descricao;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'uf_consumo';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN uf_consumo CHAR(2) NULL AFTER percentual_biodiesel;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'icms_monofasico_valor';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN icms_monofasico_valor DECIMAL(12,2) NULL AFTER uf_consumo;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'icms_monofasico_aliquota_adrem';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN icms_monofasico_aliquota_adrem DECIMAL(10,4) NULL AFTER icms_monofasico_valor;
    END IF;

    -- 6) Vinculação frota (infCpl / uso futuro)
    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'placa';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN placa VARCHAR(10) NULL AFTER icms_monofasico_aliquota_adrem;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'motorista_nome';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN motorista_nome VARCHAR(100) NULL AFTER placa;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'motorista_cpf';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN motorista_cpf VARCHAR(14) NULL AFTER motorista_nome;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'km_veiculo';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN km_veiculo INT NULL AFTER motorista_cpf;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'veiculo_id';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN veiculo_id INT UNSIGNED NULL COMMENT 'Vinculação com veiculos.id' AFTER km_veiculo;
    END IF;

    SELECT COUNT(*) INTO col_count FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_nfe_itens' AND COLUMN_NAME = 'rota_id';
    IF col_count = 0 THEN
        ALTER TABLE fiscal_nfe_itens ADD COLUMN rota_id INT UNSIGNED NULL COMMENT 'Vinculação com rotas' AFTER veiculo_id;
    END IF;

END//

DELIMITER ;

CALL alter_fiscal_nfe_itens_add_columns();
DROP PROCEDURE IF EXISTS alter_fiscal_nfe_itens_add_columns;
