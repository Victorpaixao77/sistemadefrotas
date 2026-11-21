-- Metas do dashboard BI por empresa (opcional — se vazio, o front usa estimativa)
CREATE TABLE IF NOT EXISTS bi_metas (
    empresa_id INT NOT NULL PRIMARY KEY,
    meta_receita DECIMAL(15,2) NULL COMMENT 'Meta de faturamento no período',
    meta_lucro DECIMAL(15,2) NULL COMMENT 'Meta de lucro operacional',
    meta_km DECIMAL(15,2) NULL COMMENT 'Meta de km rodados',
    meta_margem_pct DECIMAL(5,2) NULL DEFAULT 20.00 COMMENT 'Meta de margem operacional %',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bi_metas_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
