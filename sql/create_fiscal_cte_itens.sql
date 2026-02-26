-- =============================================================================
-- fiscal_cte_itens: dados complementares do CT-e (modelo 57) - estilo fiscal_nfe_itens
-- Um registro por CT-e: tomador, valores, carga, ICMS, informações adicionais, protocolo
-- Idempotente: pode rodar mais de uma vez.
-- =============================================================================

CREATE TABLE IF NOT EXISTS fiscal_cte_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cte_id INT NOT NULL,
    -- Tomador do serviço (toma4)
    tomador_cnpj VARCHAR(18) NULL COMMENT 'CNPJ do tomador',
    tomador_nome VARCHAR(255) NULL COMMENT 'xNome do tomador',
    -- Valores (vPrest)
    valor_prestacao DECIMAL(15,2) NULL COMMENT 'vTPrest - valor total do serviço',
    valor_receber DECIMAL(15,2) NULL COMMENT 'vRec - valor líquido a receber',
    comp_nome VARCHAR(100) NULL COMMENT 'xNome do componente (ex: FRETE VALOR BASE)',
    comp_valor DECIMAL(15,2) NULL COMMENT 'vComp do componente',
    -- ICMS (imp/ICMS/ICMS00)
    icms_cst VARCHAR(3) NULL COMMENT 'CST do ICMS',
    icms_vbc DECIMAL(15,2) NULL COMMENT 'Base de cálculo',
    icms_picms DECIMAL(5,2) NULL COMMENT 'Alíquota %',
    icms_vicms DECIMAL(15,2) NULL COMMENT 'Valor do ICMS',
    -- Carga (infCTeNorm/infCarga)
    valor_carga DECIMAL(15,2) NULL COMMENT 'vCarga',
    produto_predominante VARCHAR(100) NULL COMMENT 'proPred',
    -- Informações adicionais (infAdic)
    inf_complementar TEXT NULL COMMENT 'infCpl - ex: placa, motorista',
    -- Protocolo (protCTe/infProt) - quando autorizado
    data_protocolo DATETIME NULL COMMENT 'dhRecbto',
    numero_protocolo VARCHAR(30) NULL COMMENT 'nProt',
    status_protocolo VARCHAR(5) NULL COMMENT 'cStat',
    motivo_protocolo VARCHAR(255) NULL COMMENT 'xMotivo',
    versao_aplicativo VARCHAR(20) NULL COMMENT 'verAplic',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cte_id) REFERENCES fiscal_cte(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cte_item (cte_id),
    INDEX idx_cte (cte_id),
    INDEX idx_tomador (tomador_cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dados complementares do CT-e (tomador, valores, carga, protocolo)';

