-- Último NSU da Distribuição DFe por empresa (para buscar NF-e do CNPJ automaticamente).
CREATE TABLE IF NOT EXISTS fiscal_distribuicao_nsu (
    empresa_id INT UNSIGNED NOT NULL PRIMARY KEY,
    ult_nsu VARCHAR(15) NOT NULL DEFAULT '0',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Último NSU consultado na Distribuição DFe (sincronizar NF-e pelo CNPJ)';
