-- =============================================================================
-- gestao_montagem — posições custom (modo montagem) do veículo na Gestão Pro.
-- Guarda, por veículo, a posição de cada eixo (por id) e do tanque, em JSON.
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/gestao_montagem.sql
-- =============================================================================

CREATE TABLE IF NOT EXISTS gestao_montagem (
    veiculo_id INT NOT NULL PRIMARY KEY,
    empresa_id INT NOT NULL,
    config LONGTEXT NULL COMMENT 'JSON {"eixos":{"<eixo_id>":x,...},"tankX":x}',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Montagem custom (posições de eixos/tanque) por veículo';
